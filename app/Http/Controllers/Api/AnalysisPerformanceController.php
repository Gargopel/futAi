<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class AnalysisPerformanceController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $analyses = AnalysisResult::query()->get();
        $evaluated = $analyses->whereIn('result_status', ['won', 'lost', 'void']);
        $won = $analyses->where('result_status', 'won')->count();
        $lost = $analyses->where('result_status', 'lost')->count();
        $void = $analyses->where('result_status', 'void')->count();
        $unknown = $analyses->where('result_status', 'unknown')->count();
        $pending = $analyses->filter(fn (AnalysisResult $analysis) => blank($analysis->result_status) || $analysis->result_status === 'pending')->count();

        return response()->json([
            'total_analyses' => $analyses->count(),
            'evaluated_analyses' => $evaluated->count(),
            'pending_analyses' => $pending,
            'won' => $won,
            'lost' => $lost,
            'void' => $void,
            'unknown' => $unknown,
            'win_rate' => $this->winRate($won, $lost),
            'average_confidence' => round((float) ($analyses->avg('confidence') ?? 0), 2),
            'win_rate_by_market' => $this->groupedWinRates($evaluated, 'suggested_market'),
            'win_rate_by_risk_level' => $this->groupedWinRates($evaluated, 'risk_level'),
            'win_rate_by_confidence_band' => $this->confidenceBandRates($evaluated),
        ]);
    }

    private function groupedWinRates(Collection $analyses, string $field): array
    {
        return $analyses
            ->groupBy($field)
            ->map(fn (Collection $items, string $key) => $this->summary($key, $items))
            ->values()
            ->all();
    }

    private function confidenceBandRates(Collection $analyses): array
    {
        $bands = [
            '0-44' => fn ($confidence) => $confidence <= 44,
            '45-59' => fn ($confidence) => $confidence >= 45 && $confidence <= 59,
            '60-74' => fn ($confidence) => $confidence >= 60 && $confidence <= 74,
            '75-84' => fn ($confidence) => $confidence >= 75 && $confidence <= 84,
            '85-100' => fn ($confidence) => $confidence >= 85,
        ];

        return collect($bands)->map(function (callable $filter, string $label) use ($analyses) {
            $items = $analyses->filter(fn (AnalysisResult $analysis) => $filter((float) $analysis->confidence));

            return $this->summary($label, $items);
        })->values()->all();
    }

    private function summary(string $label, Collection $items): array
    {
        $won = $items->where('result_status', 'won')->count();
        $lost = $items->where('result_status', 'lost')->count();

        return [
            'label' => $label ?: 'Sem classificacao',
            'total' => $items->count(),
            'won' => $won,
            'lost' => $lost,
            'win_rate' => $this->winRate($won, $lost),
        ];
    }

    private function winRate(int $won, int $lost): float
    {
        $total = $won + $lost;

        return $total === 0 ? 0.0 : round(($won / $total) * 100, 2);
    }
}
