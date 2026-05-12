<?php

namespace App\Services\FootballAnalysis;

use App\Models\AnalysisResult;
use App\Models\FootballMatch;

class FootballAnalysisService
{
    public function __construct(
        private readonly TeamFormService $teamFormService,
        private readonly MarketSuggestionService $marketSuggestionService,
        private readonly AnalysisNarrativeService $analysisNarrativeService,
        private readonly AnalysisEvaluationService $analysisEvaluationService,
        private readonly AnalysisRuleResolver $rules,
    ) {}

    public function analyze(FootballMatch $match): array
    {
        $match->loadMissing(['league', 'homeTeam', 'awayTeam']);

        $homeForm = $this->teamFormService->calculate($match->homeTeam, $match->id);
        $awayForm = $this->teamFormService->calculate($match->awayTeam, $match->id);
        $sampleWarnings = $this->sampleWarnings($homeForm, $awayForm);
        $suggestions = $this->marketSuggestionService->suggest($homeForm, $awayForm, $sampleWarnings);
        $selectedSuggestion = $suggestions[0];
        $summary = $this->analysisNarrativeService->summarize($selectedSuggestion, $homeForm, $awayForm, $sampleWarnings);
        $factors = $this->analysisNarrativeService->factors($selectedSuggestion, $homeForm, $awayForm, $sampleWarnings);

        $data = [
            'home_team_form' => $homeForm,
            'away_team_form' => $awayForm,
            'suggestions' => $suggestions,
            'selected_suggestion' => $selectedSuggestion,
            'sample_warnings' => $sampleWarnings,
            'rules' => $this->rules->all(),
        ];

        $analysisResult = $match->analysisResults()->latest()->first() ?? new AnalysisResult(['match_id' => $match->id]);
        $analysisResult->fill([
            'match_id' => $match->id,
            'summary' => $summary,
            'confidence' => $selectedSuggestion['confidence'],
            'risk_level' => $selectedSuggestion['risk_level'],
            'suggested_market' => $selectedSuggestion['market'],
            'suggested_selection' => $selectedSuggestion['selection'],
            'result_status' => 'pending',
            'data' => $data,
            'generated_at' => now(),
            'evaluated_at' => null,
            'evaluation_reason' => null,
        ]);
        $analysisResult->save();

        if ($match->status === 'finished' && $match->home_goals !== null && $match->away_goals !== null) {
            $analysisResult = $this->analysisEvaluationService->evaluate($analysisResult);
        }

        return [
            'match_id' => $match->id,
            'analysis_result_id' => $analysisResult->id,
            'league' => $match->league?->name,
            'home_team' => $match->homeTeam?->name,
            'away_team' => $match->awayTeam?->name,
            'suggested_market' => $selectedSuggestion['market'],
            'suggested_selection' => $selectedSuggestion['selection'],
            'confidence' => $selectedSuggestion['confidence'],
            'risk_level' => $selectedSuggestion['risk_level'],
            'result_status' => $analysisResult->result_status,
            'evaluated_at' => $analysisResult->evaluated_at?->toISOString(),
            'evaluation_reason' => $analysisResult->evaluation_reason,
            'summary' => $summary,
            'factors' => $factors,
            'metrics' => [
                'home_team_form' => $homeForm,
                'away_team_form' => $awayForm,
            ],
            'suggestions' => $suggestions,
            'sample_warnings' => $sampleWarnings,
            'generated_at' => $analysisResult->generated_at?->toISOString(),
        ];
    }

    private function sampleWarnings(array $homeForm, array $awayForm): array
    {
        $warnings = [];

        if ($homeForm['sample_size'] < 3) {
            $warnings[] = "{$homeForm['team_name']} tem menos de 3 partidas finalizadas na base local";
        }

        if ($awayForm['sample_size'] < 3) {
            $warnings[] = "{$awayForm['team_name']} tem menos de 3 partidas finalizadas na base local";
        }

        if ($homeForm['sample_size'] < 5 || $awayForm['sample_size'] < 5) {
            $warnings[] = 'Amostra pequena: confianca reduzida automaticamente';
        }

        return array_values(array_unique($warnings));
    }
}
