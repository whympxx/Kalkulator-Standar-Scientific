<?php

namespace App\Services\Crypto;

use App\Services\Calculator\CalculationException;

/**
 * Operasi bilangan bulat besar berbasis bcmath. Semua angka berupa string desimal.
 */
class BigMath
{
    /** Basis Miller-Rabin; deterministik untuk n < 3,3 × 10^24. */
    private const WITNESSES = ['2', '3', '5', '7', '11', '13', '17', '19', '23', '29', '31', '37', '41'];

    /** Rapikan & validasi bilangan bulat: "+007" -> "7", "-0" -> "0". */
    public static function int(string $value, string $label = 'Angka'): string
    {
        $value = trim($value);
        if (! preg_match('/^([+-]?)0*(\d+)$/', $value, $m)) {
            throw new CalculationException("{$label} harus bilangan bulat.");
        }
        $digits = $m[2];

        return ($m[1] === '-' && $digits !== '0') ? '-'.$digits : $digits;
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp($a, $b, 0);
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isNegative(string $a): bool
    {
        return self::cmp($a, '0') < 0;
    }

    public static function abs(string $a): string
    {
        return ltrim($a, '-');
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, 0);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, 0);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul($a, $b, 0);
    }

    /** Pembagian bulat dengan floor (cocok untuk aritmetika modular). */
    public static function div(string $a, string $b): string
    {
        $q = bcdiv($a, $b, 0); // dipotong ke arah nol
        if (! self::isZero(bcmod($a, $b, 0)) && (self::isNegative($a) !== self::isNegative($b))) {
            $q = self::sub($q, '1');
        }

        return $q;
    }

    /** Sisa bagi yang selalu non-negatif untuk modulus positif. */
    public static function mod(string $a, string $n): string
    {
        $r = bcmod($a, $n, 0);

        return self::isNegative($r) ? self::add($r, self::abs($n)) : $r;
    }

    public static function powMod(string $base, string $exp, string $n): string
    {
        if (self::cmp($n, '1') === 0) {
            return '0';
        }

        return bcpowmod(self::mod($base, $n), $exp, $n, 0);
    }

    public static function gcd(string $a, string $b): string
    {
        $a = self::abs($a);
        $b = self::abs($b);
        while (! self::isZero($b)) {
            [$a, $b] = [$b, bcmod($a, $b, 0)];
        }

        return $a;
    }

    /**
     * Algoritma Euclid diperluas: a·x + b·y = gcd(a, b).
     *
     * @return array{gcd: string, x: string, y: string, rows: list<array{a: string, b: string, q: string, r: string}>}
     */
    public static function egcd(string $a, string $b): array
    {
        [$oldR, $r] = [$a, $b];
        [$oldS, $s] = ['1', '0'];
        [$oldT, $t] = ['0', '1'];
        $rows = [];

        while (! self::isZero($r)) {
            $q = self::div($oldR, $r);
            $rem = self::sub($oldR, self::mul($q, $r));
            $rows[] = ['a' => $oldR, 'b' => $r, 'q' => $q, 'r' => $rem];
            [$oldR, $r] = [$r, $rem];
            [$oldS, $s] = [$s, self::sub($oldS, self::mul($q, $s))];
            [$oldT, $t] = [$t, self::sub($oldT, self::mul($q, $t))];
        }

        if (self::isNegative($oldR)) {
            [$oldR, $oldS, $oldT] = [self::abs($oldR), self::mul($oldS, '-1'), self::mul($oldT, '-1')];
        }

        return ['gcd' => $oldR, 'x' => $oldS, 'y' => $oldT, 'rows' => $rows];
    }

    /** Invers modular a⁻¹ mod n, atau null bila tidak ada. */
    public static function inverse(string $a, string $n): ?string
    {
        $result = self::egcd(self::mod($a, $n), $n);

        return $result['gcd'] === '1' ? self::mod($result['x'], $n) : null;
    }

    public static function isPrime(string $n): bool
    {
        if (self::cmp($n, '2') < 0) {
            return false;
        }

        foreach (self::WITNESSES as $p) {
            if (self::cmp($n, $p) === 0) {
                return true;
            }
            if (self::isZero(bcmod($n, $p, 0))) {
                return false;
            }
        }

        // n - 1 = d · 2^s
        $nMinus1 = self::sub($n, '1');
        $d = $nMinus1;
        $s = 0;
        while (self::isZero(bcmod($d, '2', 0))) {
            $d = bcdiv($d, '2', 0);
            $s++;
        }

        foreach (self::WITNESSES as $a) {
            $x = bcpowmod($a, $d, $n, 0);
            if ($x === '1' || $x === $nMinus1) {
                continue;
            }
            for ($i = 1; $i < $s; $i++) {
                $x = bcpowmod($x, '2', $n, 0);
                if ($x === $nMinus1) {
                    continue 2;
                }
            }

            return false;
        }

        return true;
    }

    /** Representasi biner dari bilangan non-negatif. */
    public static function toBinary(string $n): string
    {
        if (self::isZero($n)) {
            return '0';
        }
        $bits = '';
        while (! self::isZero($n)) {
            $bits = bcmod($n, '2', 0).$bits;
            $n = bcdiv($n, '2', 0);
        }

        return $bits;
    }
}
