<?php

namespace Tests\Unit;

use App\Services\Calculator\CalculationException;
use App\Services\Calculator\ExpressionEvaluator;
use App\Services\Calculator\ResultFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpressionEvaluatorTest extends TestCase
{
    private function calc(string $expression, string $angle = 'deg'): string
    {
        return (new ResultFormatter)->format((new ExpressionEvaluator)->evaluate($expression, $angle));
    }

    /** @return array<string, array{string, string}> */
    public static function validExpressions(): array
    {
        return [
            'penjumlahan' => ['2+3', '5'],
            'prioritas operator' => ['2+3*4', '14'],
            'kurung' => ['(2+3)*4', '20'],
            'pembagian desimal' => ['10/4', '2.5'],
            'galat float dibuang' => ['0.1+0.2', '0.3'],
            'minus unary' => ['-5+3', '-2'],
            'minus setelah kali' => ['2*-3', '-6'],
            'pangkat asosiatif kanan' => ['2^3^2', '512'],
            'minus dan pangkat' => ['-2^2', '-4'],
            'pangkat negatif' => ['2^-1', '0.5'],
            'simbol tampilan' => ['6×7−2÷2', '41'],
            'persen tunggal' => ['50%', '0.5'],
            'persen tambah' => ['200+10%', '220'],
            'persen kurang' => ['200-10%', '180'],
            'persen kali' => ['200*10%', '20'],
            'faktorial' => ['5!', '120'],
            'faktorial nol' => ['0!', '1'],
            'sin derajat' => ['sin(30)', '0.5'],
            'sin 180 tepat nol' => ['sin(180)', '0'],
            'cos derajat' => ['cos(60)', '0.5'],
            'tan derajat' => ['tan(45)', '1'],
            'asin derajat' => ['asin(1)', '90'],
            'sin radian' => ['sin(pi/2)', '1'],
            'log' => ['log(1000)', '3'],
            'ln' => ['ln(e)', '1'],
            'akar' => ['sqrt(16)', '4'],
            'simbol akar' => ['√(2)^2', '2'],
            'exp' => ['exp(0)', '1'],
            'abs' => ['abs(-4)', '4'],
            'pi' => ['π', '3.14159265359'],
            'perkalian implisit' => ['2pi', '6.28318530718'],
            'perkalian implisit kurung' => ['3(4+1)(2)', '30'],
            'kurung otomatis ditutup' => ['sqrt(9', '3'],
            'notasi ilmiah input' => ['1e+3*2', '2000'],
            'angka besar' => ['10^21', '1e+21'],
            'angka kecil' => ['10^-12', '1e-12'],
            'spasi diabaikan' => [' 1 + 1 ', '2'],
        ];
    }

    #[DataProvider('validExpressions')]
    public function test_menghitung_ekspresi_valid(string $expression, string $expected): void
    {
        $angle = str_contains($expression, 'pi/2') ? 'rad' : 'deg';
        $this->assertSame($expected, $this->calc($expression, $angle));
    }

    /** @return array<string, array{string, string}> */
    public static function invalidExpressions(): array
    {
        return [
            'bagi nol' => ['5/0', 'dibagi dengan nol'],
            'bagi nol hasil ekspresi' => ['1/(2-2)', 'dibagi dengan nol'],
            'nol pangkat negatif' => ['0^-1', 'dibagi dengan nol'],
            'akar negatif' => ['sqrt(-1)', 'negatif'],
            'log nol' => ['log(0)', 'Logaritma'],
            'ln negatif' => ['ln(-2)', 'Logaritma'],
            'tan 90' => ['tan(90)', 'tan tidak terdefinisi'],
            'asin di luar domain' => ['asin(2)', 'antara -1 dan 1'],
            'faktorial desimal' => ['2.5!', 'bilangan bulat'],
            'faktorial negatif' => ['(-3)!', 'bilangan bulat'],
            'faktorial terlalu besar' => ['171!', 'maksimal'],
            'pangkat akar negatif' => ['(-8)^(1/3)', 'tidak terdefinisi'],
            'hasil tak hingga' => ['10^400', 'terlalu besar'],
            'operator menggantung' => ['2+', 'belum lengkap'],
            'kurung tutup berlebih' => ['2)', 'Kurung tutup'],
            'kurung kosong' => ['()', 'tidak valid'],
            'fungsi tanpa kurung' => ['sin 30', 'harus diikuti kurung'],
            'fungsi tidak dikenal' => ['foo(2)', 'tidak dikenal'],
            'karakter aneh' => ['2&3', 'tidak dikenal'],
            'kode php' => ['system("ls")', 'tidak dikenal'],
            'kosong' => ['   ', 'kosong'],
            'titik saja' => ['.', 'tidak dikenal'],
        ];
    }

    #[DataProvider('invalidExpressions')]
    public function test_menolak_ekspresi_tidak_valid(string $expression, string $messagePart): void
    {
        try {
            $this->calc($expression);
            $this->fail("Ekspresi \"{$expression}\" seharusnya gagal.");
        } catch (CalculationException $e) {
            $this->assertStringContainsStringIgnoringCase($messagePart, $e->getMessage());
        }
    }

    public function test_menolak_ekspresi_terlalu_panjang(): void
    {
        $this->expectException(CalculationException::class);
        $this->calc(str_repeat('1+', 101).'1');
    }

    public function test_formatter_tidak_menampilkan_minus_nol(): void
    {
        $this->assertSame('0', (new ResultFormatter)->format(-0.0));
    }
}
