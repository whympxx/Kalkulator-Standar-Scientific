<?php

namespace App\Services\Crypto;

use App\Services\Calculator\CalculationException;

/**
 * Aritmetika modular untuk kriptografi, lengkap dengan langkah pengerjaan.
 */
class ModularCalculator
{
    public const OPERATIONS = ['mod', 'powmod', 'inverse', 'gcd', 'prime', 'totient'];

    /** Batas faktorisasi (trial division) agar tetap cepat. */
    public const MAX_FACTORIZE = '1000000000000';

    /** Batas jumlah bit eksponen yang langkah square-and-multiply-nya ditampilkan. */
    private const MAX_STEP_BITS = 32;

    public function calculate(string $operation, ?string $a, ?string $b, ?string $n): CryptoResult
    {
        return match ($operation) {
            'mod' => $this->mod($this->need($a, 'a'), $this->modulus($n)),
            'powmod' => $this->powMod($this->need($a, 'a'), $this->need($b, 'b (eksponen)'), $this->modulus($n)),
            'inverse' => $this->inverse($this->need($a, 'a'), $this->modulus($n)),
            'gcd' => $this->gcd($this->need($a, 'a'), $this->need($b, 'b')),
            'prime' => $this->prime($this->need($a, 'a')),
            'totient' => $this->totient($this->need($a, 'n')),
            default => throw new CalculationException('Operasi modular tidak dikenal.'),
        };
    }

    private function mod(string $a, string $n): CryptoResult
    {
        $q = BigMath::div($a, $n);
        $r = BigMath::mod($a, $n);

        return new CryptoResult("{$a} mod {$n}", $r, [
            CryptoResult::detail('Hasil bagi (q)', $q),
        ], [
            "{$a} = {$q} × {$n} + {$r}",
            "Jadi {$a} mod {$n} = {$r}",
        ]);
    }

    private function powMod(string $a, string $b, string $n): CryptoResult
    {
        if (BigMath::isNegative($b)) {
            $inv = BigMath::inverse($a, $n)
                ?? throw new CalculationException("Eksponen negatif butuh invers, tetapi gcd({$a}, {$n}) ≠ 1.");
            $positive = BigMath::abs($b);
            $result = BigMath::powMod($inv, $positive, $n);

            return new CryptoResult("{$a}^{$b} mod {$n}", $result, [], [
                "{$a}^{$b} = ({$a}⁻¹)^{$positive}, dengan {$a}⁻¹ mod {$n} = {$inv}",
                "{$inv}^{$positive} mod {$n} = {$result}",
            ]);
        }

        $result = BigMath::powMod($a, $b, $n);
        $bits = BigMath::toBinary($b);
        $steps = ["Eksponen {$b} dalam biner = {$bits}"];

        if (strlen($bits) <= self::MAX_STEP_BITS) {
            // Square-and-multiply dari bit paling kiri.
            $base = BigMath::mod($a, $n);
            $acc = '1';
            foreach (str_split($bits) as $i => $bit) {
                $squared = BigMath::mod(BigMath::mul($acc, $acc), $n);
                $line = 'Bit '.($i + 1)." ({$bit}): kuadratkan {$acc}² mod {$n} = {$squared}";
                $acc = $squared;
                if ($bit === '1') {
                    $acc = BigMath::mod(BigMath::mul($acc, $base), $n);
                    $line .= ", kalikan × {$base} mod {$n} = {$acc}";
                }
                $steps[] = $line;
            }
        } else {
            $steps[] = 'Langkah square-and-multiply tidak ditampilkan karena eksponen terlalu besar.';
        }
        $steps[] = "Jadi {$a}^{$b} mod {$n} = {$result}";

        return new CryptoResult("{$a}^{$b} mod {$n}", $result, [
            CryptoResult::detail('Jumlah bit eksponen', (string) strlen($bits)),
        ], $steps);
    }

    private function inverse(string $a, string $n): CryptoResult
    {
        $egcd = BigMath::egcd(BigMath::mod($a, $n), $n);
        $steps = $this->euclidSteps($egcd['rows']);

        if ($egcd['gcd'] !== '1') {
            throw new CalculationException("{$a} tidak punya invers modulo {$n} karena gcd({$a}, {$n}) = {$egcd['gcd']} (harus 1).");
        }

        $inverse = BigMath::mod($egcd['x'], $n);
        $steps[] = "Dari Euclid diperluas: ".BigMath::mod($a, $n)." × ({$egcd['x']}) + {$n} × ({$egcd['y']}) = 1";
        $steps[] = "Jadi {$a}⁻¹ mod {$n} = {$egcd['x']} mod {$n} = {$inverse}";
        $steps[] = "Cek: {$a} × {$inverse} mod {$n} = ".BigMath::mod(BigMath::mul($a, $inverse), $n);

        return new CryptoResult("{$a}⁻¹ mod {$n}", $inverse, [], $steps);
    }

