<?php

namespace App\Services\Calculator;

/**
 * Evaluator ekspresi matematika dengan recursive-descent parser (tanpa eval()).
 *
 * Tata bahasa (prioritas rendah ke tinggi):
 *   expression := term (('+' | '-') term)*
 *   term       := unary (('*' | '/') unary | <perkalian implisit> power)*
 *   unary      := ('-' | '+') unary | power
 *   power      := postfix ('^' unary)?          // asosiatif kanan
 *   postfix    := primary ('!' | '%')*
 *   primary    := number | constant | func '(' expression ')' | '(' expression ')'
 *
 * Kurung yang belum ditutup di akhir ekspresi ditutup otomatis.
 */
class ExpressionEvaluator
{
    public const MAX_LENGTH = 200;

    public const MAX_FACTORIAL = 170;

    /** Batas di bawah ini dianggap nol (membuang galat floating point trigonometri). */
    private const EPSILON = 1e-12;

    /** @var list<array{type: string, value: mixed}> */
    private array $tokens = [];

    private int $pos = 0;

    private bool $degrees = true;

    public function __construct(private readonly Tokenizer $tokenizer = new Tokenizer) {}

    /**
     * @param  'deg'|'rad'  $angleMode
     */
    public function evaluate(string $expression, string $angleMode = 'deg'): float
    {
        if (mb_strlen($expression) > self::MAX_LENGTH) {
            throw new CalculationException('Ekspresi terlalu panjang (maks. '.self::MAX_LENGTH.' karakter).');
        }

        $this->degrees = $angleMode !== 'rad';
        $this->tokens = $this->tokenizer->tokenize($expression);
        $this->pos = 0;

        if ($this->tokens === []) {
            throw new CalculationException('Ekspresi masih kosong.');
        }

        $value = $this->parseExpression();

        if ($this->peek() !== null) {
            throw $this->peek()['type'] === 'rparen'
                ? new CalculationException('Kurung tutup berlebih.')
                : CalculationException::syntax();
        }

        return $this->guard($value);
    }

