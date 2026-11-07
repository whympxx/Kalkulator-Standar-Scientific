<?php

namespace App\Services\Crypto;

use App\Services\Calculator\CalculationException;

/**
 * Cipher klasik alfabet A–Z. Huruf besar/kecil dipertahankan, karakter lain tidak diubah.
 */
class ClassicCipher
{
    public const CIPHERS = ['caesar', 'vigenere', 'affine'];

    public function calculate(string $cipher, string $action, string $text, array $params): CryptoResult
    {
        if ($action === 'bruteforce') {
            if ($cipher !== 'caesar') {
                throw new CalculationException('Brute force hanya tersedia untuk Caesar.');
            }

            return $this->caesarBruteForce($text);
        }

        $decrypt = $action === 'decrypt';

        return match ($cipher) {
            'caesar' => $this->caesar($text, $this->int($params['shift'] ?? null, 'Geseran'), $decrypt),
            'vigenere' => $this->vigenere($text, (string) ($params['key'] ?? ''), $decrypt),
            'affine' => $this->affine($text, $this->int($params['a'] ?? null, 'a'), $this->int($params['b'] ?? null, 'b'), $decrypt),
            default => throw new CalculationException('Cipher tidak dikenal.'),
        };
    }

    private function caesar(string $text, int $shift, bool $decrypt): CryptoResult
    {
        $k = $this->mod26($decrypt ? -$shift : $shift);
        $result = $this->mapLetters($text, fn (int $x) => ($x + $k) % 26);
        $label = $decrypt ? 'Dekripsi' : 'Enkripsi';
        $formula = $decrypt ? "P = (C − {$shift}) mod 26" : "C = (P + {$shift}) mod 26";

        return new CryptoResult(
            "Caesar {$label} k={$shift}: ".$this->preview($text),
            $result,
            [CryptoResult::detail('Geseran efektif', (string) $this->mod26($shift))],
            [$formula, ...$this->letterSteps($text, $result)],
        );
    }

    private function caesarBruteForce(string $text): CryptoResult
    {
        $details = [];
        for ($k = 1; $k < 26; $k++) {
            $details[] = CryptoResult::detail("k = {$k}", $this->mapLetters($text, fn (int $x) => ($x - $k + 26) % 26));
        }

        return new CryptoResult(
            'Caesar brute force: '.$this->preview($text),
            'Semua 25 kemungkinan kunci ditampilkan di bawah.',
            $details,
            ['Mencoba dekripsi dengan setiap geseran k = 1 … 25.', 'Cari baris yang membentuk kalimat bermakna.'],
        );
    }

    private function vigenere(string $text, string $key, bool $decrypt): CryptoResult
    {
        $shifts = array_map(fn ($c) => ord($c) - 65, str_split(preg_replace('/[^A-Z]/', '', strtoupper($key))));
        if ($shifts === []) {
            throw new CalculationException('Kunci Vigenère harus berisi minimal satu huruf A–Z.');
        }

        $i = 0;
        $count = count($shifts);
        $result = $this->mapLetters($text, function (int $x) use (&$i, $shifts, $count, $decrypt) {
            $k = $shifts[$i++ % $count];

            return $decrypt ? ($x - $k + 26) % 26 : ($x + $k) % 26;
        });

        $cleanKey = implode('', array_map(fn ($s) => chr($s + 65), $shifts));

        return new CryptoResult(
            'Vigenère '.($decrypt ? 'Dekripsi' : 'Enkripsi')." kunci={$cleanKey}: ".$this->preview($text),
            $result,
            [
                CryptoResult::detail('Kunci', $cleanKey),
                CryptoResult::detail('Geseran kunci', implode(', ', $shifts)),
            ],
            [
                $decrypt ? 'Pᵢ = (Cᵢ − Kᵢ) mod 26' : 'Cᵢ = (Pᵢ + Kᵢ) mod 26',
                'Kunci diulang hanya untuk huruf; spasi & tanda baca dilewati.',
                ...$this->letterSteps($text, $result),
            ],
        );
    }

    private function affine(string $text, int $a, int $b, bool $decrypt): CryptoResult
    {
        $a26 = $this->mod26($a);
        $inverse = BigMath::inverse((string) $a26, '26');
        if ($inverse === null) {
            throw new CalculationException("a = {$a} tidak valid: gcd(a, 26) harus 1. Pilihan a: 1, 3, 5, 7, 9, 11, 15, 17, 19, 21, 23, 25.");
        }
        $inv = (int) $inverse;
        $b26 = $this->mod26($b);

        $result = $decrypt
            ? $this->mapLetters($text, fn (int $y) => ($inv * ($y - $b26 + 26)) % 26)
            : $this->mapLetters($text, fn (int $x) => ($a26 * $x + $b26) % 26);

        return new CryptoResult(
            'Affine '.($decrypt ? 'Dekripsi' : 'Enkripsi')." a={$a}, b={$b}: ".$this->preview($text),
            $result,
            [CryptoResult::detail('a⁻¹ mod 26', (string) $inv)],
            [
                $decrypt ? "P = a⁻¹ (C − b) mod 26 = {$inv}(C − {$b26}) mod 26" : "C = (a·P + b) mod 26 = ({$a26}P + {$b26}) mod 26",
                ...$this->letterSteps($text, $result),
            ],
        );
    }

    /** Terapkan fungsi pada indeks huruf (A=0 … Z=25). */
    private function mapLetters(string $text, callable $fn): string
    {
        return preg_replace_callback('/[A-Za-z]/', function ($m) use ($fn) {
            $base = ctype_upper($m[0]) ? 65 : 97;

            return chr($fn(ord($m[0]) - $base) + $base);
        }, $text);
    }

    /** @return list<string> */
    private function letterSteps(string $from, string $to): array
    {
        $steps = [];
        $len = min(strlen($from), strlen($to));
        for ($i = 0; $i < $len && count($steps) < 12; $i++) {
            if (ctype_alpha($from[$i])) {
                $x = ord(strtoupper($from[$i])) - 65;
                $y = ord(strtoupper($to[$i])) - 65;
                $steps[] = "{$from[$i]} ({$x}) → {$to[$i]} ({$y})";
            }
        }

        return $steps;
    }

    private function preview(string $text): string
    {
        return '"'.mb_strimwidth($text, 0, 30, '…').'"';
    }

    private function mod26(int $x): int
    {
        return (($x % 26) + 26) % 26;
    }

    private function int(mixed $value, string $label): int
    {
        if (! is_scalar($value) || ! preg_match('/^\s*-?\d{1,9}\s*$/', (string) $value)) {
            throw new CalculationException("{$label} harus bilangan bulat.");
        }

        return (int) $value;
    }
}
