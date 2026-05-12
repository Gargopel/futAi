<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FootballAnalysis\BacktestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BacktestController extends Controller
{
    public function __invoke(Request $request, BacktestService $backtestService): JsonResponse
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'league_id' => ['nullable', 'exists:leagues,id'],
            'market' => ['nullable', 'string'],
            'rule_overrides' => ['nullable', 'array'],
            'persist' => ['sometimes', 'boolean'],
        ]);

        abort_if(($data['persist'] ?? false) === true, 422, 'Persistencia de backtest ainda nao esta habilitada nesta etapa.');

        return response()->json($backtestService->run($data));
    }
}
