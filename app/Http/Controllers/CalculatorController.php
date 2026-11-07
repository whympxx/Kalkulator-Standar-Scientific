<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculateRequest;
use App\Services\Calculator\CalculationException;
use App\Services\Calculator\ExpressionEvaluator;
use App\Services\Calculator\HistoryRepository;
use App\Services\Calculator\ResultFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class CalculatorController extends Controller
{
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
