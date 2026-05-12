<?php

namespace Tests\Feature;

use App\Models\AnalysisResult;
use App\Models\AnalysisRule;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Team;
use App\Services\FootballAnalysis\AnalysisEvaluationService;
use App\Services\FootballAnalysis\AnalysisRuleResolver;
use App\Services\FootballAnalysis\TeamFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FutiaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_leagues_can_be_listed_and_created(): void
    {
        League::factory()->create(['name' => 'Brasileirao Serie A']);

        $this->getJson('/api/leagues')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Brasileirao Serie A']);

        $this->postJson('/api/leagues', [
            'name' => 'Copa do Brasil',
            'country' => 'Brasil',
            'season' => '2026',
        ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Copa do Brasil']);

        $this->assertDatabaseHas('leagues', ['name' => 'Copa do Brasil']);
    }

    public function test_teams_can_be_listed_and_created(): void
    {
        $league = League::factory()->create();

        $this->postJson('/api/teams', [
            'league_id' => $league->id,
            'name' => 'Flamengo',
            'short_name' => 'FLA',
            'country' => 'Brasil',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Flamengo')
            ->assertJsonPath('league.id', $league->id);

        $this->getJson('/api/teams')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Flamengo']);
    }

    public function test_matches_can_be_listed_created_and_viewed(): void
    {
        $league = League::factory()->create();
        $homeTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Palmeiras']);
        $awayTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Corinthians']);

        $matchId = $this->postJson('/api/matches', [
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'starts_at' => '2026-06-15 20:00:00',
            'status' => 'finished',
            'home_goals' => 2,
            'away_goals' => 0,
        ])
            ->assertCreated()
            ->assertJsonPath('home_team.name', 'Palmeiras')
            ->json('id');

        $this->getJson('/api/matches')
            ->assertOk()
            ->assertJsonFragment(['status' => 'finished']);

        $this->getJson("/api/matches/{$matchId}")
            ->assertOk()
            ->assertJsonPath('away_team.name', 'Corinthians')
            ->assertJsonPath('home_goals', 2);
    }

    public function test_match_analysis_endpoint_returns_real_payload_and_persists_result(): void
    {
        [$league, $homeTeam, $awayTeam] = $this->createAnalysisBase();

        $futureMatch = FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'scheduled',
            'home_goals' => null,
            'away_goals' => null,
        ]);

        $this->getJson("/api/matches/{$futureMatch->id}/analysis")
            ->assertOk()
            ->assertJsonPath('match_id', $futureMatch->id)
            ->assertJsonStructure([
                'match_id',
                'analysis_result_id',
                'league',
                'home_team',
                'away_team',
                'suggested_market',
                'suggested_selection',
                'confidence',
                'risk_level',
                'summary',
                'factors',
                'metrics' => ['home_team_form', 'away_team_form'],
                'suggestions' => [
                    '*' => ['market', 'selection', 'score', 'confidence', 'risk_level', 'reasons'],
                ],
            ]);

        $this->assertDatabaseHas('analysis_results', [
            'match_id' => $futureMatch->id,
        ]);
    }

    public function test_analysis_updates_latest_result_instead_of_polluting_records(): void
    {
        [$league, $homeTeam, $awayTeam] = $this->createAnalysisBase();

        $futureMatch = FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'scheduled',
        ]);

        $firstId = $this->getJson("/api/matches/{$futureMatch->id}/analysis")->json('analysis_result_id');
        $secondId = $this->getJson("/api/matches/{$futureMatch->id}/analysis")->json('analysis_result_id');

        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, AnalysisResult::where('match_id', $futureMatch->id)->count());
    }

    public function test_analysis_does_not_break_with_small_sample(): void
    {
        $league = League::factory()->create();
        $homeTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Time Novo A']);
        $awayTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Time Novo B']);
        $futureMatch = FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'scheduled',
        ]);

        $this->getJson("/api/matches/{$futureMatch->id}/analysis")
            ->assertOk()
            ->assertJsonPath('risk_level', 'high')
            ->assertJsonCount(3, 'sample_warnings');
    }

    public function test_team_form_calculation_handles_home_and_away_goals_correctly(): void
    {
        $league = League::factory()->create();
        $team = Team::factory()->create(['league_id' => $league->id]);
        $opponent = Team::factory()->create(['league_id' => $league->id]);

        FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $team->id,
            'away_team_id' => $opponent->id,
            'status' => 'finished',
            'home_goals' => 3,
            'away_goals' => 1,
            'starts_at' => now()->subDays(2),
        ]);

        FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $opponent->id,
            'away_team_id' => $team->id,
            'status' => 'finished',
            'home_goals' => 2,
            'away_goals' => 1,
            'starts_at' => now()->subDay(),
        ]);

        $form = app(TeamFormService::class)->calculate($team);

        $this->assertSame(2, $form['last_10']['games']);
        $this->assertSame(4, $form['last_10']['goals_for']);
        $this->assertSame(3, $form['last_10']['goals_against']);
        $this->assertSame(1, $form['last_10']['wins']);
        $this->assertSame(1, $form['last_10']['losses']);
    }

    public function test_analysis_evaluation_service_evaluates_over_1_5_correctly(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Over 1.5 Goals', 1, 1);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('won', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_service_evaluates_over_2_5_correctly(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Over 2.5 Goals', 1, 1);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('lost', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_service_evaluates_both_teams_to_score_correctly(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Both Teams To Score', 2, 1);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('won', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_service_evaluates_home_double_chance_correctly(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Home Double Chance', 1, 1);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('won', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_service_evaluates_away_double_chance_correctly(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Away Double Chance', 1, 2);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('won', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_service_evaluates_home_win_lean_correctly(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Home Win Lean', 2, 1);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('won', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_service_evaluates_away_win_lean_correctly(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Away Win Lean', 2, 1);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('lost', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_keeps_pending_if_match_is_not_finished(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Over 1.5 Goals', null, null, 'scheduled');

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('pending', $analysis->refresh()->result_status);
    }

    public function test_analysis_evaluation_returns_unknown_for_unknown_market(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Corners Over 8.5', 2, 1);

        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->assertSame('unknown', $analysis->refresh()->result_status);
    }

    public function test_updating_finished_match_triggers_analysis_evaluation(): void
    {
        $match = FootballMatch::factory()->create([
            'status' => 'scheduled',
            'home_goals' => null,
            'away_goals' => null,
        ]);
        $analysis = AnalysisResult::factory()->create([
            'match_id' => $match->id,
            'suggested_market' => 'Over 1.5 Goals',
            'suggested_selection' => 'Over 1.5',
            'result_status' => 'pending',
        ]);

        $this->patchJson("/api/matches/{$match->id}", [
            'status' => 'finished',
            'home_goals' => 2,
            'away_goals' => 1,
        ])->assertOk();

        $this->assertSame('won', $analysis->refresh()->result_status);
        $this->assertNotNull($analysis->evaluated_at);
    }

    public function test_analysis_results_endpoint_lists_history(): void
    {
        $analysis = $this->createAnalysisForEvaluation('Over 1.5 Goals', 2, 1);
        app(AnalysisEvaluationService::class)->evaluate($analysis);

        $this->getJson('/api/analysis-results?market=Over%201.5%20Goals&result_status=won')
            ->assertOk()
            ->assertJsonFragment(['suggested_market' => 'Over 1.5 Goals'])
            ->assertJsonStructure([
                '*' => ['id', 'match', 'league', 'home_team', 'away_team', 'starts_at', 'suggested_market', 'result_status', 'match_score'],
            ]);
    }

    public function test_analysis_performance_endpoint_returns_metrics(): void
    {
        app(AnalysisEvaluationService::class)->evaluate($this->createAnalysisForEvaluation('Over 1.5 Goals', 2, 1));
        app(AnalysisEvaluationService::class)->evaluate($this->createAnalysisForEvaluation('Over 2.5 Goals', 1, 0));

        $this->getJson('/api/analysis-performance')
            ->assertOk()
            ->assertJsonPath('total_analyses', 2)
            ->assertJsonPath('evaluated_analyses', 2)
            ->assertJsonStructure([
                'win_rate_by_market',
                'win_rate_by_risk_level',
                'win_rate_by_confidence_band',
            ]);
    }

    public function test_evaluate_analyses_command_works(): void
    {
        $this->createAnalysisForEvaluation('Over 1.5 Goals', 2, 1);

        $this->artisan('futia:evaluate-analyses')
            ->expectsOutput('Analises processadas: 1')
            ->assertSuccessful();
    }

    public function test_analysis_rule_resolver_uses_catalog_defaults_without_database_rows(): void
    {
        $this->assertSame(1.0, app(AnalysisRuleResolver::class)->number('global.attack_weight'));
        $this->assertTrue(app(AnalysisRuleResolver::class)->boolean('market.over_1_5.enabled'));
        $this->assertSame(5, app(AnalysisRuleResolver::class)->integer('global.minimum_matches_required'));
    }

    public function test_analysis_rules_endpoint_lists_and_updates_rules(): void
    {
        $this->getJson('/api/analysis-rules')
            ->assertOk()
            ->assertJsonFragment(['key' => 'global.attack_weight']);

        $this->patchJson('/api/analysis-rules/global.attack_weight', ['value' => 1.4])
            ->assertOk()
            ->assertJsonPath('value', 1.4);

        $this->assertDatabaseHas('analysis_rules', ['key' => 'global.attack_weight']);
    }

    public function test_market_rules_can_disable_a_suggestion(): void
    {
        [$league, $homeTeam, $awayTeam] = $this->createAnalysisBase();

        AnalysisRule::updateOrCreate(
            ['key' => 'market.home_win_lean.enabled'],
            [
                'name' => 'Ativar tendencia mandante',
                'description' => 'Teste',
                'weight' => 1,
                'is_active' => true,
                'config' => ['type' => 'boolean', 'value' => false, 'default' => true],
            ],
        );

        $futureMatch = FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'scheduled',
        ]);

        $markets = collect($this->getJson("/api/matches/{$futureMatch->id}/analysis")
            ->assertOk()
            ->json('suggestions'))
            ->pluck('market');

        $this->assertFalse($markets->contains('Home Win Lean'));
    }

    public function test_backtest_endpoint_runs_without_persisting_analysis_results(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $before = AnalysisResult::count();

        $this->postJson('/api/backtests', [
            'date_from' => now()->subDays(45)->toDateString(),
            'date_to' => now()->toDateString(),
        ])
            ->assertOk()
            ->assertJsonStructure([
                'summary' => ['total_matches', 'won', 'lost', 'win_rate', 'average_confidence'],
                'by_market',
                'by_risk_level',
                'by_confidence_band',
                'items',
                'rules',
            ])
            ->assertJsonPath('summary.total_matches', 30);

        $this->assertSame($before, AnalysisResult::count());
    }

    public function test_backtest_uses_only_matches_before_tested_match(): void
    {
        $league = League::factory()->create();
        $homeTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Temporal Casa']);
        $awayTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Temporal Fora']);

        FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'finished',
            'home_goals' => 1,
            'away_goals' => 0,
            'starts_at' => '2026-01-01 12:00:00',
        ]);

        $testedMatch = FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'finished',
            'home_goals' => 2,
            'away_goals' => 1,
            'starts_at' => '2026-01-10 12:00:00',
        ]);

        FootballMatch::factory()->create([
            'league_id' => $league->id,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'status' => 'finished',
            'home_goals' => 8,
            'away_goals' => 7,
            'starts_at' => '2026-01-20 12:00:00',
        ]);

        $item = collect($this->postJson('/api/backtests', [
            'date_from' => '2026-01-10',
            'date_to' => '2026-01-10',
            'league_id' => $league->id,
        ])->assertOk()->json('items'))->firstWhere('match_id', $testedMatch->id);

        $this->assertSame(1, $item['metrics']['home_team_form']['sample_size']);
        $this->assertSame(1, $item['metrics']['away_team_form']['sample_size']);
        $this->assertSame(1, $item['metrics']['home_team_form']['last_10']['goals_for']);
    }

    public function test_backtest_rule_overrides_are_simulated_and_not_saved(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $response = $this->postJson('/api/backtests', [
            'date_from' => now()->subDays(45)->toDateString(),
            'date_to' => now()->toDateString(),
            'rule_overrides' => [
                'market.home_win_lean.enabled' => false,
                'market.over_1_5.weight' => 1.25,
            ],
        ])->assertOk();

        $this->assertFalse(collect($response->json('items'))->pluck('suggested_market')->contains('Home Win Lean'));
        $this->assertTrue((bool) AnalysisRule::where('key', 'market.home_win_lean.enabled')->first()->config['value']);
    }

    public function test_match_import_preview_csv_validates_without_persisting(): void
    {
        $csv = "date,league,home_team,away_team,status,home_goals,away_goals\n2026-05-01 20:00,Brasileirao,Flamengo,Palmeiras,finished,2,1\n";

        $this->postJson('/api/imports/matches', [
            'format' => 'csv',
            'mode' => 'preview',
            'content' => $csv,
        ])
            ->assertOk()
            ->assertJsonPath('summary.valid_rows', 1)
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('rows.0.action', 'create');

        $this->assertDatabaseMissing('football_matches', ['home_goals' => 2, 'away_goals' => 1]);
    }

    public function test_match_import_commit_csv_creates_league_teams_and_match(): void
    {
        $csv = "date,league,home_team,away_team,status,home_goals,away_goals,country,season\n2026-05-01 20:00,Brasileirao,Flamengo,Palmeiras,finished,2,1,Brasil,2026\n";

        $this->postJson('/api/imports/matches', [
            'format' => 'csv',
            'mode' => 'commit',
            'content' => $csv,
        ])
            ->assertOk()
            ->assertJsonPath('summary.created', 1)
            ->assertJsonPath('rows.0.action', 'created');

        $this->assertDatabaseHas('leagues', ['name' => 'Brasileirao']);
        $this->assertDatabaseHas('teams', ['name' => 'Flamengo']);
        $this->assertDatabaseHas('football_matches', ['status' => 'finished', 'home_goals' => 2, 'away_goals' => 1]);
    }

    public function test_match_import_skips_duplicates(): void
    {
        $csv = "date,league,home_team,away_team,status,home_goals,away_goals\n2026-05-01 20:00,Brasileirao,Flamengo,Palmeiras,finished,2,1\n";

        $this->postJson('/api/imports/matches', ['format' => 'csv', 'mode' => 'commit', 'content' => $csv])->assertOk();

        $this->postJson('/api/imports/matches', ['format' => 'csv', 'mode' => 'commit', 'content' => $csv])
            ->assertOk()
            ->assertJsonPath('summary.duplicates', 1)
            ->assertJsonPath('summary.created', 0)
            ->assertJsonPath('rows.0.status', 'duplicate');

        $this->assertSame(1, FootballMatch::count());
    }

    public function test_match_import_accepts_json_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('matches.json', json_encode([
            [
                'date' => '2026-05-02 18:30',
                'league' => 'Copa Local',
                'home_team' => 'Gremio',
                'away_team' => 'Internacional',
                'status' => 'scheduled',
                'home_goals' => null,
                'away_goals' => null,
            ],
        ]));

        $this->post('/api/imports/matches', [
            'format' => 'json',
            'mode' => 'commit',
            'file' => $file,
        ])
            ->assertOk()
            ->assertJsonPath('summary.created', 1);

        $this->assertDatabaseHas('football_matches', ['status' => 'scheduled']);
    }

    public function test_match_import_reports_invalid_rows(): void
    {
        $csv = "date,league,home_team,away_team,status,home_goals,away_goals\ninvalid,Brasileirao,Flamengo,Flamengo,finished,,\n";

        $this->postJson('/api/imports/matches', [
            'format' => 'csv',
            'mode' => 'preview',
            'content' => $csv,
        ])
            ->assertOk()
            ->assertJsonPath('summary.invalid_rows', 1)
            ->assertJsonPath('rows.0.status', 'invalid');
    }

    private function createAnalysisBase(): array
    {
        $league = League::factory()->create();
        $homeTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Mandante Forte']);
        $awayTeam = Team::factory()->create(['league_id' => $league->id, 'name' => 'Visitante Fragil']);

        foreach ([[3, 1], [2, 0], [4, 1], [2, 2], [1, 0]] as $index => [$homeGoals, $awayGoals]) {
            FootballMatch::factory()->create([
                'league_id' => $league->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'status' => 'finished',
                'home_goals' => $homeGoals,
                'away_goals' => $awayGoals,
                'starts_at' => now()->subDays(10 - $index),
            ]);
        }

        return [$league, $homeTeam, $awayTeam];
    }

    private function createAnalysisForEvaluation(string $market, ?int $homeGoals, ?int $awayGoals, string $status = 'finished'): AnalysisResult
    {
        $match = FootballMatch::factory()->create([
            'status' => $status,
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
        ]);

        return AnalysisResult::factory()->create([
            'match_id' => $match->id,
            'suggested_market' => $market,
            'suggested_selection' => $market,
            'confidence' => 70,
            'risk_level' => 'medium',
            'result_status' => 'pending',
            'evaluated_at' => null,
            'evaluation_reason' => null,
        ]);
    }
}
