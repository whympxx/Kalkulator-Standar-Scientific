<?php

namespace App\Services\Crypto;

/**
 * Hasil satu perhitungan kriptografi.
 */
final class CryptoResult
{
    /**
     * @param  string  $summary  ringkasan untuk riwayat, mis. "7^560 mod 561"
     * @param  string  $result  hasil utama
     * @param  list<array{label: string, value: string}>  $details  nilai pendukung
     * @param  list<string>  $steps  langkah pengerjaan
     */
    public function __construct(
        public readonly string $summary,
        public readonly string $result,
        public readonly array $details = [],
        public readonly array $steps = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'result' => $this->result,
            'details' => $this->details,
            'steps' => $this->steps,
        ];
    }

    /** @return array{label: string, value: string} */
    public static function detail(string $label, string $value): array
    {
        return ['label' => $label, 'value' => $value];
    }
}
