<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    protected string $exporterUrl;
    protected int $timeout;

    public function __construct()
    {
        // Diambil dari config/services.php -> env NODE_EXPORTER_URL
        $this->exporterUrl = config('services.node_exporter.url', 'http://192.168.1.240:9100/metrics');
        $this->timeout     = (int) config('services.node_exporter.timeout', 3);
    }

    public function index()
    {
        return view('monitoring.index');
    }

    public function stats()
    {
        $metrics = $this->fetchMetrics();

        return response()->json([
            'cpu'          => $this->getCpuUsage($metrics),
            'ram'          => $this->getRamUsage($metrics),
            'disk'         => $this->getDiskUsage($metrics),
            'network'      => $this->getNetworkTraffic($metrics),
            'server_time'  => now()->format('H:i:s'),
            'exporter_ok'  => $metrics !== null,
            'exporter_url' => $this->exporterUrl,
        ]);
    }

    /**
     * Ambil teks mentah dari node_exporter (http://192.168.1.240:9100/metrics)
     * lalu parse ke array. Return null kalau exporter tidak bisa dihubungi.
     */
    protected function fetchMetrics(): ?array
    {
        try {
            $response = Http::timeout($this->timeout)->get($this->exporterUrl);

            if (!$response->ok()) {
                Log::warning('node_exporter merespon non-200', ['status' => $response->status()]);
                return null;
            }

            return $this->parseMetrics($response->body());
        } catch (\Throwable $e) {
            Log::warning('Gagal konek ke node_exporter: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse format teks Prometheus (yang diekspos node_exporter) menjadi:
     * ['nama_metric' => [ ['labels' => ['device' => 'eth0', ...], 'value' => 123.45], ... ] ]
     */
    protected function parseMetrics(string $body): array
    {
        $result = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:]*)(\{(.*)\})?\s+([0-9eE+\-.]+)\s*$/', $line, $m)) {
                continue;
            }

            $name      = $m[1];
            $labelsRaw = $m[3] ?? '';
            $value     = (float) $m[4];

            $labels = [];
            if ($labelsRaw !== '') {
                preg_match_all('/(\w+)="([^"]*)"/', $labelsRaw, $lm, PREG_SET_ORDER);
                foreach ($lm as $lmatch) {
                    $labels[$lmatch[1]] = $lmatch[2];
                }
            }

            $result[$name][] = ['labels' => $labels, 'value' => $value];
        }

        return $result;
    }

    /**
     * % CPU dihitung dari counter node_cpu_seconds_total (mode=idle vs total)
     * dengan membandingkan dua titik waktu (disimpan di cache antar polling).
     */
    protected function getCpuUsage(?array $metrics): array
    {
        if (!$metrics || !isset($metrics['node_cpu_seconds_total'])) {
            return ['percent' => 0, 'source' => 'node_exporter_unreachable'];
        }

        $idle = 0.0;
        $total = 0.0;

        foreach ($metrics['node_cpu_seconds_total'] as $row) {
            $total += $row['value'];
            if (($row['labels']['mode'] ?? '') === 'idle') {
                $idle += $row['value'];
            }
        }

        $now  = microtime(true);
        $prev = Cache::get('monitoring_cpu_snapshot');
        Cache::put('monitoring_cpu_snapshot', ['idle' => $idle, 'total' => $total, 'time' => $now], 60);

        if (!$prev) {
            return ['percent' => 0, 'source' => 'node_exporter_warmup']; // butuh 1x polling lagi
        }

        $totalDelta = $total - $prev['total'];
        $idleDelta  = $idle - $prev['idle'];
        $percent    = $totalDelta > 0 ? round((1 - ($idleDelta / $totalDelta)) * 100, 2) : 0;

        return ['percent' => max(min($percent, 100), 0), 'source' => 'node_exporter'];
    }

    /**
     * RAM dari gauge node_memory_MemTotal_bytes & node_memory_MemAvailable_bytes.
     */
    protected function getRamUsage(?array $metrics): array
    {
        if (!$metrics || !isset($metrics['node_memory_MemTotal_bytes'])) {
            return ['total_gb' => 0, 'used_gb' => 0, 'percent' => 0, 'source' => 'node_exporter_unreachable'];
        }

        $total     = $metrics['node_memory_MemTotal_bytes'][0]['value'] ?? 0;
        $available = $metrics['node_memory_MemAvailable_bytes'][0]['value'] ?? 0;
        $used      = max($total - $available, 0);

        return [
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb'  => round($used / 1024 / 1024 / 1024, 2),
            'percent'  => $total > 0 ? round(($used / $total) * 100, 2) : 0,
            'source'   => 'node_exporter',
        ];
    }

    /**
     * Storage capacity & sisa ruang dari node_filesystem_size_bytes /
     * node_filesystem_avail_bytes, per-mountpoint (skip filesystem virtual
     * seperti tmpfs/overlay/proc supaya tidak bias).
     */
    protected function getDiskUsage(?array $metrics): array
    {
        if (!$metrics || !isset($metrics['node_filesystem_size_bytes'])) {
            return ['main' => null, 'filesystems' => [], 'source' => 'node_exporter_unreachable'];
        }

        $ignoreFstypes = ['tmpfs', 'devtmpfs', 'overlay', 'squashfs', 'proc', 'sysfs', 'cgroup', 'cgroup2', 'devpts', 'debugfs', 'mqueue', 'tracefs', 'ramfs', 'iso9660'];
        $ignoreMountPrefixes = ['/boot', '/snap', '/dev', '/run', '/sys', '/proc'];

        // Index nilai avail berdasarkan device+mountpoint agar mudah dipasangkan dengan size
        $availByKey = [];
        foreach (($metrics['node_filesystem_avail_bytes'] ?? []) as $row) {
            $key = ($row['labels']['device'] ?? '') . '|' . ($row['labels']['mountpoint'] ?? '');
            $availByKey[$key] = $row['value'];
        }

        $filesystems = [];

        foreach ($metrics['node_filesystem_size_bytes'] as $row) {
            $labels = $row['labels'];
            $fstype = $labels['fstype'] ?? '';
            $mount  = $labels['mountpoint'] ?? '';
            $device = $labels['device'] ?? '';
            $total  = $row['value'];

            if ($total <= 0 || in_array($fstype, $ignoreFstypes)) {
                continue;
            }

            $skip = false;
            foreach ($ignoreMountPrefixes as $prefix) {
                if ($mount !== '/' && str_starts_with($mount, $prefix)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $avail = $availByKey[$device . '|' . $mount] ?? 0;
            $used  = max($total - $avail, 0);

            $filesystems[] = [
                'mountpoint' => $mount,
                'device'     => $device,
                'fstype'     => $fstype,
                'total_gb'   => round($total / (1024 ** 3), 2),
                'used_gb'    => round($used / (1024 ** 3), 2),
                'avail_gb'   => round($avail / (1024 ** 3), 2),
                'percent'    => $total > 0 ? round(($used / $total) * 100, 2) : 0,
            ];
        }

        // Root filesystem ("/") ditaruh paling atas, sisanya urut abjad mountpoint
        usort($filesystems, function ($a, $b) {
            if ($a['mountpoint'] === '/') return -1;
            if ($b['mountpoint'] === '/') return 1;
            return strcmp($a['mountpoint'], $b['mountpoint']);
        });

        $main = null;
        foreach ($filesystems as $fs) {
            if ($fs['mountpoint'] === '/') {
                $main = $fs;
                break;
            }
        }
        $main = $main ?? ($filesystems[0] ?? null);

        return [
            'main'        => $main,
            'filesystems' => $filesystems,
            'source'      => 'node_exporter',
        ];
    }

    protected function getNetworkTraffic(?array $metrics): array
    {
        if (!$metrics || !isset($metrics['node_network_receive_bytes_total'])) {
            return ['rx_mbps' => 0, 'tx_mbps' => 0, 'source' => 'node_exporter_unreachable'];
        }

        $ignorePrefixes = ['lo', 'docker', 'veth', 'virbr', 'br-'];

        $rx = 0.0;
        foreach ($metrics['node_network_receive_bytes_total'] as $row) {
            $dev = $row['labels']['device'] ?? '';
            if ($this->shouldSkipInterface($dev, $ignorePrefixes)) continue;
            $rx += $row['value'];
        }

        $tx = 0.0;
        foreach (($metrics['node_network_transmit_bytes_total'] ?? []) as $row) {
            $dev = $row['labels']['device'] ?? '';
            if ($this->shouldSkipInterface($dev, $ignorePrefixes)) continue;
            $tx += $row['value'];
        }

        $now  = microtime(true);
        $prev = Cache::get('monitoring_net_snapshot');
        Cache::put('monitoring_net_snapshot', ['rx' => $rx, 'tx' => $tx, 'time' => $now], 60);

        if (!$prev) {
            return ['rx_mbps' => 0, 'tx_mbps' => 0, 'source' => 'node_exporter_warmup'];
        }

        $interval = max($now - $prev['time'], 0.001);
        $rxMbps   = max((($rx - $prev['rx']) * 8) / $interval / 1_000_000, 0);
        $txMbps   = max((($tx - $prev['tx']) * 8) / $interval / 1_000_000, 0);

        return [
            'rx_mbps' => round($rxMbps, 3),
            'tx_mbps' => round($txMbps, 3),
            'source'  => 'node_exporter',
        ];
    }

    protected function shouldSkipInterface(string $device, array $ignorePrefixes): bool
    {
        foreach ($ignorePrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($device, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
