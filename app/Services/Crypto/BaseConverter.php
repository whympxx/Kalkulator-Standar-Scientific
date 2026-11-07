<?php

namespace App\Services\Crypto;

use App\Services\Calculator\CalculationException;

/**
 * Konversi bilangan bulat antar basis 2–36 (angka besar didukung) dengan langkah pengerjaan.
 */
class BaseConverter
{
    private const DIGITS = '0123456789abcdefghijklmnopqrstuvwxyz';

    /** Batas jumlah langkah yang ditampilkan. */
    private const MAX_STEPS = 40;

    public function convert(string $value, int $fromBase, int $toBase): CryptoResult
    {
        foreach (['asal' => $fromBase, 'tujuan' => $toBase] as $label => $base) {
            if ($base < 2 || $base > 36) {
                throw new CalculationException("Basis {$label} harus antara 2 dan 36.");
            }
        }

        [$negative, $digits] = $this->clean($value, $fromBase);
        $decimal = $this->toDecimal($digits, $fromBase);
        $signed = ($negative && $decimal !== '0' ? '-' : '');

        $steps = [];
        if ($fromBase !== 10) {
            $steps[] = "Ubah ke desimal ({$digits} basis {$fromBase}):";
            $steps = [...$steps, ...$this->positionalSteps($digits, $fromBase, $decimal)];
        }
        if ($toBase !== 10) {
            $steps[] = "Ubah {$decimal} ke basis {$toBase} dengan pembagian berulang:";
            $steps = [...$steps, ...$this->divisionSteps($decimal, $toBase)];
        }

        $result = $signed.$this->fromDecimal($decimal, $toBase);

        return new CryptoResult(
            "{$value} (basis {$fromBase}) → basis {$toBase}",
            $result,
            [
                CryptoResult::detail('Biner (2)', $signed.$this->fromDecimal($decimal, 2)),
                CryptoResult::detail('Oktal (8)', $signed.$this->fromDecimal($decimal, 8)),
                CryptoResult::detail('Desimal (10)', $signed.$decimal),
                CryptoResult::detail('Heksadesimal (16)', $signed.strtoupper($this->fromDecimal($decimal, 16))),
                CryptoResult::detail('Base36', $signed.$this->fromDecimal($decimal, 36)),
                CryptoResult::detail('Jumlah bit', (string) strlen($this->fromDecimal($decimal, 2))),
            ],
            $steps ?: ['Basis asal dan tujuan sama-sama 10.'],
        );
    }

    /** @return array{0: bool, 1: string} [negatif?, digit tanpa prefiks] */
    private function clean(string $value, int $base): array
    {
        $value = strtolower(str_replace(['_', ' '], '', trim($value)));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        if ($value === '') {
            throw new CalculationException('Bilangan wajib diisi.');
        }

        // Prefiks umum: 0x (16), 0b (2), 0o (8).
        $prefixes = ['0x' => 16, '0b' => 2, '0o' => 8];
        foreach ($prefixes as $prefix => $prefixBase) {
            if ($base === $prefixBase && str_starts_with($value, $prefix)) {
                $value = substr($value, 2);
            }
        }

        $value = ltrim($value, '0') ?: '0';
        $allowed = substr(self::DIGITS, 0, $base);

        if ($value === '' || strspn($value, $allowed) !== strlen($value)) {
            throw new CalculationException("\"{$value}\" bukan bilangan basis {$base} yang valid (digit yang boleh: ".strtoupper($allowed).').');
        }

        return [$negative, $value];
    }

    private function toDecimal(string $digits, int $base): string
    {
        $result = '0';
        foreach (str_split($digits) as $digit) {
            $result = BigMath::add(BigMath::mul($result, (string) $base), (string) strpos(self::DIGITS, $digit));
        }

        return $result;
    }

    private function fromDecimal(string $decimal, int $base): string
    {
        if ($decimal === '0') {
            return '0';
        }

        $out = '';
        while ($decimal !== '0') {
            $out = self::DIGITS[(int) bcmod($decimal, (string) $base, 0)].$out;
            $decimal = bcdiv($decimal, (string) $base, 0);
        }

        return $out;
    }

    /** @return list<string> */
    private function positionalSteps(string $digits, int $base, string $decimal): array
    {
        $len = strlen($digits);
        if ($len > 16) {
            return ["{$digits}₍{$base}₎ = {$decimal} (rincian dilewati karena terlalu panjang)"];
        }

        $terms = [];
        foreach (str_split($digits) as $i => $digit) {
            $terms[] = strpos(self::DIGITS, $digit).'×'.$base.'^'.($len - 1 - $i);
        }

        return [implode(' + ', $terms)." = {$decimal}"];
    }

    /** @return list<string> */
    private function divisionSteps(string $decimal, int $base): array
    {
        $steps = [];
        $n = $decimal;
        while ($n !== '0' && count($steps) < self::MAX_STEPS) {
            $q = bcdiv($n, (string) $base, 0);
            $r = (int) bcmod($n, (string) $base, 0);
            $steps[] = "{$n} ÷ {$base} = {$q} sisa {$r}".($r > 9 ? ' ('.strtoupper(self::DIGITS[$r]).')' : '');
            $n = $q;
        }
        if ($n !== '0') {
            $steps[] = '… (langkah berikutnya dilewati)';
        }
        $steps[] = 'Baca sisa dari bawah ke atas.';

        return $steps;
    }
}
