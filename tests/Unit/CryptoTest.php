<?php

namespace Tests\Unit;

use App\Services\Calculator\CalculationException;
use App\Services\Crypto\BigMath;
use App\Services\Crypto\ClassicCipher;
use App\Services\Crypto\HashCalculator;
use App\Services\Crypto\ModularCalculator;
use App\Services\Crypto\RsaCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CryptoTest extends TestCase
{
    // ---------- Aritmetika modular ----------

    /** @return array<string, array{string, ?string, ?string, ?string, string}> */
    public static function modularCases(): array
    {
        return [
            'mod positif' => ['mod', '17', null, '5', '2'],
            'mod negatif selalu positif' => ['mod', '-7', null, '3', '2'],
            'pangkat modular' => ['powmod', '4', '13', '497', '445'],
            'pangkat modular besar' => ['powmod', '2', '100', '1000000007', '976371285'],
            'pangkat eksponen negatif' => ['powmod', '3', '-1', '11', '4'],
            'invers modular' => ['inverse', '3', null, '11', '4'],
            'invers RSA' => ['inverse', '17', null, '3120', '2753'],
            'invers dari a negatif' => ['inverse', '-3', null, '11', '7'],
            'gcd' => ['gcd', '240', '46', null, '2'],
            'gcd negatif' => ['gcd', '-12', '18', null, '6'],
            'prima kecil' => ['prime', '97', null, null, 'Prima'],
            'bilangan Carmichael' => ['prime', '561', null, null, 'Bukan prima'],
            'prima Mersenne M61' => ['prime', '2305843009213693951', null, null, 'Prima'],
            'satu bukan prima' => ['prime', '1', null, null, 'Bukan prima'],
            'totient' => ['totient', '36', null, null, '12'],
            'totient prima' => ['totient', '97', null, null, '96'],
            'totient satu' => ['totient', '1', null, null, '1'],
        ];
    }

    #[DataProvider('modularCases')]
    public function test_aritmetika_modular(string $op, ?string $a, ?string $b, ?string $n, string $expected): void
    {
        $this->assertSame($expected, (new ModularCalculator)->calculate($op, $a, $b, $n)->result);
    }

    public function test_langkah_euclid_dan_bezout(): void
    {
        $result = (new ModularCalculator)->calculate('gcd', '240', '46', null);

        $this->assertContains('240 = 5 × 46 + 10', $result->steps);
        $this->assertContains('Identitas Bézout: 240 × (-9) + 46 × (47) = 2', $result->steps);
    }

    public function test_langkah_square_and_multiply(): void
    {
        $result = (new ModularCalculator)->calculate('powmod', '4', '13', '497');

        $this->assertContains('Eksponen 13 dalam biner = 1101', $result->steps);
        $this->assertSame('Jadi 4^13 mod 497 = 445', $result->steps[array_key_last($result->steps)]);
    }

    public function test_faktorisasi_bilangan_komposit(): void
    {
        $result = (new ModularCalculator)->calculate('prime', '360', null, null);

        $this->assertSame('2^3 × 3^2 × 5', $result->details[0]['value']);
    }

    /** @return array<string, array{string, ?string, ?string, ?string, string}> */
    public static function modularErrors(): array
    {
        return [
            'invers tidak ada' => ['inverse', '6', null, '9', 'tidak punya invers'],
            'modulus nol' => ['mod', '5', null, '0', 'positif'],
            'modulus kosong' => ['mod', '5', null, null, 'wajib diisi'],
            'bukan angka' => ['mod', '1.5', null, '3', 'bilangan bulat'],
            'gcd nol nol' => ['gcd', '0', '0', null, 'tidak terdefinisi'],
            'totient terlalu besar' => ['totient', '10000000000000', null, null, '10^12'],
        ];
    }

    #[DataProvider('modularErrors')]
    public function test_error_modular(string $op, ?string $a, ?string $b, ?string $n, string $message): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage($message);
        (new ModularCalculator)->calculate($op, $a, $b, $n);
    }

    public function test_bigmath_floor_division(): void
    {
        $this->assertSame('-3', BigMath::div('-7', '3'));
        $this->assertSame('2', BigMath::div('7', '3'));
        $this->assertSame('1101', BigMath::toBinary('13'));
    }

    // ---------- RSA ----------

    public function test_rsa_kunci_contoh_buku_teks(): void
    {
        $result = (new RsaCalculator)->calculate('keys', '61', '53', '17', null);

        $this->assertSame('Publik (e=17, n=3233) · Privat (d=2753, n=3233)', $result->result);
        $this->assertContains('φ(n) = (p − 1)(q − 1) = 60 × 52 = 3120', $result->steps);
    }

    public function test_rsa_enkripsi_dan_dekripsi_angka(): void
    {
        $rsa = new RsaCalculator;

        $this->assertSame('2790', $rsa->calculate('encrypt', '61', '53', '17', '65')->result);
        $this->assertSame('65', $rsa->calculate('decrypt', '61', '53', '17', '2790')->result);
    }

    public function test_rsa_teks_bolak_balik(): void
    {
        $rsa = new RsaCalculator;
        $cipher = $rsa->calculate('encrypt', '61', '53', '17', 'Halo, Dunia! 🔐', 'text')->result;

        $this->assertMatchesRegularExpression('/^\d+( \d+)+$/', $cipher);
        $this->assertSame('Halo, Dunia! 🔐', $rsa->calculate('decrypt', '61', '53', '17', $cipher, 'text')->result);
    }

    public function test_rsa_memilih_e_otomatis(): void
    {
        $result = (new RsaCalculator)->calculate('keys', '61', '53', null, null);
        $this->assertStringContainsString('e=7,', $result->result); // 65537 > φ, 3 dan 5 tidak koprima dengan 3120

        $big = (new RsaCalculator)->calculate('keys', '1000000007', '998244353', null, null);
        $this->assertStringContainsString('e=65537,', $big->result);
    }

    /** @return array<string, array{string, string, ?string, ?string, string, string}> */
    public static function rsaErrors(): array
    {
        return [
            'p bukan prima' => ['keys', '15', '53', '17', null, 'bukan bilangan prima'],
            'p sama dengan q' => ['keys', '61', '61', '17', null, 'berbeda'],
            'e tidak koprima' => ['keys', '61', '53', '15', null, 'gcd(e, φ(n))'],
            'e terlalu besar' => ['keys', '61', '53', '5000', null, '1 < e'],
            'pesan melebihi n' => ['encrypt', '61', '53', '17', '5000', '0 ≤ m < n'],
            'pesan kosong' => ['encrypt', '61', '53', '17', '', 'wajib diisi'],
            'n terlalu kecil untuk teks' => ['encrypt', '3', '5', '3', 'A', 'n > 255'],
        ];
    }

    #[DataProvider('rsaErrors')]
    public function test_error_rsa(string $action, string $p, string $q, ?string $e, ?string $msg, string $message): void
    {
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage($message);
        (new RsaCalculator)->calculate($action, $p, $q, $e, $msg, $message === 'n > 255' ? 'text' : 'number');
    }

    // ---------- Cipher klasik ----------

    public function test_caesar(): void
    {
        $c = new ClassicCipher;
        $this->assertSame('Khoor, Zruog!', $c->calculate('caesar', 'encrypt', 'Hello, World!', ['shift' => '3'])->result);
        $this->assertSame('Hello, World!', $c->calculate('caesar', 'decrypt', 'Khoor, Zruog!', ['shift' => '3'])->result);
        $this->assertSame('abc', $c->calculate('caesar', 'encrypt', 'xyz', ['shift' => '-23'])->result);
    }

    public function test_caesar_brute_force(): void
    {
        $result = (new ClassicCipher)->calculate('caesar', 'bruteforce', 'Khoor', []);

        $this->assertCount(25, $result->details);
        $this->assertSame(['label' => 'k = 3', 'value' => 'Hello'], $result->details[2]);
    }

    public function test_vigenere(): void
    {
        $c = new ClassicCipher;
        $this->assertSame('LXFOPV EF RNHR', $c->calculate('vigenere', 'encrypt', 'ATTACK AT DAWN', ['key' => 'lemon'])->result);
        $this->assertSame('ATTACK AT DAWN', $c->calculate('vigenere', 'decrypt', 'LXFOPV EF RNHR', ['key' => 'LEMON'])->result);
    }

    public function test_affine(): void
    {
        $c = new ClassicCipher;
        $this->assertSame('IHHWVC SWFRCP', $c->calculate('affine', 'encrypt', 'AFFINE CIPHER', ['a' => '5', 'b' => '8'])->result);
        $this->assertSame('AFFINE CIPHER', $c->calculate('affine', 'decrypt', 'IHHWVC SWFRCP', ['a' => '5', 'b' => '8'])->result);
    }

    public function test_error_cipher(): void
    {
        $c = new ClassicCipher;
        foreach ([
            ['affine', ['a' => '13', 'b' => '1'], 'gcd(a, 26)'],
            ['vigenere', ['key' => '123'], 'minimal satu huruf'],
            ['caesar', ['shift' => 'abc'], 'bilangan bulat'],
        ] as [$cipher, $params, $message]) {
            try {
                $c->calculate($cipher, 'encrypt', 'TEST', $params);
                $this->fail("{$cipher} seharusnya gagal");
            } catch (CalculationException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    // ---------- Hash & encoding ----------

    public function test_hash(): void
    {
        $result = (new HashCalculator)->calculate('hash', 'abc');

        $this->assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $result->result);
        $this->assertSame(['label' => 'MD5', 'value' => '900150983cd24fb0d6963f7d28e17f72'], $result->details[0]);
    }

    public function test_encoding_bolak_balik(): void
    {
        $h = new HashCalculator;
        $this->assertSame('SGFsbyDwn5SQ', $h->calculate('base64_encode', 'Halo 🔐')->result);
        $this->assertSame('Halo 🔐', $h->calculate('base64_decode', 'SGFsbyDwn5SQ')->result);
        $this->assertSame('48656c6c6f', $h->calculate('hex_encode', 'Hello')->result);
        $this->assertSame('Hello', $h->calculate('hex_decode', '48 65 6C 6C 6F')->result);
    }

    public function test_error_decode(): void
    {
        $this->expectException(CalculationException::class);
        (new HashCalculator)->calculate('hex_decode', 'xyz');
    }
}
