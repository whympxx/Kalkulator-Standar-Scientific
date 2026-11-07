<?php

namespace App\Services\Crypto;

use App\Services\Calculator\CalculationException;

/**
 * XOR cipher per byte dengan kunci berulang. Cipherteks ditampilkan dalam Hex.
 */
class XorCipher
{
    private const STEP_LIMIT = 8;

    public function calculate(string $action, string $text, string $key): CryptoResult
    {
        if ($key === '') {
            throw new CalculationException('Kunci XOR wajib diisi.');
        }

        if ($action === 'encrypt') {
            $bytes = $this->xor($text, $key);

            return new CryptoResult(
                'XOR enkripsi kunci="'.mb_strimwidth($key, 0, 20, '…').'": "'.mb_strimwidth($text, 0, 30, '…').'"',
                bin2hex($bytes),
                [
                    CryptoResult::detail('Base64', base64_encode($bytes)),
                    CryptoResult::detail('Panjang', strlen($text).' byte'),
                ],
                ['C = P ⊕ K (per byte, kunci diulang)', ...$this->steps($text, $key), 'Hasil ditampilkan dalam Hex.'],
            );
        }

        $cipher = $this->hexToBytes($text);
        $plain = $this->xor($cipher, $key);
        if (! mb_check_encoding($plain, 'UTF-8')) {
            throw new CalculationException('Hasil dekripsi bukan teks yang valid. Pastikan kuncinya benar.');
        }

        return new CryptoResult(
            'XOR dekripsi kunci="'.mb_strimwidth($key, 0, 20, '…').'"',
            $plain,
            [CryptoResult::detail('Panjang', strlen($cipher).' byte')],
            ['P = C ⊕ K (XOR dengan kunci yang sama mengembalikan teks asli)', ...$this->steps($cipher, $key)],
        );
    }

    private function xor(string $data, string $key): string
    {
        $out = '';
        $keyLength = strlen($key);
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $out .= $data[$i] ^ $key[$i % $keyLength];
        }

        return $out;
    }

    /** @return list<string> */
    private function steps(string $data, string $key): array
    {
        $steps = [];
        $limit = min(strlen($data), self::STEP_LIMIT);
        for ($i = 0; $i < $limit; $i++) {
            $a = ord($data[$i]);
            $b = ord($key[$i % strlen($key)]);
            $steps[] = sprintf('%s ⊕ %s = %08b ⊕ %08b = %08b (0x%02x)', $this->label($a), $this->label($b), $a, $b, $a ^ $b, $a ^ $b);
        }
        if (strlen($data) > $limit) {
            $steps[] = '… ('.(strlen($data) - $limit).' byte lagi)';
        }

        return $steps;
    }

    private function label(int $byte): string
    {
        return $byte >= 33 && $byte < 127 ? "'".chr($byte)."'" : sprintf('0x%02x', $byte);
    }

    private function hexToBytes(string $hex): string
    {
        $clean = preg_replace('/[\s:]+|^0x/i', '', $hex);
        if ($clean === '' || strlen($clean) % 2 !== 0 || ! ctype_xdigit($clean)) {
            throw new CalculationException('Cipherteks XOR harus berupa Hex (mis. 030a1f…).');
        }

        return hex2bin($clean);
    }
}
