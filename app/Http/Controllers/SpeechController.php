<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpeechController extends Controller
{
    public function index()
    {
        return view('speech.index');
    }

    /**
     * Generate an MP3 file from the submitted text and return a
     * downloadable URL.
     *
     * Catatan: Google Translate TTS (endpoint gratis yang dipakai di sini)
     * hanya menyediakan SATU suara per bahasa, jadi pilihan "Male/Female"
     * tidak mempengaruhi hasil file MP3 — itu tetap dipakai untuk preview
     * suara langsung di browser (ResponsiveVoice). Kalau butuh suara pria/
     * wanita yang benar-benar berbeda di file MP3, perlu pindah ke layanan
     * berbayar seperti Google Cloud TTS, Azure Speech, atau ElevenLabs.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'text'  => 'required|string|max:5000',
            'voice' => 'required|in:Indonesian Male,Indonesian Female',
        ]);

        $text = trim($request->input('text'));
        $lang = 'id';

        // Endpoint TTS Google hanya menerima ±200 karakter per request,
        // jadi teks panjang dipecah dulu lalu hasil mp3-nya digabung.
        $chunks = $this->splitText($text, 200);

        $binary = '';
        foreach ($chunks as $chunk) {
            $audio = $this->fetchChunkAudio($chunk, $lang);

            if ($audio === '') {
                return response()->json([
                    'message' => 'Gagal mengambil audio dari layanan TTS. Coba lagi.',
                ], 502);
            }

            $binary .= $audio;
        }

        $filename = 'speech/' . Str::uuid() . '.mp3';
        Storage::disk('public')->put($filename, $binary);

        return response()->json([
            'url'      => Storage::disk('public')->url($filename),
            'filename' => 'text-to-speech-' . now()->format('Ymd-His') . '.mp3',
        ]);
    }

    private function fetchChunkAudio(string $text, string $lang): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            'Referer'    => 'https://translate.google.com/',
        ])
            ->timeout(15)
            ->get('https://translate.google.com/translate_tts', [
                'ie'     => 'UTF-8',
                'client' => 'tw-ob',
                'q'      => $text,
                'tl'     => $lang,
            ]);

        return $response->successful() ? $response->body() : '';
    }

    /**
     * Split text into chunks no longer than $limit characters,
     * breaking on word boundaries so words aren't cut in half.
     */
    private function splitText(string $text, int $limit): array
    {
        $words = preg_split('/\s+/', $text);
        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (mb_strlen($candidate) > $limit) {
                if ($current !== '') {
                    $chunks[] = $current;
                }
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
