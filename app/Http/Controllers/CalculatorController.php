<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculateRequest;
use App\Services\Calculator\CalculationException;
use App\Services\Calculator\ExpressionEvaluator;
use App\Services\Calculator\HistoryRepository;
use App\Services\Calculator\ResultFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalculatorController extends Controller
{
    private const MAX_PLOTS = 3;

    private const MODE_LABELS = [
        'standard' => 'Standar',
        'scientific' => 'Scientific',
        'crypto' => 'Kriptografi',
    ];

    public function __construct(private readonly HistoryRepository $history) {}

    public function index(): View
    {
        return view('calculator', [
            'history' => $this->history->all(),
            'historyLimit' => HistoryRepository::LIMIT,
        ]);
    }

    public function calculate(
        CalculateRequest $request,
        ExpressionEvaluator $evaluator,
        ResultFormatter $formatter,
    ): JsonResponse {
        $expression = trim($request->validated('expression'));
        $mode = $request->validated('mode', 'standard');

        try {
            $result = $formatter->format(
                $evaluator->evaluate($expression, $request->validated('angle', 'deg'))
            );
        } catch (CalculationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->history->add($expression, $result, $mode);

        return response()->json([
            'expression' => $expression,
            'result' => $result,
            'history' => $this->history->all(),
        ]);
    }

    /**
     * Pratinjau hasil saat mengetik — tidak disimpan ke riwayat.
     */
    public function preview(
        CalculateRequest $request,
        ExpressionEvaluator $evaluator,
        ResultFormatter $formatter,
    ): JsonResponse {
        try {
            $result = $formatter->format(
                $evaluator->evaluate(trim($request->validated('expression')), $request->validated('angle', 'deg'))
            );
        } catch (CalculationException $e) {
            return response()->json(['result' => null, 'message' => $e->getMessage()]);
        }

        return response()->json(['result' => $result]);
    }

    /**
     * Titik-titik grafik untuk 1–3 fungsi f(x).
     */
    public function plot(Request $request, ExpressionEvaluator $evaluator): JsonResponse
    {
        $data = $request->validate([
            'functions' => ['required', 'array', 'min:1', 'max:'.self::MAX_PLOTS],
            'functions.*' => ['nullable', 'string', 'max:'.ExpressionEvaluator::MAX_LENGTH],
            'from' => ['required', 'numeric', 'between:-1000000,1000000'],
            'to' => ['required', 'numeric', 'between:-1000000,1000000', 'gt:from'],
            'samples' => ['sometimes', 'integer', 'between:50,1000'],
            'angle' => ['sometimes', 'in:deg,rad'],
        ], [
            'functions.required' => 'Isi minimal satu fungsi.',
            'functions.max' => 'Maksimal '.self::MAX_PLOTS.' fungsi.',
            'to.gt' => 'Batas kanan x harus lebih besar dari batas kiri.',
            'between' => 'Rentang x harus antara -1.000.000 dan 1.000.000.',
            'numeric' => 'Rentang x harus berupa angka.',
        ]);

        $from = (float) $data['from'];
        $to = (float) $data['to'];
        $samples = (int) ($data['samples'] ?? 400);
        $xs = [];
        for ($i = 0; $i < $samples; $i++) {
            $xs[] = $from + ($to - $from) * $i / ($samples - 1);
        }

        $series = [];
        foreach ($data['functions'] as $expression) {
            $expression = trim((string) $expression);
            if ($expression === '') {
                continue;
            }
            try {
                $series[] = [
                    'expression' => $expression,
                    'y' => $evaluator->evaluateSeries($expression, $xs, $data['angle'] ?? 'rad'),
                ];
            } catch (CalculationException $e) {
                $series[] = ['expression' => $expression, 'error' => $e->getMessage()];
            }
        }

        if ($series === []) {
            return response()->json(['message' => 'Isi minimal satu fungsi, contoh: sin(x)'], 422);
        }

        return response()->json(['x' => $xs, 'series' => $series]);
    }

    /**
     * Unduh riwayat sebagai file CSV.
     */
    public function exportHistory(): StreamedResponse
    {
        $rows = $this->history->all();
        $filename = 'riwayat-kalkulator-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Waktu', 'Mode', 'Ekspresi', 'Hasil'], ',', '"', '');
            foreach (array_reverse($rows) as $row) {
                fputcsv($out, [
                    $row['time'],
                    self::MODE_LABELS[$row['mode']] ?? $row['mode'],
                    $this->safeCell($row['expression']),
                    $this->safeCell($row['result']),
                ], ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah CSV injection: sel yang diawali =, +, -, @ dianggap rumus oleh Excel. */
    private function safeCell(string $value): string
    {
        if (is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    public function history(): JsonResponse
    {
        return response()->json(['history' => $this->history->all()]);
    }

    public function clearHistory(): JsonResponse
    {
        $this->history->clear();

        return response()->json(['history' => []]);
    }
}
