<?php

namespace App\Services\FootballAnalysis;

class AnalysisRuleCatalog
{
    public function all(): array
    {
        return [
            'global.recent_form_weight' => $this->number('global.recent_form_weight', 'Peso da forma recente', 'Multiplicador aplicado a indicadores de forma recente.', 1),
            'global.home_advantage_weight' => $this->number('global.home_advantage_weight', 'Peso do mando de campo', 'Multiplicador aplicado a indicadores de mandante/visitante.', 1),
            'global.attack_weight' => $this->number('global.attack_weight', 'Peso do ataque', 'Multiplicador aplicado a indicadores ofensivos.', 1),
            'global.defense_weight' => $this->number('global.defense_weight', 'Peso da defesa adversaria', 'Multiplicador aplicado a indicadores defensivos e gols sofridos.', 1),
            'global.sample_size_penalty' => $this->number('global.sample_size_penalty', 'Penalidade por amostra pequena', 'Redutor de confianca aplicado quando ha poucos jogos.', 8),
            'global.minimum_matches_required' => $this->integer('global.minimum_matches_required', 'Minimo de jogos recomendados', 'Amostra minima antes de reduzir a confianca e elevar risco.', 5),

            'market.over_1_5.enabled' => $this->boolean('market.over_1_5.enabled', 'Ativar Over 1.5', 'Permite que o motor sugira Over 1.5 Goals.', true),
            'market.over_1_5.weight' => $this->number('market.over_1_5.weight', 'Peso Over 1.5', 'Multiplicador final do score de Over 1.5 Goals.', 1),
            'market.over_2_5.enabled' => $this->boolean('market.over_2_5.enabled', 'Ativar Over 2.5', 'Permite que o motor sugira Over 2.5 Goals.', true),
            'market.over_2_5.weight' => $this->number('market.over_2_5.weight', 'Peso Over 2.5', 'Multiplicador final do score de Over 2.5 Goals.', 1),
            'market.btts.enabled' => $this->boolean('market.btts.enabled', 'Ativar Ambas Marcam', 'Permite que o motor sugira Both Teams To Score.', true),
            'market.btts.weight' => $this->number('market.btts.weight', 'Peso Ambas Marcam', 'Multiplicador final do score de Both Teams To Score.', 1),
            'market.home_double_chance.enabled' => $this->boolean('market.home_double_chance.enabled', 'Ativar dupla chance mandante', 'Permite que o motor sugira Home Double Chance.', true),
            'market.home_double_chance.weight' => $this->number('market.home_double_chance.weight', 'Peso dupla chance mandante', 'Multiplicador final do score de Home Double Chance.', 1),
            'market.away_double_chance.enabled' => $this->boolean('market.away_double_chance.enabled', 'Ativar dupla chance visitante', 'Permite que o motor sugira Away Double Chance.', true),
            'market.away_double_chance.weight' => $this->number('market.away_double_chance.weight', 'Peso dupla chance visitante', 'Multiplicador final do score de Away Double Chance.', 1),
            'market.home_win_lean.enabled' => $this->boolean('market.home_win_lean.enabled', 'Ativar tendencia mandante', 'Permite que o motor sugira Home Win Lean.', true),
            'market.home_win_lean.weight' => $this->number('market.home_win_lean.weight', 'Peso tendencia mandante', 'Multiplicador final do score de Home Win Lean.', 1),
            'market.away_win_lean.enabled' => $this->boolean('market.away_win_lean.enabled', 'Ativar tendencia visitante', 'Permite que o motor sugira Away Win Lean.', true),
            'market.away_win_lean.weight' => $this->number('market.away_win_lean.weight', 'Peso tendencia visitante', 'Multiplicador final do score de Away Win Lean.', 1),

            'confidence.low_threshold' => $this->number('confidence.low_threshold', 'Limite confianca baixa', 'Abaixo deste valor a confianca e baixa.', 45),
            'confidence.medium_threshold' => $this->number('confidence.medium_threshold', 'Limite confianca media', 'A partir deste valor a confianca deixa de ser moderada/baixa.', 60),
            'confidence.high_threshold' => $this->number('confidence.high_threshold', 'Limite confianca alta', 'A partir deste valor a confianca e alta.', 75),
            'confidence.very_high_threshold' => $this->number('confidence.very_high_threshold', 'Limite confianca muito alta', 'A partir deste valor a confianca e muito alta.', 85),
            'risk.low_min_confidence' => $this->number('risk.low_min_confidence', 'Confianca minima para risco baixo', 'Confianca minima para classificar risco baixo.', 75),
            'risk.medium_min_confidence' => $this->number('risk.medium_min_confidence', 'Confianca minima para risco medio', 'Confianca minima para classificar risco medio.', 60),
        ];
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    private function number(string $key, string $name, string $description, float $value): array
    {
        return $this->rule($key, $name, $description, 'number', $value);
    }

    private function integer(string $key, string $name, string $description, int $value): array
    {
        return $this->rule($key, $name, $description, 'integer', $value);
    }

    private function boolean(string $key, string $name, string $description, bool $value): array
    {
        return $this->rule($key, $name, $description, 'boolean', $value);
    }

    private function rule(string $key, string $name, string $description, string $type, mixed $value): array
    {
        return compact('key', 'name', 'description') + [
            'weight' => is_numeric($value) ? (float) $value : 1.0,
            'is_active' => true,
            'config' => [
                'type' => $type,
                'value' => $value,
                'default' => $value,
            ],
        ];
    }
}
