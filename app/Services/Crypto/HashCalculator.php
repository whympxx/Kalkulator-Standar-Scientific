<?php

namespace App\Services\Crypto;

use App\Services\Calculator\CalculationException;

/**
 * Fungsi hash dan encoding (Base64 / Hex).
 */
class HashCalculator
{
    public const ACTIONS = ['hash', 'base64_encode', 'base64_decode', 'hex_encode', 'hex_decode'];

    private const ALGORITHMS = [
        'md5' => 'MD5',
        'sha1' => 'SHA-1',
        'sha256' => 'SHA-256',
        'sha512' => 'SHA-512',
        'sha3-256' => 'SHA3-256',
        'crc32b' => 'CRC32',
    ];

    public function calculate(string $action, string $text): CryptoResult
    {
        return match ($action) {
            'hash' => $this->hash($text),
            'base64_encode' => $this->encode('Base64', base64_encode($text), $text),
            'hex_encode' => $this->encode('Hex', bin2hex($text), $text),
            'base64_decode' => $this->decode('Base64', $this->base64Decode($text)),
            'hex_decode' => $this->decode('Hex', $this->hexDecode($text)),
            default => throw new CalculationException('Aksi hash/encoding tidak dikenal.'),
        };
    }

    private function hash(string $text): CryptoResult
    {
        $details = [];
        foreach (self::ALGORITHMS as $algo => $label) {
            $details[] = CryptoResult::detail($label, hash($algo, $text));
        }

        return new CryptoResult(
            'SHA-256('.$this->preview($text).')',
            hash('sha256', $text),
            $details,
            [
                'Teks diubah ke byte UTF-8 ('.strlen($text).' byte), lalu dihitung digest-nya.',
                'Hash bersifat satu arah: tidak bisa dikembalikan ke teks asal.',
                'MD5 dan SHA-1 sudah tidak aman untuk keamanan; gunakan SHA-256 ke atas.',
            ],
        );
    }

    private function encode(string $label, string $encoded, string $text): CryptoResult
    {
        return new CryptoResult(
            "{$label} encode(".$this->preview($text).')',
            $encoded,
            [CryptoResult::detail('Ukuran', strlen($text).' byte → '.strlen($encoded).' karakter')],
            ['Encoding bukan enkripsi: siapa pun bisa men-decode tanpa kunci.'],
        );
    }

    private function decode(string $label, string $bytes): CryptoResult
    {
        if (! mb_check_encoding($bytes, 'UTF-8')) {
            throw new CalculationException("Hasil decode {$label} bukan teks UTF-8 (kemungkinan data biner).");
        }

        return new CryptoResult("{$label} decode", $bytes, [
            CryptoResult::detail('Ukuran', strlen($bytes).' byte'),
        ]);
    }

    private function base64Decode(string $text): string
    {
        $clean = preg_replace('/\s+/', '', $text);
        // Dukung juga varian URL-safe (-, _) dan padding yang hilang.
        $clean = strtr($clean, '-_', '+/');
        $clean .= str_repeat('=', (4 - strlen($clean) % 4) % 4);
        $decoded = base64_decode($clean, true);
        if ($decoded === false || $clean === '') {
            throw new CalculationException('Teks bukan Base64 yang valid.');
        }

        return $decoded;
    }

    private function hexDecode(string $text): string
    {
        $clean = preg_replace('/[\s:]+|^0x/i', '', $text);
        if ($clean === '' || strlen($clean) % 2 !== 0 || ! ctype_xdigit($clean)) {
            throw new CalculationException('Teks bukan Hex yang valid (harus pasangan karakter 0-9 / a-f).');
        }

        return hex2bin($clean);
    }

    private function preview(string $text): string
    {
        return '"'.mb_strimwidth($text, 0, 30, '…').'"';
    }
}
