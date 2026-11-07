<?php

namespace App\Services\Calculator;

/**
 * Memecah teks ekspresi menjadi token.
 *
 * Token: ['type' => num|const|func|op|postfix|lparen|rparen, 'value' => mixed]
 */
class Tokenizer
{
    public const FUNCTIONS = ['sin', 'cos', 'tan', 'asin', 'acos', 'atan', 'log', 'ln', 'sqrt', 'exp', 'abs'];

    public const CONSTANTS = ['pi' => M_PI, 'e' => M_E];

    /** Simbol tampilan yang boleh dikirim dari UI / keyboard. */
    private const SYMBOLS = [
        '×' => '*',
        '÷' => '/',
        '−' => '-',
        'π' => 'pi',
        '√' => 'sqrt',
    ];

    /**
     * @return list<array{type: string, value: mixed}>
     */
    public function tokenize(string $expression): array
    {
        $input = strtolower(strtr($expression, self::SYMBOLS));
        $length = strlen($input);
        $tokens = [];
        $i = 0;

        while ($i < $length) {
            $char = $input[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if (preg_match('/\G(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?/', $input, $m, 0, $i)) {
                $tokens[] = ['type' => 'num', 'value' => (float) $m[0]];
                $i += strlen($m[0]);

                continue;
            }

            if (preg_match('/\G[a-z]+/', $input, $m, 0, $i)) {
                $tokens = [...$tokens, ...$this->splitIdentifier($m[0])];
                $i += strlen($m[0]);

                continue;
            }

            $tokens[] = match ($char) {
                '+', '-', '*', '/', '^' => ['type' => 'op', 'value' => $char],
                '!', '%' => ['type' => 'postfix', 'value' => $char],
                '(' => ['type' => 'lparen', 'value' => $char],
                ')' => ['type' => 'rparen', 'value' => $char],
                default => throw new CalculationException("Karakter tidak dikenal: \"{$char}\"."),
            };
            $i++;
        }

        return $tokens;
    }

    /**
     * Mengenali nama fungsi/konstanta, termasuk yang ditulis berdempetan
     * seperti "pie" (π·e) atau "esin" (e·sin).
     *
     * @return list<array{type: string, value: mixed}>
     */
    private function splitIdentifier(string $word): array
    {
        $names = [...self::FUNCTIONS, ...array_keys(self::CONSTANTS), 'x'];
        // Nama terpanjang dulu agar "asin" tidak terbaca "a" + "sin".
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));

        $tokens = [];
        while ($word !== '') {
            foreach ($names as $name) {
                if (str_starts_with($word, $name)) {
                    $tokens[] = match (true) {
                        $name === 'x' => ['type' => 'var', 'value' => 'x'],
                        isset(self::CONSTANTS[$name]) => ['type' => 'const', 'value' => self::CONSTANTS[$name]],
                        default => ['type' => 'func', 'value' => $name],
                    };
                    $word = substr($word, strlen($name));

                    continue 2;
                }
            }

            throw new CalculationException("Fungsi tidak dikenal: \"{$word}\".");
        }

        return $tokens;
    }
}
