<?php

namespace App\Services\FootballAnalysis;

class MarketSuggestionService
{
    public function __construct(
        private readonly ConfidenceScoreService $confidenceScoreService,
        private readonly RiskClassifier $riskClassifier,
        private readonly AnalysisRuleResolver $rules,
    ) {}

    public function suggest(array $homeForm, array $awayForm, array $sampleWarnings = []): array
    {
        $minimumSample = min($homeForm['sample_size'], $awayForm['sample_size']);
        $home = $homeForm['last_10'];
        $away = $awayForm['last_10'];
        $homeAtHome = $homeForm['home'];
        $awayAway = $awayForm['away'];

        $expectedGoals = $home['avg_goals_for'] + $away['avg_goals_for'];
        $combinedConceded = $home['avg_goals_against'] + $away['avg_goals_against'];
        $recentFormWeight = $this->rules->number('global.recent_form_weight');
        $homeAdvantageWeight = $this->rules->number('global.home_advantage_weight');
        $attackWeight = $this->rules->number('global.attack_weight');
        $defenseWeight = $this->rules->number('global.defense_weight');

        $rawSuggestions = [
            [
                'rule_key' => 'market.over_1_5',
                'market' => 'Over 1.5 Goals',
                'selection' => 'Over 1.5',
                'score' => $this->weighted([
                    [$home['over_1_5_rate'], 0.22 * $recentFormWeight],
                    [$away['over_1_5_rate'], 0.22 * $recentFormWeight],
                    [$this->goalsScore($expectedGoals, 2.2), 0.2 * $attackWeight],
                    [$this->goalsScore($combinedConceded, 2.0), 0.16 * $defenseWeight],
                    [($home['scored_at_least_one_rate'] + $away['scored_at_least_one_rate']) / 2, 0.1 * $attackWeight],
                    [($home['conceded_at_least_one_rate'] + $away['conceded_at_least_one_rate']) / 2, 0.1 * $defenseWeight],
                ]),
                'reasons' => [
                    "{$homeForm['team_name']} teve over 1.5 em {$home['over_1_5_rate']}% dos ultimos jogos",
                    "{$awayForm['team_name']} teve over 1.5 em {$away['over_1_5_rate']}% dos ultimos jogos",
                    'Media combinada de gols marcados: '.round($expectedGoals, 2),
                ],
            ],
            [
                'rule_key' => 'market.over_2_5',
                'market' => 'Over 2.5 Goals',
                'selection' => 'Over 2.5',
                'score' => $this->weighted([
                    [$home['over_2_5_rate'], 0.25 * $recentFormWeight],
                    [$away['over_2_5_rate'], 0.25 * $recentFormWeight],
                    [$this->goalsScore($expectedGoals, 3.0), 0.25 * $attackWeight],
                    [($home['both_teams_score_rate'] + $away['both_teams_score_rate']) / 2, 0.25 * $recentFormWeight],
                ]),
                'reasons' => [
                    "{$homeForm['team_name']} teve over 2.5 em {$home['over_2_5_rate']}% dos ultimos jogos",
                    "{$awayForm['team_name']} teve over 2.5 em {$away['over_2_5_rate']}% dos ultimos jogos",
                    'Ambas marcam aparece com frequencia media de '.round(($home['both_teams_score_rate'] + $away['both_teams_score_rate']) / 2, 2).'%',
                ],
            ],
            [
                'rule_key' => 'market.btts',
                'market' => 'Both Teams To Score',
                'selection' => 'Yes',
                'score' => $this->weighted([
                    [$home['scored_at_least_one_rate'], 0.2 * $attackWeight],
                    [$away['scored_at_least_one_rate'], 0.2 * $attackWeight],
                    [$home['conceded_at_least_one_rate'], 0.18 * $defenseWeight],
                    [$away['conceded_at_least_one_rate'], 0.18 * $defenseWeight],
                    [($home['both_teams_score_rate'] + $away['both_teams_score_rate']) / 2, 0.24 * $recentFormWeight],
                ]),
                'reasons' => [
                    "{$homeForm['team_name']} marcou em {$home['scored_at_least_one_rate']}% dos ultimos jogos",
                    "{$awayForm['team_name']} marcou em {$away['scored_at_least_one_rate']}% dos ultimos jogos",
                    "{$homeForm['team_name']} sofreu gol em {$home['conceded_at_least_one_rate']}% dos ultimos jogos",
                ],
            ],
            [
                'rule_key' => 'market.home_double_chance',
                'market' => 'Home Double Chance',
                'selection' => "{$homeForm['team_name']} ou Empate",
                'score' => $this->weighted([
                    [$home['non_loss_rate'], 0.25 * $recentFormWeight],
                    [$homeAtHome['non_loss_rate'], 0.25 * $homeAdvantageWeight],
                    [$awayAway['loss_rate'], 0.2 * $homeAdvantageWeight],
                    [$this->goalDiffScore($home['goal_difference_avg'] - $away['goal_difference_avg']), 0.3 * $recentFormWeight],
                ]),
                'reasons' => [
                    "{$homeForm['team_name']} nao perdeu em {$home['non_loss_rate']}% dos ultimos jogos",
                    "Como mandante, {$homeForm['team_name']} nao perdeu em {$homeAtHome['non_loss_rate']}%",
                    "{$awayForm['team_name']} perdeu {$awayAway['loss_rate']}% dos jogos fora",
                ],
            ],
            [
                'rule_key' => 'market.away_double_chance',
                'market' => 'Away Double Chance',
                'selection' => "{$awayForm['team_name']} ou Empate",
                'score' => $this->weighted([
                    [$away['non_loss_rate'], 0.25 * $recentFormWeight],
                    [$awayAway['non_loss_rate'], 0.25 * $homeAdvantageWeight],
                    [$homeAtHome['loss_rate'], 0.2 * $homeAdvantageWeight],
                    [$this->goalDiffScore($away['goal_difference_avg'] - $home['goal_difference_avg']), 0.3 * $recentFormWeight],
                ]),
                'reasons' => [
                    "{$awayForm['team_name']} nao perdeu em {$away['non_loss_rate']}% dos ultimos jogos",
                    "Fora de casa, {$awayForm['team_name']} nao perdeu em {$awayAway['non_loss_rate']}%",
                    "{$homeForm['team_name']} perdeu {$homeAtHome['loss_rate']}% dos jogos como mandante",
                ],
            ],
            [
                'rule_key' => 'market.home_win_lean',
                'market' => 'Home Win Lean',
                'selection' => "{$homeForm['team_name']} vence",
                'score' => $this->weighted([
                    [$home['win_rate'], 0.25 * $recentFormWeight],
                    [$homeAtHome['win_rate'], 0.25 * $homeAdvantageWeight],
                    [$this->goalDiffScore($home['goal_difference_avg'] - $away['goal_difference_avg']), 0.25 * $recentFormWeight],
                    [$this->attackDefenseScore($home['avg_goals_for'], $away['avg_goals_against']), 0.25 * (($attackWeight + $defenseWeight) / 2)],
                ]),
                'reasons' => [
                    "{$homeForm['team_name']} venceu {$home['win_rate']}% dos ultimos jogos",
                    "Em casa, venceu {$homeAtHome['win_rate']}%",
                    'Comparacao ataque/defesa favorece o mandante em '.round($this->attackDefenseScore($home['avg_goals_for'], $away['avg_goals_against']), 2).' pontos',
                ],
            ],
            [
                'rule_key' => 'market.away_win_lean',
                'market' => 'Away Win Lean',
                'selection' => "{$awayForm['team_name']} vence",
                'score' => $this->weighted([
                    [$away['win_rate'], 0.25 * $recentFormWeight],
                    [$awayAway['win_rate'], 0.25 * $homeAdvantageWeight],
                    [$this->goalDiffScore($away['goal_difference_avg'] - $home['goal_difference_avg']), 0.25 * $recentFormWeight],
                    [$this->attackDefenseScore($away['avg_goals_for'], $home['avg_goals_against']), 0.25 * (($attackWeight + $defenseWeight) / 2)],
                ]),
                'reasons' => [
                    "{$awayForm['team_name']} venceu {$away['win_rate']}% dos ultimos jogos",
                    "Fora de casa, venceu {$awayAway['win_rate']}%",
                    'Comparacao ataque/defesa favorece o visitante em '.round($this->attackDefenseScore($away['avg_goals_for'], $home['avg_goals_against']), 2).' pontos',
                ],
            ],
        ];

        $suggestions = collect($rawSuggestions)
            ->filter(fn (array $suggestion) => $this->rules->boolean($suggestion['rule_key'].'.enabled'))
            ->map(function (array $suggestion) use ($minimumSample, $sampleWarnings) {
                $score = round(max(0, min(100, $suggestion['score'] * $this->rules->number($suggestion['rule_key'].'.weight'))), 2);
                $confidence = $this->confidenceScoreService->fromScore($score, $minimumSample);

                return [
                    ...collect($suggestion)->except('rule_key')->all(),
                    'score' => $score,
                    'confidence' => $confidence,
                    'confidence_label' => $this->confidenceScoreService->label($confidence),
                    'risk_level' => $this->riskClassifier->classify($confidence, $minimumSample),
                    'reasons' => array_values(array_merge($suggestion['reasons'], $sampleWarnings)),
                ];
            })
            ->sortByDesc('confidence')
            ->values()
            ->all();

        if ($suggestions !== []) {
            return $suggestions;
        }

        return [[
            'market' => 'No Market Enabled',
            'selection' => 'Sem sugestao',
            'score' => 0,
            'confidence' => 0,
            'confidence_label' => 'baixa',
            'risk_level' => 'high',
            'reasons' => ['Nenhum mercado esta ativo nas regras de analise.'],
        ]];
    }

    private function weighted(array $values): float
    {
        return collect($values)->sum(fn (array $item) => $item[0] * $item[1]);
    }

    private function goalsScore(float $goals, float $target): float
    {
        return max(0, min(100, ($goals / $target) * 75));
    }

    private function goalDiffScore(float $difference): float
    {
        return max(0, min(100, 50 + ($difference * 25)));
    }

    private function attackDefenseScore(float $attackAverage, float $opponentConcededAverage): float
    {
        return max(0, min(100, (($attackAverage + $opponentConcededAverage) / 3) * 100));
    }
}
