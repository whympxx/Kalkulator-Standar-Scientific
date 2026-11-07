<?php

namespace Tests\Feature;

use Tests\TestCase;

class CryptoEndpointTest extends TestCase
{
    public function test_halaman_memuat_tab_kriptografi(): void
    {
        $this->get('/')->assertOk()->assertSee('Kriptografi')->assertSee('Vigenère');
    }

    public function test_modular_dan_masuk_riwayat(): void
    {
        $this->postJson('/crypto/modular', ['operation' => 'powmod', 'a' => '4', 'b' => '13', 'n' => '497'])
            ->assertOk()
            ->assertJsonPath('result', '445')
            ->assertJsonPath('history.0.mode', 'crypto')
            ->assertJsonPath('history.0.expression', '4^13 mod 497')
            ->assertJsonStructure(['summary', 'result', 'details', 'steps', 'history']);
    }

    public function test_rsa_enkripsi(): void
    {
        $this->postJson('/crypto/rsa', ['action' => 'encrypt', 'p' => '61', 'q' => '53', 'e' => '17', 'message' => '65'])
            ->assertOk()
            ->assertJsonPath('result', '2790');
    }

    public function test_cipher_mempertahankan_spasi(): void
    {
        $this->postJson('/crypto/cipher', ['cipher' => 'caesar', 'action' => 'encrypt', 'text' => '  abc  ', 'shift' => '1'])
            ->assertOk()
            ->assertJsonPath('result', '  bcd  ');
    }

    public function test_hash_teks_kosong_dan_spasi(): void
    {
        $this->postJson('/crypto/hash', ['action' => 'hash', 'text' => ''])
            ->assertOk()
            ->assertJsonPath('result', hash('sha256', ''));

        $this->postJson('/crypto/hash', ['action' => 'hash', 'text' => ' abc'])
            ->assertOk()
            ->assertJsonPath('result', hash('sha256', ' abc'));
    }

    public function test_error_kripto_mengembalikan_422_dan_tidak_masuk_riwayat(): void
    {
        $this->postJson('/crypto/modular', ['operation' => 'inverse', 'a' => '6', 'n' => '9'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'tidak punya invers'));

        $this->getJson('/history')->assertJsonCount(0, 'history');
    }

    public function test_validasi_input_kripto(): void
    {
        $this->postJson('/crypto/modular', ['operation' => 'hack'])->assertStatus(422);
        $this->postJson('/crypto/rsa', ['action' => 'keys', 'q' => '53'])->assertStatus(422);
        $this->postJson('/crypto/cipher', ['cipher' => 'enigma', 'action' => 'encrypt', 'text' => 'x'])->assertStatus(422);
        $this->postJson('/crypto/modular', ['operation' => 'mod', 'a' => str_repeat('9', 401), 'n' => '7'])->assertStatus(422);
    }
}
