<?php

namespace App\Http\Controllers;

use App\Services\Calculator\CalculationException;
use App\Services\Calculator\HistoryRepository;
use App\Services\Crypto\BaseConverter;
use App\Services\Crypto\ClassicCipher;
use App\Services\Crypto\CryptoResult;
use App\Services\Crypto\HashCalculator;
use App\Services\Crypto\ModularCalculator;
use App\Services\Crypto\RsaCalculator;
use App\Services\Crypto\XorCipher;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CryptoController extends Controller
{
    /** Batas digit angka besar (p, q, a, b, n, …). */
    private const MAX_DIGITS = 400;

    private const MAX_TEXT = 2000;

    public function __construct(private readonly HistoryRepository $history) {}

    public function modular(Request $request, ModularCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'operation' => ['required', Rule::in(ModularCalculator::OPERATIONS)],
            'a' => ['nullable', 'string', 'max:'.self::MAX_DIGITS],
            'b' => ['nullable', 'string', 'max:'.self::MAX_DIGITS],
            'n' => ['nullable', 'string', 'max:'.self::MAX_DIGITS],
        ], $this->messages());

        return $this->respond(fn () => $calculator->calculate(
            $data['operation'], $data['a'] ?? null, $data['b'] ?? null, $data['n'] ?? null,
        ));
    }

    public function rsa(Request $request, RsaCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(RsaCalculator::ACTIONS)],
            'p' => ['required', 'string', 'max:'.self::MAX_DIGITS],
            'q' => ['required', 'string', 'max:'.self::MAX_DIGITS],
            'e' => ['nullable', 'string', 'max:'.self::MAX_DIGITS],
            'message' => ['nullable', 'string', 'max:'.self::MAX_TEXT * 4],
            'format' => ['sometimes', Rule::in(['number', 'text'])],
        ], $this->messages() + [
            'p.required' => 'Bilangan prima p wajib diisi.',
            'q.required' => 'Bilangan prima q wajib diisi.',
        ]);

        return $this->respond(fn () => $calculator->calculate(
            $data['action'], $data['p'], $data['q'], $data['e'] ?? null, $data['message'] ?? null, $data['format'] ?? 'number',
        ));
    }

    public function cipher(Request $request, ClassicCipher $cipher): JsonResponse
    {
        $data = $request->validate([
            'cipher' => ['required', Rule::in(ClassicCipher::CIPHERS)],
            'action' => ['required', Rule::in(['encrypt', 'decrypt', 'bruteforce'])],
            'text' => ['required', 'string', 'max:'.self::MAX_TEXT],
            'shift' => ['nullable'],
            'key' => ['nullable', 'string', 'max:200'],
            'a' => ['nullable'],
            'b' => ['nullable'],
        ], $this->messages());

        return $this->respond(fn () => $cipher->calculate($data['cipher'], $data['action'], $data['text'], $data));
    }

    public function base(Request $request, BaseConverter $converter): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:'.self::MAX_DIGITS],
            'from_base' => ['required', 'integer', 'between:2,36'],
            'to_base' => ['required', 'integer', 'between:2,36'],
        ], $this->messages() + [
            'between' => 'Basis harus antara 2 dan 36.',
            'integer' => 'Basis harus bilangan bulat.',
        ]);

        return $this->respond(fn () => $converter->convert($data['value'], (int) $data['from_base'], (int) $data['to_base']));
    }

    public function xor(Request $request, XorCipher $cipher): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['encrypt', 'decrypt'])],
            'text' => ['required', 'string', 'max:'.self::MAX_TEXT * 2],
            'key' => ['required', 'string', 'max:200'],
        ], $this->messages());

        return $this->respond(fn () => $cipher->calculate($data['action'], $data['text'], $data['key']));
    }

    public function hash(Request $request, HashCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(HashCalculator::ACTIONS)],
            'text' => ['present', 'nullable', 'string', 'max:'.self::MAX_TEXT * 4],
        ], $this->messages());

        return $this->respond(fn () => $calculator->calculate($data['action'], $data['text'] ?? ''));
    }

    /**
     * @param  Closure(): CryptoResult  $compute
     */
    private function respond(Closure $compute): JsonResponse
    {
        try {
            $result = $compute();
        } catch (CalculationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->history->add($result->summary, $result->result, 'crypto');

        return response()->json($result->toArray() + ['history' => $this->history->all()]);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'in' => 'Pilihan :attribute tidak dikenal.',
            'max' => 'Kolom :attribute terlalu panjang (maks. :max karakter).',
            'string' => 'Kolom :attribute harus berupa teks.',
            'present' => 'Kolom :attribute wajib dikirim.',
        ];
    }
}
