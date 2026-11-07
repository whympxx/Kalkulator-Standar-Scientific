<?php

namespace App\Services\Crypto;

use App\Services\Calculator\CalculationException;

/**
 * RSA untuk pembelajaran: pembuatan kunci dari p, q, e serta enkripsi/dekripsi.
 */
class RsaCalculator
{
    public const ACTIONS = ['keys', 'encrypt', 'decrypt'];

    public const MAX_TEXT = 300;

    public function calculate(string $action, string $p, string $q, ?string $e, ?string $message, string $format = 'number'): CryptoResult
    {
        $key = $this->buildKey($p, $q, $e);

        if ($action === 'keys') {
            return new CryptoResult(
                "RSA kunci (p={$key['p']}, q={$key['q']})",
                "Publik (e={$key['e']}, n={$key['n']}) · Privat (d={$key['d']}, n={$key['n']})",
                $this->keyDetails($key),
                $key['steps'],
            );
        }

        if ($message === null || trim($message) === '') {
            throw new CalculationException('Pesan wajib diisi.');
        }

        return match ([$action, $format]) {
            ['encrypt', 'number'] => $this->encryptNumber($key, $message),
            ['decrypt', 'number'] => $this->decryptNumber($key, $message),
            ['encrypt', 'text'] => $this->encryptText($key, $message),
            ['decrypt', 'text'] => $this->decryptText($key, $message),
            default => throw new CalculationException('Aksi RSA tidak dikenal.'),
        };
    }

    /**
     * @return array{p: string, q: string, n: string, phi: string, e: string, d: string, steps: list<string>}
     */
    private function buildKey(string $p, string $q, ?string $e): array
    {
        $p = BigMath::int($p, 'p');
        $q = BigMath::int($q, 'q');

        foreach (['p' => $p, 'q' => $q] as $name => $value) {
            if (! BigMath::isPrime($value)) {
                throw new CalculationException("{$name} = {$value} bukan bilangan prima.");
            }
        }
        if ($p === $q) {
            throw new CalculationException('p dan q harus dua bilangan prima yang berbeda.');
        }

        $n = BigMath::mul($p, $q);
        $phi = BigMath::mul(BigMath::sub($p, '1'), BigMath::sub($q, '1'));
        $steps = [
            "n = p × q = {$p} × {$q} = {$n}",
            "φ(n) = (p − 1)(q − 1) = ".BigMath::sub($p, '1').' × '.BigMath::sub($q, '1')." = {$phi}",
        ];

        if ($e === null || trim($e) === '') {
            $e = $this->chooseE($phi);
            $steps[] = "e dipilih otomatis: {$e} (1 < e < φ(n) dan gcd(e, φ(n)) = 1)";
        } else {
            $e = BigMath::int($e, 'e');
            if (BigMath::cmp($e, '1') <= 0 || BigMath::cmp($e, $phi) >= 0) {
                throw new CalculationException("e harus memenuhi 1 < e < φ(n) = {$phi}.");
            }
            $g = BigMath::gcd($e, $phi);
            if ($g !== '1') {
                throw new CalculationException("e = {$e} tidak valid karena gcd(e, φ(n)) = {$g} (harus 1).");
            }
            $steps[] = "gcd(e, φ(n)) = gcd({$e}, {$phi}) = 1 ✓";
        }

        $d = BigMath::inverse($e, $phi);
        $steps[] = "d = e⁻¹ mod φ(n) = {$e}⁻¹ mod {$phi} = {$d}";
        $steps[] = "Cek: e × d mod φ(n) = ".BigMath::mod(BigMath::mul($e, $d), $phi);

        return compact('p', 'q', 'n', 'phi', 'e', 'd', 'steps');
    }

    private function chooseE(string $phi): string
    {
        if (BigMath::cmp('65537', $phi) < 0 && BigMath::gcd('65537', $phi) === '1') {
            return '65537';
        }
        for ($e = '3'; BigMath::cmp($e, $phi) < 0; $e = BigMath::add($e, '2')) {
            if (BigMath::gcd($e, $phi) === '1') {
                return $e;
            }
        }

        throw new CalculationException('Tidak ada nilai e yang valid untuk p dan q ini.');
    }

    /** @return list<array{label: string, value: string}> */
    private function keyDetails(array $key): array
    {
        return [
            CryptoResult::detail('n', $key['n']),
            CryptoResult::detail('φ(n)', $key['phi']),
            CryptoResult::detail('e (publik)', $key['e']),
            CryptoResult::detail('d (privat)', $key['d']),
        ];
    }

