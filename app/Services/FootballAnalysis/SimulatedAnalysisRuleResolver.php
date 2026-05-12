<?php

namespace App\Services\FootballAnalysis;

class SimulatedAnalysisRuleResolver extends AnalysisRuleResolver
{
    private ?array $simulated = null;

    public function __construct(
        AnalysisRuleCatalog $catalog,
        private readonly AnalysisRuleResolver $baseResolver,
        private readonly array $overrides = [],
    ) {
        parent::__construct($catalog);
    }

    public function all(): array
    {
        return $this->simulated ??= $this->resolveSimulated();
    }

    private function resolveSimulated(): array
    {
        $rules = $this->baseResolver->all();

        foreach ($this->overrides as $key => $value) {
            if (! isset($rules[$key])) {
                continue;
            }

            $casted = $this->castOverride($value, $rules[$key]['type']);
            $rules[$key]['value'] = $casted;
            $rules[$key]['config']['value'] = $casted;

            if (is_numeric($casted)) {
                $rules[$key]['weight'] = (float) $casted;
            }
        }

        return $rules;
    }

    private function castOverride(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) round((float) $value),
            default => round((float) $value, 4),
        };
    }
}
