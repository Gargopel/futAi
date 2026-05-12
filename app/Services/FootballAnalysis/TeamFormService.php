<?php

namespace App\Services\FootballAnalysis;

use App\Models\FootballMatch;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class TeamFormService
{
    public function calculate(Team $team, ?int $excludeMatchId = null, ?CarbonInterface $before = null): array
    {
        $matches = FootballMatch::query()
            ->where('status', 'finished')
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->where(function ($query) use ($team) {
                $query->where('home_team_id', $team->id)
                    ->orWhere('away_team_id', $team->id);
            })
            ->when($excludeMatchId, fn ($query) => $query->whereKeyNot($excludeMatchId))
            ->when($before, fn ($query) => $query->where('starts_at', '<', $before))
            ->orderByDesc('starts_at')
            ->get();

        $homeMatches = $matches->where('home_team_id', $team->id)->values();
        $awayMatches = $matches->where('away_team_id', $team->id)->values();

        return [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'sample_size' => $matches->count(),
            'last_5' => $this->summarize($matches->take(5), $team),
            'last_10' => $this->summarize($matches->take(10), $team),
            'home' => $this->summarize($homeMatches, $team),
            'away' => $this->summarize($awayMatches, $team),
        ];
    }

    private function summarize(Collection|\Illuminate\Support\Collection $matches, Team $team): array
    {
        $games = $matches->count();
        $wins = 0;
        $draws = 0;
        $losses = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;
        $over15 = 0;
        $over25 = 0;
        $bothTeamsScored = 0;
        $scoredAtLeastOne = 0;
        $concededAtLeastOne = 0;

        foreach ($matches as $match) {
            $isHome = $match->home_team_id === $team->id;
            $for = $isHome ? (int) $match->home_goals : (int) $match->away_goals;
            $against = $isHome ? (int) $match->away_goals : (int) $match->home_goals;
            $totalGoals = (int) $match->home_goals + (int) $match->away_goals;

            $goalsFor += $for;
            $goalsAgainst += $against;
            $wins += $for > $against ? 1 : 0;
            $draws += $for === $against ? 1 : 0;
            $losses += $for < $against ? 1 : 0;
            $over15 += $totalGoals >= 2 ? 1 : 0;
            $over25 += $totalGoals >= 3 ? 1 : 0;
            $bothTeamsScored += ($match->home_goals > 0 && $match->away_goals > 0) ? 1 : 0;
            $scoredAtLeastOne += $for > 0 ? 1 : 0;
            $concededAtLeastOne += $against > 0 ? 1 : 0;
        }

        return [
            'games' => $games,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'goals_for' => $goalsFor,
            'goals_against' => $goalsAgainst,
            'avg_goals_for' => $this->average($goalsFor, $games),
            'avg_goals_against' => $this->average($goalsAgainst, $games),
            'goal_difference_avg' => $this->average($goalsFor - $goalsAgainst, $games),
            'win_rate' => $this->rate($wins, $games),
            'draw_rate' => $this->rate($draws, $games),
            'loss_rate' => $this->rate($losses, $games),
            'non_loss_rate' => $this->rate($wins + $draws, $games),
            'over_1_5_rate' => $this->rate($over15, $games),
            'over_2_5_rate' => $this->rate($over25, $games),
            'both_teams_score_rate' => $this->rate($bothTeamsScored, $games),
            'scored_at_least_one_rate' => $this->rate($scoredAtLeastOne, $games),
            'conceded_at_least_one_rate' => $this->rate($concededAtLeastOne, $games),
        ];
    }

    private function average(int|float $value, int $games): float
    {
        return $games === 0 ? 0.0 : round($value / $games, 2);
    }

    private function rate(int|float $value, int $games): float
    {
        return $games === 0 ? 0.0 : round(($value / $games) * 100, 2);
    }
}
