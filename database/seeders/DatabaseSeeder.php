<?php

namespace Database\Seeders;

use App\Models\AnalysisRule;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\TeamMatchStat;
use App\Services\FootballAnalysis\AnalysisRuleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $brasileirao = League::create(['name' => 'Brasileirao Serie A', 'country' => 'Brasil', 'season' => '2026']);
        $copaDoBrasil = League::create(['name' => 'Copa do Brasil', 'country' => 'Brasil', 'season' => '2026']);
        League::create(['name' => 'Premier League', 'country' => 'Inglaterra', 'season' => '2026/2027']);

        $teams = collect([
            ['name' => 'Flamengo', 'short_name' => 'FLA'],
            ['name' => 'Palmeiras', 'short_name' => 'PAL'],
            ['name' => 'Gremio', 'short_name' => 'GRE'],
            ['name' => 'Internacional', 'short_name' => 'INT'],
            ['name' => 'Sao Paulo', 'short_name' => 'SAO'],
            ['name' => 'Corinthians', 'short_name' => 'COR'],
        ])->mapWithKeys(fn (array $team) => [
            $team['name'] => Team::create([
                ...$team,
                'league_id' => $brasileirao->id,
                'country' => 'Brasil',
            ]),
        ]);

        $finishedFixtures = [
            ['Flamengo', 'Corinthians', 3, 1],
            ['Palmeiras', 'Sao Paulo', 2, 0],
            ['Gremio', 'Internacional', 1, 1],
            ['Flamengo', 'Gremio', 4, 2],
            ['Corinthians', 'Palmeiras', 0, 2],
            ['Sao Paulo', 'Internacional', 1, 0],
            ['Palmeiras', 'Flamengo', 2, 2],
            ['Internacional', 'Corinthians', 2, 1],
            ['Gremio', 'Sao Paulo', 0, 0],
            ['Flamengo', 'Internacional', 3, 0],
            ['Corinthians', 'Gremio', 1, 2],
            ['Sao Paulo', 'Palmeiras', 0, 1],
            ['Flamengo', 'Sao Paulo', 2, 1],
            ['Palmeiras', 'Gremio', 1, 0],
            ['Internacional', 'Flamengo', 1, 3],
            ['Corinthians', 'Sao Paulo', 0, 0],
            ['Gremio', 'Flamengo', 2, 3],
            ['Palmeiras', 'Internacional', 3, 1],
            ['Sao Paulo', 'Gremio', 1, 1],
            ['Internacional', 'Palmeiras', 0, 2],
            ['Flamengo', 'Palmeiras', 2, 1],
            ['Corinthians', 'Internacional', 1, 1],
            ['Gremio', 'Corinthians', 2, 0],
            ['Sao Paulo', 'Flamengo', 0, 2],
            ['Palmeiras', 'Corinthians', 2, 0],
            ['Internacional', 'Gremio', 1, 0],
            ['Flamengo', 'Corinthians', 3, 0],
            ['Sao Paulo', 'Internacional', 0, 0],
            ['Gremio', 'Palmeiras', 1, 3],
            ['Corinthians', 'Flamengo', 1, 4],
        ];

        $baseDate = CarbonImmutable::now()->subDays(count($finishedFixtures) + 10);
        $finishedMatches = collect($finishedFixtures)->map(function (array $fixture, int $index) use ($teams, $brasileirao, $copaDoBrasil, $baseDate) {
            [$home, $away, $homeGoals, $awayGoals] = $fixture;

            return $this->createMatchWithStats([
                'league_id' => $index % 6 === 0 ? $copaDoBrasil->id : $brasileirao->id,
                'home_team_id' => $teams[$home]->id,
                'away_team_id' => $teams[$away]->id,
                'starts_at' => $baseDate->addDays($index)->setTime(19 + ($index % 3), 0),
                'status' => 'finished',
                'home_goals' => $homeGoals,
                'away_goals' => $awayGoals,
                'notes' => 'Partida historica de exemplo para o motor estatistico.',
            ]);
        });

        collect([
            ['Flamengo', 'Internacional', $brasileirao, 3],
            ['Palmeiras', 'Corinthians', $brasileirao, 5],
            ['Sao Paulo', 'Gremio', $copaDoBrasil, 7],
            ['Gremio', 'Flamengo', $brasileirao, 10],
            ['Internacional', 'Palmeiras', $copaDoBrasil, 13],
        ])->each(function (array $fixture) use ($teams) {
            [$home, $away, $league, $daysAhead] = $fixture;

            FootballMatch::create([
                'league_id' => $league->id,
                'home_team_id' => $teams[$home]->id,
                'away_team_id' => $teams[$away]->id,
                'starts_at' => CarbonImmutable::now()->addDays($daysAhead)->setTime(20, 30),
                'status' => 'scheduled',
            ]);
        });

        Prediction::create([
            'match_id' => $finishedMatches->first()->id,
            'market' => 'Over 1.5 Goals',
            'selection' => 'Over 1.5',
            'confidence' => 72.5,
            'risk_level' => 'medium',
            'status' => 'won',
            'reasoning' => 'Previsao demonstrativa mantida para validar a estrutura de predictions.',
        ]);

        AnalysisRule::create([
            'name' => 'Forma recente legada',
            'key' => 'recent_form_v1',
            'description' => 'Regra legada mantida apenas como exemplo historico.',
            'weight' => 1,
            'is_active' => false,
            'config' => ['windows' => [5, 10]],
        ]);

        $this->seedAnalysisRules(app(AnalysisRuleCatalog::class));
    }

    private function seedAnalysisRules(AnalysisRuleCatalog $catalog): void
    {
        foreach ($catalog->all() as $rule) {
            AnalysisRule::updateOrCreate(
                ['key' => $rule['key']],
                [
                    'name' => $rule['name'],
                    'description' => $rule['description'],
                    'weight' => $rule['weight'],
                    'is_active' => $rule['is_active'],
                    'config' => $rule['config'],
                ],
            );
        }
    }

    private function createMatchWithStats(array $data): FootballMatch
    {
        $match = FootballMatch::create($data);

        $homeShots = 7 + ((int) $match->home_goals * 4);
        $awayShots = 6 + ((int) $match->away_goals * 4);
        $homePossession = min(68, max(38, 50 + (((int) $match->home_goals - (int) $match->away_goals) * 6)));

        TeamMatchStat::create([
            'match_id' => $match->id,
            'team_id' => $match->home_team_id,
            'shots' => $homeShots,
            'shots_on_target' => min($homeShots, 2 + ((int) $match->home_goals * 2)),
            'corners' => 3 + ((int) $match->home_goals),
            'yellow_cards' => 1 + (((int) $match->away_goals) % 3),
            'red_cards' => 0,
            'possession' => $homePossession,
            'expected_goals' => round(0.35 + ((int) $match->home_goals * 0.62), 2),
        ]);

        TeamMatchStat::create([
            'match_id' => $match->id,
            'team_id' => $match->away_team_id,
            'shots' => $awayShots,
            'shots_on_target' => min($awayShots, 2 + ((int) $match->away_goals * 2)),
            'corners' => 2 + ((int) $match->away_goals),
            'yellow_cards' => 1 + (((int) $match->home_goals) % 3),
            'red_cards' => 0,
            'possession' => 100 - $homePossession,
            'expected_goals' => round(0.3 + ((int) $match->away_goals * 0.58), 2),
        ]);

        return $match;
    }
}
