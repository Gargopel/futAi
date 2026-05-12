<?php

namespace App\Services\FootballAnalysis;

class RiskClassifier
{
    public function __construct(private readonly AnalysisRuleResolver $rules) {}

    public function classify(float $confidence, int $minimumSampleSize): string
    {
        $minimumMatchesRequired = $this->rules->integer('global.minimum_matches_required');

        if ($minimumSampleSize < 3) {
            return 'high';
        }

        if ($minimumSampleSize < $minimumMatchesRequired && $confidence < $this->rules->number('risk.low_min_confidence')) {
            return 'high';
        }

        return match (true) {
            $confidence >= $this->rules->number('risk.low_min_confidence') => 'low',
            $confidence >= $this->rules->number('risk.medium_min_confidence') => 'medium',
            default => 'high',
        };
    }
}
