<?php

namespace App\Services\Calculator;

use Illuminate\Contracts\Session\Session;

/**
 * Menyimpan riwayat perhitungan di session pengguna.
 */
class HistoryRepository
{
    public const LIMIT = 10;

    /** Panjang maksimal teks yang disimpan per entri (hasil kripto bisa panjang). */
    private const MAX_TEXT = 2000;

    private const KEY = 'calculator.history';

    public function __construct(private readonly Session $session) {}

    /**
     * @return list<array{expression: string, result: string, mode: string, time: string}>
     */
    public function all(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function add(string $expression, string $result, string $mode): void
    {
        $history = $this->all();

        array_unshift($history, [
            'expression' => mb_strimwidth($expression, 0, self::MAX_TEXT, '…'),
            'result' => mb_strimwidth($result, 0, self::MAX_TEXT, '…'),
            'mode' => $mode,
            'time' => now()->toIso8601String(),
        ]);

        $this->session->put(self::KEY, array_slice($history, 0, self::LIMIT));
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }
}