    private function parseExpression(): float
    {
        [$value] = $this->parseTerm();

        while ($this->isOp('+', '-')) {
            $op = $this->next()['value'];
            [$right, $isPercent] = $this->parseTerm();

            // Gaya kalkulator: 200 + 10% = 220, 200 - 10% = 180.
            if ($isPercent) {
                $right *= $value;
            }

            $value = $op === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * @return array{0: float, 1: bool} nilai dan apakah suku ini hanya "x%"
     */
    private function parseTerm(): array
    {
        $value = $this->parseUnary();
        $isPercent = $this->previousIs('postfix', '%');

        while (true) {
            if ($this->isOp('*', '/')) {
                $op = $this->next()['value'];
                $right = $this->parseUnary();

                if ($op === '/' && $right == 0.0) {
                    throw CalculationException::divisionByZero();
                }

                $value = $op === '*' ? $value * $right : $value / $right;
                $isPercent = false;
            } elseif (in_array($this->peek()['type'] ?? null, ['num', 'const', 'func', 'lparen'], true)) {
                // Perkalian implisit: 2π, 3(4+1), (1+1)(2+2), 2sin(30)
                $value *= $this->parsePower();
                $isPercent = false;
            } else {
                return [$value, $isPercent];
            }
        }
    }

    private function parseUnary(): float
    {
        if ($this->isOp('-')) {
            $this->next();

            return -$this->parseUnary();
        }

        if ($this->isOp('+')) {
            $this->next();

            return $this->parseUnary();
        }

        return $this->parsePower();
    }

    private function parsePower(): float
    {
        $base = $this->parsePostfix();

        if (! $this->isOp('^')) {
            return $base;
        }

        $this->next();
        $exponent = $this->parseUnary();

        if ($base == 0.0 && $exponent < 0) {
            throw CalculationException::divisionByZero();
        }

        return $this->guard($base ** $exponent);
    }

    private function parsePostfix(): float
    {
        $value = $this->parsePrimary();

        while (($token = $this->peek()) !== null && $token['type'] === 'postfix') {
            $this->next();
            $value = $token['value'] === '!' ? $this->factorial($value) : $value / 100;
        }

        return $value;
    }

    private function parsePrimary(): float
    {
        $token = $this->next();

        if ($token === null) {
            throw CalculationException::incomplete();
        }

        switch ($token['type']) {
            case 'num':
            case 'const':
                return $token['value'];

            case 'lparen':
                $value = $this->parseExpression();
                $this->closeParen();

                return $value;

            case 'func':
                if (($this->next()['type'] ?? null) !== 'lparen') {
                    throw new CalculationException("Fungsi {$token['value']} harus diikuti kurung, contoh: {$token['value']}(30).");
                }
                $argument = $this->parseExpression();
                $this->closeParen();

                return $this->applyFunction($token['value'], $argument);

            default:
                throw CalculationException::syntax();
        }
    }

    private function closeParen(): void
    {
        $token = $this->peek();

        if ($token === null) {
            return; // ditutup otomatis di akhir ekspresi
        }

        if ($token['type'] !== 'rparen') {
            throw CalculationException::syntax();
        }

        $this->next();
    }

    private function applyFunction(string $name, float $x): float
    {
        return match ($name) {
            'sin' => $this->snap(sin($this->toRadians($x))),
            'cos' => $this->snap(cos($this->toRadians($x))),
            'tan' => $this->tan($x),
            'asin', 'acos' => $x < -1 || $x > 1
                ? throw new CalculationException("Nilai {$name} harus di antara -1 dan 1.")
                : $this->fromRadians($name === 'asin' ? asin($x) : acos($x)),
            'atan' => $this->fromRadians(atan($x)),
            'log', 'ln' => $x <= 0
                ? throw new CalculationException('Logaritma hanya untuk bilangan lebih dari 0.')
                : ($name === 'log' ? log10($x) : log($x)),
            'sqrt' => $x < 0
                ? throw new CalculationException('Akar kuadrat bilangan negatif tidak terdefinisi.')
                : sqrt($x),
            'exp' => exp($x),
            'abs' => abs($x),
        };
    }

    private function tan(float $x): float
    {
        $radians = $this->toRadians($x);
        $cos = $this->snap(cos($radians));

        if ($cos == 0.0) {
            throw new CalculationException('tan tidak terdefinisi untuk sudut ini.');
        }

        return $this->snap(sin($radians)) / $cos;
    }

    private function factorial(float $n): float
    {
        if ($n < 0 || abs($n - round($n)) > 1e-9) {
            throw new CalculationException('Faktorial hanya untuk bilangan bulat ≥ 0.');
        }

        if ($n > self::MAX_FACTORIAL) {
            throw new CalculationException('Faktorial maksimal '.self::MAX_FACTORIAL.'!.');
        }

        $result = 1.0;
        for ($i = 2; $i <= (int) round($n); $i++) {
            $result *= $i;
        }

        return $result;
    }

    private function toRadians(float $x): float
    {
        return $this->degrees ? deg2rad(fmod($x, 360.0)) : $x;
    }

    private function fromRadians(float $x): float
    {
        return $this->degrees ? rad2deg($x) : $x;
    }

    private function snap(float $x): float
    {
        return abs($x) < self::EPSILON ? 0.0 : $x;
    }

    private function guard(float $value): float
    {
        if (is_nan($value)) {
            throw CalculationException::undefined();
        }

        if (is_infinite($value)) {
            throw new CalculationException('Hasil terlalu besar.');
        }

        return $value;
    }

    /** @return array{type: string, value: mixed}|null */
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    /** @return array{type: string, value: mixed}|null */
    private function next(): ?array
    {
        return $this->tokens[$this->pos++] ?? null;
    }

    private function isOp(string ...$ops): bool
    {
        $token = $this->peek();

        return $token !== null && $token['type'] === 'op' && in_array($token['value'], $ops, true);
    }

    private function previousIs(string $type, string $value): bool
    {
        $token = $this->tokens[$this->pos - 1] ?? null;

        return $token !== null && $token['type'] === $type && $token['value'] === $value;
    }
}
