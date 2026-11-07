<?php

namespace Tests\Feature;

use App\Services\Calculator\HistoryRepository;
use Tests\TestCase;

class CalculatorTest extends TestCase
{
    public function test_halaman_kalkulator_tampil(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Standar')
            ->assertSee('Scientific')
            ->assertSee('Riwayat');
    }

    public function test_alamat_lama_dialihkan(): void
    {
        $this->get('/kalkulator.php')->assertRedirect('/');
    }

    public function test_menghitung_dan_menyimpan_riwayat(): void
    {
        $this->postJson('/calculate', ['expression' => '2+3*4', 'mode' => 'standard'])
            ->assertOk()
            ->assertJsonPath('result', '14')
            ->assertJsonPath('history.0.expression', '2+3*4')
            ->assertJsonPath('history.0.result', '14')
            ->assertJsonPath('history.0.mode', 'standard');

        $this->getJson('/history')->assertJsonCount(1, 'history');
    }

    public function test_mode_sudut_radian(): void
    {
        $this->postJson('/calculate', ['expression' => 'cos(pi)', 'mode' => 'scientific', 'angle' => 'rad'])
            ->assertOk()
            ->assertJsonPath('result', '-1');
    }

    public function test_error_perhitungan_tidak_masuk_riwayat(): void
    {
        $this->postJson('/calculate', ['expression' => '1/0'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tidak bisa dibagi dengan nol.');

        $this->getJson('/history')->assertJsonCount(0, 'history');
    }

    public function test_validasi_input(): void
    {
        $this->postJson('/calculate', ['expression' => ''])->assertStatus(422);
        $this->postJson('/calculate', ['expression' => '1+1', 'mode' => 'hack'])->assertStatus(422);
        $this->postJson('/calculate', ['expression' => '1+1', 'angle' => 'grad'])->assertStatus(422);
        $this->postJson('/calculate', ['expression' => str_repeat('1', 201)])->assertStatus(422);
    }

    public function test_riwayat_dibatasi(): void
    {
        for ($i = 1; $i <= HistoryRepository::LIMIT + 3; $i++) {
            $this->postJson('/calculate', ['expression' => "{$i}+0"])->assertOk();
        }

        $this->getJson('/history')
            ->assertJsonCount(HistoryRepository::LIMIT, 'history')
            ->assertJsonPath('history.0.result', (string) (HistoryRepository::LIMIT + 3));
    }

    public function test_hapus_riwayat(): void
    {
        $this->postJson('/calculate', ['expression' => '1+1'])->assertOk();

        $this->deleteJson('/history')->assertOk()->assertJsonPath('history', []);
        $this->getJson('/history')->assertJsonCount(0, 'history');
    }

    public function test_riwayat_di_escape_saat_dirender(): void
    {
        $this->withSession(['calculator.history' => [[
            'expression' => '</script><script>alert(1)</script>',
            'result' => '1',
            'mode' => 'standard',
            'time' => now()->toIso8601String(),
        ]]])->get('/')
            ->assertOk()
            ->assertDontSee('</script><script>alert(1)', false);
    }
}
