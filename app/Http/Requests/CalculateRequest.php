<?php

namespace App\Http\Requests;

use App\Services\Calculator\ExpressionEvaluator;
use Illuminate\Foundation\Http\FormRequest;

class CalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expression' => ['required', 'string', 'max:'.ExpressionEvaluator::MAX_LENGTH],
            'mode' => ['sometimes', 'in:standard,scientific'],
            'angle' => ['sometimes', 'in:deg,rad'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expression.required' => 'Ekspresi masih kosong.',
            'expression.max' => 'Ekspresi terlalu panjang (maks. :max karakter).',
            'mode.in' => 'Mode kalkulator tidak dikenal.',
            'angle.in' => 'Satuan sudut harus deg atau rad.',
        ];
    }
}
