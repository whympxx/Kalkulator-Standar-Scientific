<?php

namespace App\Services\Calculator;

/**
 * Mengubah hasil float menjadi teks yang rapi:
 * 0.1 + 0.2 -> "0.3", 1e21 -> "1e+21", -0 -> "0".
 */
class ResultFormatter
{
    /** Jumlah digit signifikan yang ditampilkan. */
    public const PRECISION = 12;

    public function format(float $value): string
    {
        // Bulatkan ke 12 digit signifikan untuk membuang galat floating point.
        $value = (float) sprintf('%.'.self::PRECISION.'g', $value);

        if ($value == 0.0) {
            return '0';
        }

        $abs = abs($value);

        if ($abs >= 1e15 || $abs < 1e-9) {
            [$mantissa, $exponent] = explode('e', sprintf('%.'.(self::PRECISION - 1).'e', $value));
            $mantissa = $this->trimZeros($mantissa);
            $exponent = (int) $exponent;

            return $mantissa.'e'.($exponent >= 0 ? '+' : '').$exponent;
        }

        $integerDigits = $abs >= 1 ? (int) floor(log10($abs)) + 1 : 1;
        $decimals = max(0, self::PRECISION - $integerDigits);
        // Angka di bawah 1 butuh tempat untuk nol di depan (0.000123...).
        if ($abs < 1) {
            $decimals += (int) -floor(log10($abs)) - 1;
        }

        return $this->trimZeros(sprintf('%.'.$decimals.'f', $value));
    }

    private function trimZeros(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }
}