    private function encryptNumber(array $key, string $message): CryptoResult
    {
        $m = $this->blockInRange($key, BigMath::int($message, 'Pesan m'), 'm');
        $c = BigMath::powMod($m, $key['e'], $key['n']);

        return new CryptoResult("RSA enkripsi m={$m} (e={$key['e']}, n={$key['n']})", $c, $this->keyDetails($key), [
            ...$key['steps'],
            "c = m^e mod n = {$m}^{$key['e']} mod {$key['n']} = {$c}",
        ]);
    }

    private function decryptNumber(array $key, string $message): CryptoResult
    {
        $c = $this->blockInRange($key, BigMath::int($message, 'Cipherteks c'), 'c');
        $m = BigMath::powMod($c, $key['d'], $key['n']);

        return new CryptoResult("RSA dekripsi c={$c} (d={$key['d']}, n={$key['n']})", $m, $this->keyDetails($key), [
            ...$key['steps'],
            "m = c^d mod n = {$c}^{$key['d']} mod {$key['n']} = {$m}",
        ]);
    }

    private function encryptText(array $key, string $text): CryptoResult
    {
        $this->requireTextCapacity($key);
        if (strlen($text) > self::MAX_TEXT) {
            throw new CalculationException('Teks maksimal '.self::MAX_TEXT.' byte untuk RSA.');
        }

        $steps = [...$key['steps'], 'Setiap byte teks (kode ASCII/UTF-8) dienkripsi terpisah: c = m^e mod n'];
        $blocks = [];
        foreach (array_values(unpack('C*', $text)) as $i => $byte) {
            $c = BigMath::powMod((string) $byte, $key['e'], $key['n']);
            $blocks[] = $c;
            if ($i < 20) {
                $char = $byte >= 32 && $byte < 127 ? '"'.chr($byte).'"' : 'byte';
                $steps[] = "{$char} → {$byte}^{$key['e']} mod {$key['n']} = {$c}";
            }
        }
        if (count($blocks) > 20) {
            $steps[] = '… ('.(count($blocks) - 20).' byte lagi)';
        }

        return new CryptoResult(
            'RSA enkripsi teks "'.mb_strimwidth($text, 0, 30, '…').'"',
            implode(' ', $blocks),
            $this->keyDetails($key),
            $steps,
        );
    }

    private function decryptText(array $key, string $cipher): CryptoResult
    {
        $this->requireTextCapacity($key);
        $parts = preg_split('/[\s,;]+/', trim($cipher), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) > self::MAX_TEXT) {
            throw new CalculationException('Maksimal '.self::MAX_TEXT.' blok cipherteks.');
        }

        $steps = [...$key['steps'], 'Setiap blok didekripsi: m = c^d mod n, lalu diubah ke karakter'];
        $bytes = '';
        foreach ($parts as $i => $part) {
            $c = $this->blockInRange($key, BigMath::int($part, 'Blok cipherteks'), 'c');
            $m = BigMath::powMod($c, $key['d'], $key['n']);
            if (BigMath::cmp($m, '255') > 0) {
                throw new CalculationException("Blok {$c} menghasilkan {$m} (> 255), bukan byte teks. Pastikan kunci sama dengan saat enkripsi.");
            }
            $bytes .= chr((int) $m);
            if ($i < 20) {
                $steps[] = "{$c}^{$key['d']} mod {$key['n']} = {$m}";
            }
        }

        if (! mb_check_encoding($bytes, 'UTF-8')) {
            throw new CalculationException('Hasil dekripsi bukan teks yang valid. Pastikan kunci sama dengan saat enkripsi.');
        }

        return new CryptoResult('RSA dekripsi teks ('.count($parts).' blok)', $bytes, $this->keyDetails($key), $steps);
    }

    private function blockInRange(array $key, string $value, string $name): string
    {
        if (BigMath::isNegative($value) || BigMath::cmp($value, $key['n']) >= 0) {
            throw new CalculationException("{$name} harus memenuhi 0 ≤ {$name} < n = {$key['n']}.");
        }

        return $value;
    }

    private function requireTextCapacity(array $key): void
    {
        if (BigMath::cmp($key['n'], '256') < 0) {
            throw new CalculationException("Mode teks butuh n > 255 (sekarang n = {$key['n']}). Pakai p dan q yang lebih besar.");
        }
    }
}
