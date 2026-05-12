<?php

namespace App\Services\FootballAnalysis;

use App\Models\AnalysisResult;
use App\Models\FootballMatch;

class AnalysisEvaluationService
{
    public function evaluate(AnalysisResult $analysisResult): AnalysisResult
    {
        $analysisResult->loadMissing('match');
        $match = $analysisResult->match;

        [$status, $reason] = $this->evaluateMarketResult(
            $analysisResult->suggested_market,
            $match?->status,
            $match?->home_goals,
            $match?->away_goals,
        );

        $analysisResult->fill([
            'result_status' => $status,
            'evaluated_at' => now(),
            'evaluation_reason' => $reason,
        ])->save();

        return $analysisResult;
    }

    public function evaluateMatch(FootballMatch $match, bool $all = true): int
    {
        $query = $match->analysisResults();

        if (! $all) {
            $query->where(fn ($query) => $query
                ->whereNull('result_status')
                ->orWhereIn('result_status', ['pending', 'unknown']));
        }

        return $query->get()
            ->each(fn (AnalysisResult $analysisResult) => $this->evaluate($analysisResult))
            ->count();
    }

    public function evaluateMarketResult(?string $market, ?string $matchStatus, ?int $homeGoals, ?int $awayGoals): array
    {
        if ($matchStatus !== 'finished' || $homeGoals === null || $awayGoals === null) {
            return ['pending', 'Analise pendente porque a partida ainda nao foi finalizada.'];
        }

        $totalGoals = $homeGoals + $awayGoals;

        return match ($market) {
            'Over 1.5 Goals' => $this->result(
                $totalGoals >= 2,
                "Over 1.5 venceu porque a partida terminou com {$totalGoals} gols.",
                "Over 1.5 perdeu porque a partida terminou com {$totalGoals} gols."
            ),
            'Over 2.5 Goals' => $this->result(
                $totalGoals >= 3,
                "Over 2.5 venceu porque a partida terminou com {$totalGoals} gols.",
                "Over 2.5 perdeu porque a partida terminou com {$totalGoals} gols."
            ),
            'Both Teams To Score' => $this->result(
                $homeGoals >= 1 && $awayGoals >= 1,
                'Ambas marcam venceu porque os dois times marcaram.',
                'Ambas marcam perdeu porque pelo menos um time nao marcou.'
            ),
            'Home Double Chance' => $this->result(
                $homeGoals >= $awayGoals,
                'Home Double Chance venceu porque o mandante venceu ou empatou.',
                'Home Double Chance perdeu porque o visitante venceu a partida.'
            ),
            'Away Double Chance' => $this->result(
                $awayGoals >= $homeGoals,
                'Away Double Chance venceu porque o visitante venceu ou empatou.',
                'Away Double Chance perdeu porque o mandante venceu a partida.'
            ),
            'Home Win Lean' => $this->result(
                $homeGoals > $awayGoals,
                'Home Win Lean venceu porque o mandante venceu a partida.',
                'Home Win Lean perdeu porque o mandante nao venceu a partida.'
            ),
            'Away Win Lean' => $this->result(
                $awayGoals > $homeGoals,
                'Away Win Lean venceu porque o visitante venceu a partida.',
                'Away Win Lean perdeu porque o visitante nao venceu a partida.'
            ),
            default => ['unknown', 'Mercado nao reconhecido para avaliacao automatica.'],
        };
    }

    private function result(bool $won, string $wonReason, string $lostReason): array
    {
        return [$won ? 'won' : 'lost', $won ? $wonReason : $lostReason];
    }
}