    private function gcd(string $a, string $b): CryptoResult
    {
        if (BigMath::isZero($a) && BigMath::isZero($b)) {
            throw new CalculationException('gcd(0, 0) tidak terdefinisi.');
        }

        $egcd = BigMath::egcd(BigMath::abs($a), BigMath::abs($b));
        $x = BigMath::isNegative($a) ? BigMath::mul($egcd['x'], '-1') : $egcd['x'];
        $y = BigMath::isNegative($b) ? BigMath::mul($egcd['y'], '-1') : $egcd['y'];
        $lcm = BigMath::isZero($egcd['gcd']) ? '0'
            : BigMath::div(BigMath::abs(BigMath::mul($a, $b)), $egcd['gcd']);

        $steps = $this->euclidSteps($egcd['rows']);
        $steps[] = "gcd({$a}, {$b}) = {$egcd['gcd']}";
        $steps[] = "Identitas Bézout: {$a} × ({$x}) + {$b} × ({$y}) = {$egcd['gcd']}";

        return new CryptoResult("gcd({$a}, {$b})", $egcd['gcd'], [
            CryptoResult::detail('KPK / lcm', $lcm),
            CryptoResult::detail('x (Bézout)', $x),
            CryptoResult::detail('y (Bézout)', $y),
            CryptoResult::detail('Relatif prima?', $egcd['gcd'] === '1' ? 'Ya' : 'Tidak'),
        ], $steps);
    }

    private function prime(string $n): CryptoResult
    {
        $isPrime = BigMath::isPrime($n);
        $details = [];
        $steps = ['Diuji dengan Miller-Rabin (13 basis prima pertama).'];

        if (! $isPrime && BigMath::cmp($n, '1') > 0 && BigMath::cmp($n, self::MAX_FACTORIZE) <= 0) {
            $factors = $this->factorize((int) $n);
            $details[] = CryptoResult::detail('Faktorisasi', $this->formatFactors($factors));
        }

        if (BigMath::cmp($n, '3317044064679887385961981') >= 0) {
            $steps[] = 'Untuk angka sebesar ini hasilnya "kemungkinan besar prima" (probabilistik).';
        }

        return new CryptoResult("prima({$n})?", $isPrime ? 'Prima' : 'Bukan prima', $details, $steps);
    }

    private function totient(string $n): CryptoResult
    {
        if (BigMath::cmp($n, '1') < 0) {
            throw new CalculationException('φ(n) hanya untuk n ≥ 1.');
        }
        if (BigMath::cmp($n, self::MAX_FACTORIZE) > 0) {
            throw new CalculationException('φ(n) hanya untuk n ≤ 10^12 (butuh faktorisasi).');
        }

        $factors = $this->factorize((int) $n);
        $phi = (int) $n;
        $parts = [];
        foreach (array_keys($factors) as $p) {
            $phi = intdiv($phi, $p) * ($p - 1);
            $parts[] = "(1 − 1/{$p})";
        }

        $steps = $factors === []
            ? ['φ(1) = 1']
            : [
                "{$n} = ".$this->formatFactors($factors),
                "φ({$n}) = {$n} × ".implode(' × ', $parts)." = {$phi}",
            ];

        return new CryptoResult("φ({$n})", (string) $phi, [
            CryptoResult::detail('Faktorisasi', $factors === [] ? '1' : $this->formatFactors($factors)),
        ], $steps);
    }

    /**
     * @param  list<array{a: string, b: string, q: string, r: string}>  $rows
     * @return list<string>
     */
    private function euclidSteps(array $rows): array
    {
        $steps = ['Algoritma Euclid:'];
        foreach (array_slice($rows, 0, 50) as $row) {
            $steps[] = "{$row['a']} = {$row['q']} × {$row['b']} + {$row['r']}";
        }
        if (count($rows) > 50) {
            $steps[] = '… ('.(count($rows) - 50).' langkah lagi)';
        }

        return $steps;
    }

    /** @return array<int, int> faktor prima => pangkat */
    private function factorize(int $n): array
    {
        $factors = [];
        for ($p = 2; $p * $p <= $n; $p += ($p === 2 ? 1 : 2)) {
            while ($n % $p === 0) {
                $factors[$p] = ($factors[$p] ?? 0) + 1;
                $n = intdiv($n, $p);
            }
        }
        if ($n > 1) {
            $factors[$n] = ($factors[$n] ?? 0) + 1;
        }

        return $factors;
    }

    /** @param  array<int, int>  $factors */
    private function formatFactors(array $factors): string
    {
        return implode(' × ', array_map(
            fn ($p, $k) => $k > 1 ? "{$p}^{$k}" : (string) $p,
            array_keys($factors),
            $factors,
        ));
    }

    private function need(?string $value, string $label): string
    {
        if ($value === null || trim($value) === '') {
            throw new CalculationException("Nilai {$label} wajib diisi.");
        }

        return BigMath::int($value, "Nilai {$label}");
    }

    private function modulus(?string $n): string
    {
        $n = $this->need($n, 'n (modulus)');
        if (BigMath::cmp($n, '1') < 0) {
            throw new CalculationException('Modulus n harus bilangan bulat positif.');
        }

        return $n;
    }
}
