<?php

namespace App\Services\FootballAnalysis;

use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BacktestService
{
    public function __construct(
        private readonly TeamFormService $teamFormService,
        private readonly AnalysisNarrativeService $narrativeService,
        private readonly AnalysisEvaluationService $evaluationService,
        private readonly AnalysisRuleCatalog $catalog,
        private readonly AnalysisRuleResolver $baseRules,
    ) {}

    public function run(array $filters): array
    {
        $rules = new SimulatedAnalysisRuleResolver(
            $this->catalog,
            $this->baseRules,
            $filters['rule_overrides'] ?? [],
        );

        $marketSuggestionService = new MarketSuggestionService(
            new ConfidenceScoreService($rules),
            new RiskClassifier($rules),
            $rules,
        );

        $matches = $this->eligibleMatches($filters);

        $items = $matches->map(function (FootballMatch $match) use ($marketSuggestionService, $rules, $filters) {
            $match->loadMissing(['league', 'homeTeam', 'awayTeam']);
            $homeForm = $this->teamFormService->calculate($match->homeTeam, $match->id, $match->starts_at);
            $awayForm = $this->teamFormService->calculate($match->awayTeam, $match->id, $match->starts_at);
            $sampleWarnings = $this->sampleWarnings($homeForm, $awayForm, $rules);
            $suggestions = $marketSuggestionService->suggest($homeForm, $awayForm, $sampleWarnings);

            if (! empty($filters['market'])) {
                $suggestions = collect($suggestions)
                    ->where('market', $filters['market'])
                    ->values()
                    ->all();
            }

            $selected = $suggestions[0] ?? [
                'market' => 'No Market Enabled',
                'selection' => 'Sem sugestao',
                'score' => 0,
                'confidence' => 0,
                'risk_level' => 'high',
                'reasons' => ['Nenhuma sugestao elegivel para os filtros do backtest.'],
            ];

            [$status, $reason] = $this->evaluationService->evaluateMarketResult(
                $selected['market'],
                $match->status,
                $match->home_goals,
                $match->away_goals,
            );

            return [
                'match_id' => $match->id,
                'league' => $match->league?->name,
                'home_team' => $match->homeTeam?->name,
                'away_team' => $match->awayTeam?->name,
                'starts_at' => $match->starts_at?->toISOString(),
                'match_score' => "{$match->home_goals} - {$match->away_goals}",
                'suggested_market' => $selected['market'],
                'suggested_selection' => $selected['selection'],
                'score' => $selected['score'],
                'confidence' => $selected['confidence'],
                'risk_level' => $selected['risk_level'],
                'result_status' => $status,
                'evaluation_reason' => $reason,
                'sample_warnings' => $sampleWarnings,
                'factors' => array_slice($selected['reasons'], 0, 4),
                'metrics' => [
                    'home_team_form' => $homeForm,
                    'away_team_form' => $awayForm,
                ],
                'suggestions' => $suggestions,
            ];
        })->values();

        return [
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'league_id' => $filters['league_id'] ?? null,
                'market' => $filters['market'] ?? null,
                'rule_overrides' => $filters['rule_overrides'] ?? [],
            ],
            'summary' => $this->summary($items),
            'by_market' => $this->groupedSummary($items, 'suggested_market'),
            'by_risk_level' => $this->groupedSummary($items, 'risk_level'),
            'by_confidence_band' => $this->confidenceBands($items),
            'items' => $items,
            'rules' => array_values($rules->all()),
        ];
    }

    private function eligibleMatches(array $filters): Collection
    {
        return FootballMatch::query()
            ->with(['league', 'homeTeam', 'awayTeam'])
            ->where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('starts_at', '>=', CarbonImmutable::parse($filters['date_from'])))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('starts_at', '<=', CarbonImmutable::parse($filters['date_to'])))
            ->when(! empty($filters['league_id']), fn ($query) => $query->where('league_id', $filters['league_id']))
            ->orderBy('starts_at')
            ->get();
    }

    private function sampleWarnings(array $homeForm, array $awayForm, AnalysisRuleResolver $rules): array
    {
        $warnings = [];
        $minimumMatches = $rules->integer('global.minimum_matches_required');

        if ($homeForm['sample_size'] < 3) {
            $warnings[] = "{$homeForm['team_name']} tem menos de 3 partidas finalizadas antes da data testada";
        }

        if ($awayForm['sample_size'] < 3) {
            $warnings[] = "{$awayForm['team_name']} tem menos de 3 partidas finalizadas antes da data testada";
        }

        if ($homeForm['sample_size'] < $minimumMatches || $awayForm['sample_size'] < $minimumMatches) {
            $warnings[] = 'Amostra historica pequena no ponto do tempo simulado';
        }

        return array_values(array_unique($warnings));
    }

    private function summary(Collection $items): array
    {
        $won = $items->where('result_status', 'won')->count();
        $lost = $items->where('result_status', 'lost')->count();
        $void = $items->where('result_status', 'void')->count();
        $unknown = $items->where('result_status', 'unknown')->count();
        $pending = $items->where('result_status', 'pending')->count();

        return [
            'total_matches' => $items->count(),
            'evaluated' => $won + $lost + $void,
            'won' => $won,
            'lost' => $lost,
            'void' => $void,
            'unknown' => $unknown,
            'pending' => $pending,
            'win_rate' => $this->winRate($won, $lost),
            'average_confidence' => round((float) ($items->avg('confidence') ?? 0), 2),
        ];
    }

    private function groupedSummary(Collection $items, string $field): array
    {
        return $items->groupBy($field)
            ->map(fn (Collection $group, string $label) => ['label' => $label ?: 'N/A'] + $this->summary($group))
            ->values()
            ->all();
    }

    private function confidenceBands(Collection $items): array
    {
        $bands = [
            '0-44' => fn ($confidence) => $confidence <= 44,
            '45-59' => fn ($confidence) => $confidence >= 45 && $confidence <= 59,
            '60-74' => fn ($confidence) => $confidence >= 60 && $confidence <= 74,
            '75-84' => fn ($confidence) => $confidence >= 75 && $confidence <= 84,
            '85-100' => fn ($confidence) => $confidence >= 85,
        ];

        return collect($bands)->map(function (callable $filter, string $label) use ($items) {
            return ['label' => $label] + $this->summary($items->filter(fn (array $item) => $filter((float) $item['confidence'])));
        })->values()->all();
    }

    private function winRate(int $won, int $lost): float
    {
        $total = $won + $lost;

        return $total === 0 ? 0.0 : round(($won / $total) * 100, 2);
    }
}
