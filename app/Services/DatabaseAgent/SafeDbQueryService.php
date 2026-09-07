<?php

namespace App\Services\DatabaseAgent;

use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SafeDbQueryService
{
    protected AiManager $ai;
    protected static ?string $cachedSchema = null;

    public function __construct(AiManager $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Generate and safely execute a SQL query based on user prompt.
     *
     * @param string $userPrompt
     * @return array ['success' => bool, 'sql' => string, 'data' => array, 'error' => ?string]
     */
    public function queryFromPrompt(string $userPrompt): array
    {
        $driver = DB::connection()->getDriverName();
        $isSqlServer = in_array($driver, ['sqlsrv', 'odbc', 'dblib']);
        $schema = $this->getSchemaSummary();

        $dialectRules = $isSqlServer ? <<<RULES
Database Engine: Microsoft SQL Server (T-SQL).
- Use Microsoft SQL Server syntax (T-SQL).
- Use `CAST(GETDATE() AS DATE)` or `GETDATE()` for current date (NEVER USE CURDATE() or NOW()).
- Use DATEADD(day, -N, GETDATE()), DATEDIFF(), YEAR(), MONTH() for date arithmetic.
- Use `TOP 100` in SELECT clause (e.g. `SELECT TOP 100 ...`) for row limiting. NEVER USE `LIMIT 100`.
- Use `ISNULL(col, default)` or `COALESCE(col, default)`.
- Use `CONCAT(...)` or `+` for string concatenation.
RULES
        : <<<RULES
Database Engine: MySQL.
- Use MySQL syntax.
- Use CURDATE(), NOW(), DATE_SUB(), YEAR(), MONTH() for dates.
- Use `LIMIT 100` at the end of the query for row limiting.
- Use `IFNULL(col, default)` or `COALESCE(col, default)`.
RULES;

        $systemPrompt = <<<PROMPT
You are an expert SQL Assistant for an enterprise HR, Payroll, and Operations ERP system.
Your job is to generate a single safe, accurate, read-only SQL query based on the user's request.

{$dialectRules}

DATABASE SCHEMA & AVAILABLE TABLES/FIELDS:
{$schema}

STRICT RULES:
1. ONLY generate a single valid SELECT query.
2. NEVER generate INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, GRANT, EXEC, or any modifying statements.
3. Join tables logically using available foreign keys (e.g., biodatas.id = employees_contracts.biodata_id).
4. Always use explicit column aliases when aggregating (e.g., COUNT(*) as total_karyawan).
5. Output MUST BE a valid JSON object with the following structure:
{
    "sql": "SELECT ...",
    "explanation": "Penjelasan singkat dalam bahasa Indonesia mengenai data apa yang diambil"
}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            $aiResponse = $this->ai->chatJson($messages, ['temperature' => 0.1]);
            $sql = trim($aiResponse['sql'] ?? '');
            $explanation = $aiResponse['explanation'] ?? '';

            if (empty($sql)) {
                return [
                    'success' => false,
                    'sql' => '',
                    'data' => [],
                    'error' => 'AI did not generate a SQL query.',
                ];
            }

            // Clean & validate SQL
            $sanitizedSql = $this->sanitizeAndValidateSql($sql, $isSqlServer);

            // Execute query
            $results = DB::select($sanitizedSql);

            // Convert stdClass to array
            $data = array_map(fn($row) => (array) $row, $results);

            return [
                'success' => true,
                'sql' => $sanitizedSql,
                'explanation' => $explanation,
                'data' => $data,
                'row_count' => count($data),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error("SafeDbQueryService error: {$e->getMessage()} | SQL: " . ($sql ?? 'N/A'));
            return [
                'success' => false,
                'sql' => $sql ?? '',
                'data' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate and sanitize SQL to ensure it's strictly read-only SELECT.
     */
    public function sanitizeAndValidateSql(string $sql, bool $isSqlServer = false): string
    {
        $cleaned = trim($sql);
        // Remove trailing semicolon and backticks wrappers
        $cleaned = rtrim($cleaned, ';');
        $cleaned = trim(preg_replace('/^```(?:sql)?|```$/m', '', $cleaned));

        // Check for disallowed multiple statements
        if (str_contains($cleaned, ';')) {
            throw new RuntimeException('Multi-statement SQL execution is strictly prohibited.');
        }

        // Must start with SELECT or WITH
        if (!preg_match('/^(select|with)\s/i', $cleaned)) {
            throw new RuntimeException('Only SELECT queries are allowed.');
        }

        // Check for forbidden keywords
        $forbiddenKeywords = config('ai.sql_guardrails.forbidden_keywords', [
            'insert', 'update', 'delete', 'drop', 'alter', 'truncate',
            'create', 'grant', 'revoke', 'replace', 'exec', 'execute',
            'outfile', 'dumpfile', 'load_file', 'into'
        ]);

        foreach ($forbiddenKeywords as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            if (preg_match($pattern, $cleaned)) {
                throw new RuntimeException("Security Exception: Forbidden SQL keyword '{$keyword}' detected.");
            }
        }

        $maxLimit = config('ai.sql_guardrails.max_limit', 100);

        if ($isSqlServer) {
            // Remove any inadvertent LIMIT clause generated for SQL Server
            $cleaned = preg_replace('/\s+limit\s+\d+/i', '', $cleaned);

            // If aggregation (COUNT, SUM, AVG) without GROUP BY, TOP is not strictly needed, but safe
            if (!preg_match('/\b(top\s+\d+|fetch\s+next\s+\d+)/i', $cleaned) && !preg_match('/^\s*select\s+count\(/i', $cleaned)) {
                $cleaned = preg_replace('/^\s*select\s+/i', "SELECT TOP {$maxLimit} ", $cleaned, 1);
            }
        } else {
            // MySQL / SQLite / PostgreSQL
            if (!preg_match('/\blimit\s+\d+/i', $cleaned) && !preg_match('/^\s*select\s+count\(/i', $cleaned)) {
                $cleaned .= " LIMIT {$maxLimit}";
            }
        }

        return $cleaned;
    }

    /**
     * Introspect database schema and create a compact schema representation.
     */
    public function getSchemaSummary(): string
    {
        if (static::$cachedSchema !== null) {
            return static::$cachedSchema;
        }

        try {
            $driver = DB::connection()->getDriverName();
            $isSqlServer = in_array($driver, ['sqlsrv', 'odbc', 'dblib']);

            $schemaLines = [];

            // Prioritized / Core domain tables description hints
            $coreTableHints = [
                'biodatas' => 'Employee personal bio (nik, nama, alamat, no_ktp, status_pernikahan, jenis_kelamin, agama, dept_id, status_karyawan)',
                'employees_contracts' => 'PKWT / Contract details (biodata_id, contract_number, contract_type, start_date, end_date, duration, status)',
                'depts' => 'Department master (id, name, code, parent_id)',
                'parent_depts' => 'Parent department divisions (id, name)',
                'attendances' => 'Attendance records (biodata_id, date, clock_in, clock_out, status, shift_id, late_minutes)',
                'attendance_fingers' => 'Fingerprint raw logs (pin, scan_date, verify_mode)',
                'leave_requests' => 'Leave/Cuti requests (biodata_id, leave_type_id, start_date, end_date, total_days, reason, status)',
                'leave_balances' => 'Employee leave balance (biodata_id, year, balance, used, remaining)',
                'leave_types' => 'Leave categories (id, name, max_days)',
                'payroll_runs' => 'Payroll batch run (id, period_id, status, total_amount)',
                'payroll_run_details' => 'Individual payroll breakdown (payroll_run_id, biodata_id, basic_salary, allowance, deduction, net_salary)',
                'payroll_periods' => 'Payroll period definition (id, name, start_date, end_date, year, month)',
                'overtimes' => 'Overtime submissions (biodata_id, date, start_time, end_time, total_hours, status)',
                'mon_orders' => 'Monitoring orders (id, order_no, customer, style, qty, delivery_date, status)',
                'mon_rekonsiliasis' => 'Reconciliation monitoring (id, order_id, stage, status, discrepancy_qty)',
                'mon_work_orders' => 'Work orders monitoring (id, wo_no, qty, line_id, status)',
                'mon_shipments' => 'Shipment monitoring (id, shipment_no, container_no, etd, eta, status)',
                'canteen_reports' => 'Canteen meal transaction reports (biodata_id, date, meal_type, price)',
                'health_tests' => 'Health test / MCU records (biodata_id, test_date, result, doctor_note)',
                'employee_lates' => 'Employee late arrival logs (biodata_id, date, minutes, penalty, status)',
                'employee_violations' => 'SP / Violation records (biodata_id, violation_type, date, description, status)',
            ];

            // Standard ANSI / INFORMATION_SCHEMA query that works on both SQL Server and MySQL
            $tablesQuery = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'";
            $tables = DB::select($tablesQuery);

            foreach ($tables as $t) {
                $tableName = $t->TABLE_NAME ?? $t->table_name ?? null;
                if (!$tableName || str_starts_with($tableName, 'failed_') || str_starts_with($tableName, 'migrations') || str_starts_with($tableName, 'sys')) {
                    continue;
                }

                // Query column names and types from INFORMATION_SCHEMA.COLUMNS
                $colQuery = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION";
                $columns = DB::select($colQuery, [$tableName]);

                $colSummary = [];
                foreach ($columns as $c) {
                    $colName = $c->COLUMN_NAME ?? $c->column_name;
                    $dataType = $c->DATA_TYPE ?? $c->data_type;
                    $colSummary[] = "{$colName} ({$dataType})";
                }

                $hint = $coreTableHints[$tableName] ?? '';
                $hintStr = $hint ? " -- {$hint}" : "";

                $schemaLines[] = "TABLE {$tableName} (" . implode(', ', array_slice($colSummary, 0, 25)) . ")" . $hintStr;
            }

            if (!empty($schemaLines)) {
                static::$cachedSchema = implode("\n", $schemaLines);
                return static::$cachedSchema;
            }

            throw new RuntimeException("No tables discovered.");
        } catch (\Throwable $e) {
            Log::error('Schema introspection fallback: ' . $e->getMessage());
            return "TABLE biodatas (id, nik, nama, dept_id, status_karyawan, jenis_kelamin, status_pernikahan, agama, tgl_masuk)\n" .
                   "TABLE employees_contracts (id, biodata_id, contract_number, contract_type, start_date, end_date, duration, status)\n" .
                   "TABLE depts (id, name, code, parent_id)\n" .
                   "TABLE attendances (id, biodata_id, date, clock_in, clock_out, status, late_minutes)\n" .
                   "TABLE leave_requests (id, biodata_id, leave_type_id, start_date, end_date, total_days, status)\n" .
                   "TABLE payroll_run_details (id, payroll_run_id, biodata_id, basic_salary, allowance, deduction, net_salary)";
        }
    }
}
