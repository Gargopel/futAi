<?php

namespace App\Services\Integrations;

use App\Models\ApiIntegration;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FootballDataOrgService
{
    public function importMatches(array $filters): array
    {
        $competition = (string) ($filters['competition'] ?? 'BSA');
        $payload = $this->get("competitions/{$competition}/matches", $this->cleanFilters([
            'season' => $filters['season'] ?? null,
            'dateFrom' => $filters['date_from'] ?? null,
            'dateTo' => $filters['date_to'] ?? null,
            'status' => $filters['status'] ?? null,
        ]));

        $matches = collect($payload['matches'] ?? []);

        $summary = [
            'source' => 'football-data-org',
            'total_rows' => $matches->count(),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $rows = DB::transaction(function () use ($matches, &$summary) {
            return $matches
                ->map(function (array $item) use (&$summary) {
                    $normalized = $this->normalizeMatch($item);

                    if ($normalized === null) {
                        $summary['skipped']++;

                        return [
                            'status' => 'skipped',
                            'action' => 'skip',
                            'errors' => ['Partida sem dados suficientes para importar.'],
                        ];
                    }

                    $match = $this->persistMatch($normalized);
                    $wasRecentlyCreated = $match->wasRecentlyCreated;

                    $summary[$wasRecentlyCreated ? 'created' : 'updated']++;

                    return [
                        'status' => 'valid',
                        'action' => $wasRecentlyCreated ? 'created' : 'updated',
                        'match_id' => $match->id,
                        'data' => [
                            'date' => $match->starts_at?->toDateTimeString(),
                            'league' => $match->league->name,
                            'home_team' => $match->homeTeam->name,
                            'away_team' => $match->awayTeam->name,
                            'status' => $match->status,
                            'home_goals' => $match->home_goals,
                            'away_goals' => $match->away_goals,
                        ],
                    ];
                })
                ->values()
                ->all();
        });

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    public function get(string $endpoint, array $query = []): array
    {
        $key = (string) (ApiIntegration::query()->where('provider', 'football-data-org')->first()?->api_key ?: config('services.football_data_org.key'));

        if ($key === '') {
            throw new RuntimeException('Configure a chave da Football-Data.org na tela Integracoes ou em FOOTBALL_DATA_ORG_KEY no .env antes de importar.');
        }

        $response = Http::baseUrl(rtrim((string) config('services.football_data_org.base_url'), '/'))
            ->acceptJson()
            ->withHeaders(['X-Auth-Token' => $key])
            ->timeout(20)
            ->retry(2, 500)
            ->get(ltrim($endpoint, '/'), $query);

        if ($response->failed()) {
            throw new RuntimeException("Football-Data.org respondeu HTTP {$response->status()}: {$response->body()}");
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function cleanFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function normalizeMatch(array $item): ?array
    {
        $competition = Arr::get($item, 'competition');
        $season = Arr::get($item, 'season');
        $homeTeam = Arr::get($item, 'homeTeam');
        $awayTeam = Arr::get($item, 'awayTeam');
        $startsAt = Arr::get($item, 'utcDate');

        if (! is_array($competition) || ! is_array($homeTeam) || ! is_array($awayTeam) || ! $startsAt) {
            return null;
        }

        if (! Arr::get($homeTeam, 'name') || ! Arr::get($awayTeam, 'name') || ! Arr::get($competition, 'name')) {
            return null;
        }

        $status = $this->mapStatus((string) Arr::get($item, 'status'));
        $homeGoals = Arr::get($item, 'score.fullTime.home');
        $awayGoals = Arr::get($item, 'score.fullTime.away');

        if ($status !== 'finished') {
            $homeGoals = null;
            $awayGoals = null;
        }

        return [
            'league' => [
                'name' => (string) Arr::get($competition, 'name'),
                'country' => Arr::get($item, 'area.name'),
                'season' => $this->seasonLabel($season),
            ],
            'home_team' => [
                'name' => (string) Arr::get($homeTeam, 'name'),
                'short_name' => Arr::get($homeTeam, 'tla') ?: Arr::get($homeTeam, 'shortName'),
                'logo_url' => Arr::get($homeTeam, 'crest'),
            ],
            'away_team' => [
                'name' => (string) Arr::get($awayTeam, 'name'),
                'short_name' => Arr::get($awayTeam, 'tla') ?: Arr::get($awayTeam, 'shortName'),
                'logo_url' => Arr::get($awayTeam, 'crest'),
            ],
            'match' => [
                'starts_at' => CarbonImmutable::parse($startsAt)->timezone(config('app.timezone')),
                'status' => $status,
                'home_goals' => is_numeric($homeGoals) ? (int) $homeGoals : null,
                'away_goals' => is_numeric($awayGoals) ? (int) $awayGoals : null,
                'notes' => 'Importado da Football-Data.org. Match ID: '.Arr::get($item, 'id'),
            ],
        ];
    }

    private function persistMatch(array $data): FootballMatch
    {
        $league = League::firstOrCreate(
            [
                'name' => $data['league']['name'],
                'season' => $data['league']['season'],
            ],
            [
                'country' => $data['league']['country'],
                'is_active' => true,
            ],
        );

        $homeTeam = $this->firstOrCreateTeam($data['home_team'], $league);
        $awayTeam = $this->firstOrCreateTeam($data['away_team'], $league);

        $match = FootballMatch::updateOrCreate(
            [
                'league_id' => $league->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'starts_at' => $data['match']['starts_at'],
            ],
            [
                'status' => $data['match']['status'],
                'home_goals' => $data['match']['home_goals'],
                'away_goals' => $data['match']['away_goals'],
                'notes' => $data['match']['notes'],
            ],
        );

        return $match->load(['league', 'homeTeam', 'awayTeam']);
    }

    private function firstOrCreateTeam(array $data, League $league): Team
    {
        $team = Team::firstOrCreate(
            ['name' => $data['name']],
            [
                'league_id' => $league->id,
                'short_name' => $data['short_name'],
                'country' => $league->country,
                'logo_url' => $data['logo_url'],
                'is_active' => true,
            ],
        );

        $updates = [];

        foreach (['league_id' => $league->id, 'short_name' => $data['short_name'], 'logo_url' => $data['logo_url']] as $field => $value) {
            if ($team->{$field} === null && $value) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $team->update($updates);
        }

        return $team;
    }

    private function seasonLabel(mixed $season): ?string
    {
        $startDate = is_array($season) ? Arr::get($season, 'startDate') : null;

        return $startDate ? CarbonImmutable::parse($startDate)->format('Y') : null;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'FINISHED', 'IN_PLAY', 'PAUSED' => $status === 'FINISHED' ? 'finished' : 'scheduled',
            'CANCELLED', 'SUSPENDED' => 'cancelled',
            default => 'scheduled',
        };
    }
}
