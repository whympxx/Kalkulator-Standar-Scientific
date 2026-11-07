<?php

namespace App\Services\Calculator;

use RuntimeException;

/**
 * Kesalahan perhitungan yang pesannya aman ditampilkan ke pengguna.
 */
class CalculationException extends RuntimeException
{
    public static function divisionByZero(): self
    {
        return new self('Tidak bisa dibagi dengan nol.');
    }

    public static function undefined(): self
    {
        return new self('Hasil tidak terdefinisi.');
    }

    public static function syntax(): self
    {
        return new self('Format ekspresi tidak valid.');
    }

    public static function incomplete(): self
    {
        return new self('Ekspresi belum lengkap.');
    }
}
