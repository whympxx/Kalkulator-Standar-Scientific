<?php

namespace App\Services\Calculator;

use Illuminate\Contracts\Session\Session;

/**
 * Menyimpan riwayat perhitungan di session pengguna.
 */
class HistoryRepository
{
    public const LIMIT = 10;

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
            'expression' => $expression,
            'result' => $result,
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
