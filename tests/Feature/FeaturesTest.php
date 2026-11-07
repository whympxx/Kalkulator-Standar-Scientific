<?php

namespace Tests\Feature;

use App\Services\Calculator\CalculationException;
use App\Services\Calculator\ExpressionEvaluator;
use App\Services\Crypto\BaseConverter;
use App\Services\Crypto\XorCipher;
use Tests\TestCase;

/**
 * Fitur tambahan: grafik, pratinjau, ekspor CSV, konversi basis, XOR.
 */
class FeaturesTest extends TestCase
{
    // ---------- Grafik ----------

    public function test_evaluasi_seri_dengan_variabel_x(): void
    {
        $ys = (new ExpressionEvaluator)->evaluateSeries('x^2 + 2x + 1', [-1.0, 0.0, 2.0]);
        $this->assertSame([0.0, 1.0, 9.0], $ys);
    }

    public function test_titik_tidak_terdefinisi_menjadi_null(): void
    {
        $ys = (new ExpressionEvaluator)->evaluateSeries('1/x', [-1.0, 0.0, 2.0]);
        $this->assertSame([-1.0, null, 0.5], $ys);
    }

    public function test_variabel_x_ditolak_di_kalkulator_biasa(): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('mode Grafik');
        (new ExpressionEvaluator)->evaluate('2x');
    }

    public function test_endpoint_grafik(): void
    {
        $response = $this->postJson('/plot', [
            'functions' => ['sin(x)', '', 'foo(x)'],
            'from' => -3.14,
            'to' => 3.14,
            'samples' => 101,
        ])->assertOk();

        $this->assertCount(101, $response->json('x'));
        $this->assertCount(2, $response->json('series'));
        $this->assertCount(101, $response->json('series.0.y'));
        $this->assertEqualsWithDelta(0.0, $response->json('series.0.y.50'), 1e-9);
        $this->assertStringContainsString('tidak dikenal', $response->json('series.1.error'));
    }

    public function test_validasi_grafik(): void
    {
        $this->postJson('/plot', ['functions' => ['x'], 'from' => 5, 'to' => 1])->assertStatus(422);
        $this->postJson('/plot', ['functions' => ['x', 'x', 'x', 'x'], 'from' => 0, 'to' => 1])->assertStatus(422);
        $this->postJson('/plot', ['functions' => [''], 'from' => 0, 'to' => 1])->assertStatus(422);
    }

    // ---------- Pratinjau ----------

    public function test_pratinjau_tidak_menyimpan_riwayat(): void
    {
        $this->postJson('/preview', ['expression' => '2+3*4'])->assertOk()->assertJsonPath('result', '14');
        $this->postJson('/preview', ['expression' => '2+'])->assertOk()->assertJsonPath('result', null);

        $this->getJson('/history')->assertJsonCount(0, 'history');
    }

    // ---------- Ekspor CSV ----------

    public function test_ekspor_csv_aman_dari_injeksi_rumus(): void
    {
        $this->withSession(['calculator.history' => [
            ['expression' => '=HYPERLINK("http://x")', 'result' => '-5', 'mode' => 'crypto', 'time' => '2026-11-07T10:00:00+00:00'],
            ['expression' => '2+3', 'result' => '5', 'mode' => 'standard', 'time' => '2026-11-07T09:00:00+00:00'],
        ]]);

        $response = $this->get('/history/export')->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Waktu,Mode,Ekspresi,Hasil', $csv);
        $this->assertStringContainsString("Standar,2+3,5", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString(',-5', $csv); // angka negatif tetap angka
        // urutan kronologis: perhitungan lama lebih dulu
        $this->assertLessThan(strpos($csv, 'HYPERLINK'), strpos($csv, '2+3'));
    }

    // ---------- Konversi basis ----------

    public function test_konversi_basis(): void
    {
        $c = new BaseConverter;
        $this->assertSame('ff', $c->convert('255', 10, 16)->result);
        $this->assertSame('255', $c->convert('0xFF', 16, 10)->result);
        $this->assertSame('-1010', $c->convert('-10', 10, 2)->result);
        $this->assertSame('18446744073709551616', $c->convert('1'.str_repeat('0', 64), 2, 10)->result);

        $result = $c->convert('13', 10, 2);
        $this->assertContains('13 ÷ 2 = 6 sisa 1', $result->steps);
        $this->assertSame('D', substr($result->details[3]['value'], -1));
    }

    public function test_konversi_basis_digit_tidak_valid(): void
    {
        $this->expectException(CalculationException::class);
        (new BaseConverter)->convert('12', 2, 10);
    }

    // ---------- XOR ----------

    public function test_xor_bolak_balik(): void
    {
        $x = new XorCipher;
        $hex = $x->calculate('encrypt', 'Halo 🔐', 'K3y')->result;

        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $hex);
        $this->assertSame('Halo 🔐', $x->calculate('decrypt', $hex, 'K3y')->result);
        $this->assertSame('030a', $x->calculate('encrypt', 'Hi', 'Kc')->result);
    }

    public function test_endpoint_kripto_baru(): void
    {
        $this->postJson('/crypto/base', ['value' => '255', 'from_base' => 10, 'to_base' => 2])
            ->assertOk()->assertJsonPath('result', '11111111');
        $this->postJson('/crypto/xor', ['action' => 'decrypt', 'text' => 'zz', 'key' => 'k'])
            ->assertStatus(422);
        $this->postJson('/crypto/base', ['value' => '1', 'from_base' => 1, 'to_base' => 2])
            ->assertStatus(422);
    }
}
