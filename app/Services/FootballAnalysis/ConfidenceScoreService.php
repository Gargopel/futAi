<?php

namespace App\Services\FootballAnalysis;

class ConfidenceScoreService
{
    public function __construct(private readonly AnalysisRuleResolver $rules) {}

    public function fromScore(float $score, int $minimumSampleSize): float
    {
        $confidence = max(0, min(100, $score));
        $minimumMatchesRequired = $this->rules->integer('global.minimum_matches_required');
        $samplePenalty = $this->rules->number('global.sample_size_penalty');

        if ($minimumSampleSize < 3) {
            $confidence -= $samplePenalty * 2.25;
        } elseif ($minimumSampleSize < $minimumMatchesRequired) {
            $confidence -= $samplePenalty;
        }

        return round(max(0, min(100, $confidence)), 2);
    }

    public function label(float $confidence): string
    {
        return match (true) {
            $confidence < $this->rules->number('confidence.low_threshold') => 'baixa',
            $confidence < $this->rules->number('confidence.medium_threshold') => 'moderada/baixa',
            $confidence < $this->rules->number('confidence.high_threshold') => 'media',
            $confidence < $this->rules->number('confidence.very_high_threshold') => 'alta',
            default => 'muito alta',
        };
    }
}
