<?php

namespace App\Services\FootballAnalysis;

class AnalysisNarrativeService
{
    public function summarize(array $selectedSuggestion, array $homeForm, array $awayForm, array $sampleWarnings): string
    {
        $home = $homeForm['last_10'];
        $away = $awayForm['last_10'];
        $warning = $sampleWarnings === []
            ? 'A amostra disponivel e suficiente para uma leitura inicial do MVP.'
            : 'Como a amostra ainda e limitada, o risco foi ajustado com cautela.';

        return sprintf(
            'A analise indica tendencia para %s. %s marcou em %.2f%% dos ultimos jogos e %s marcou em %.2f%%. A media combinada de gols marcados e %.2f, enquanto a media combinada de gols sofridos e %.2f. %s',
            $selectedSuggestion['market'],
            $homeForm['team_name'],
            $home['scored_at_least_one_rate'],
            $awayForm['team_name'],
            $away['scored_at_least_one_rate'],
            $home['avg_goals_for'] + $away['avg_goals_for'],
            $home['avg_goals_against'] + $away['avg_goals_against'],
            $warning,
        );
    }

    public function factors(array $selectedSuggestion, array $homeForm, array $awayForm, array $sampleWarnings): array
    {
        $baseFactors = array_slice($selectedSuggestion['reasons'], 0, 4);

        $baseFactors[] = "{$homeForm['team_name']} tem saldo medio de {$homeForm['last_10']['goal_difference_avg']} nos ultimos jogos";
        $baseFactors[] = "{$awayForm['team_name']} tem saldo medio de {$awayForm['last_10']['goal_difference_avg']} nos ultimos jogos";

        return array_values(array_unique(array_merge($baseFactors, $sampleWarnings)));
    }
}
