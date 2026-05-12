<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Services\FootballAnalysis\AnalysisEvaluationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $month = $request->string('month')->toString();

        return response()->json(
            FootballMatch::query()
                ->with(['league:id,name,season', 'homeTeam:id,name,short_name', 'awayTeam:id,name,short_name'])
                ->when($request->filled('league_id'), fn ($query) => $query->where('league_id', $request->integer('league_id')))
                ->when($request->filled('team_id'), function ($query) use ($request) {
                    $teamId = $request->integer('team_id');

                    $query->where(fn ($matchQuery) => $matchQuery
                        ->where('home_team_id', $teamId)
                        ->orWhere('away_team_id', $teamId));
                })
                ->when($request->string('bettable')->toString() === '1', fn ($query) => $query
                    ->where('status', 'scheduled')
                    ->where('starts_at', '>=', now()->subHours(3)))
                ->when(preg_match('/^\d{4}-\d{2}$/', $month) === 1, function ($query) use ($month) {
                    $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();

                    $query->whereBetween('starts_at', [$start, $start->endOfMonth()]);
                })
                ->when(
                    $request->string('sort')->toString() === 'starts_at',
                    fn ($query) => $query->orderBy('starts_at'),
                    fn ($query) => $query->orderByDesc('starts_at'),
                )
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'league_id' => ['required', 'exists:leagues,id'],
            'home_team_id' => ['required', 'exists:teams,id', 'different:away_team_id'],
            'away_team_id' => ['required', 'exists:teams,id'],
            'starts_at' => ['required', 'date'],
            'status' => ['required', Rule::in(['scheduled', 'finished', 'cancelled'])],
            'home_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['status'] !== 'finished') {
            $data['home_goals'] = null;
            $data['away_goals'] = null;
        }

        $match = FootballMatch::create($data);

        return response()->json($match->load(['league', 'homeTeam', 'awayTeam']), 201);
    }

    public function show(int $match): JsonResponse
    {
        $footballMatch = FootballMatch::query()
            ->with([
                'league',
                'homeTeam',
                'awayTeam',
                'teamMatchStats.team',
                'predictions',
                'analysisResults',
            ])
            ->findOrFail($match);

        return response()->json($footballMatch);
    }

    public function update(Request $request, int $match, AnalysisEvaluationService $analysisEvaluationService): JsonResponse
    {
        $footballMatch = FootballMatch::query()->findOrFail($match);

        $data = $request->validate([
            'starts_at' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['scheduled', 'finished', 'cancelled'])],
            'home_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'notes' => ['nullable', 'string'],
        ]);

        $footballMatch->fill($data);

        if (($data['status'] ?? $footballMatch->status) !== 'finished') {
            $footballMatch->home_goals = null;
            $footballMatch->away_goals = null;
        }

        $footballMatch->save();

        if ($footballMatch->status === 'finished' && $footballMatch->home_goals !== null && $footballMatch->away_goals !== null) {
            $analysisEvaluationService->evaluateMatch($footballMatch);
        }

        return response()->json($footballMatch->load(['league', 'homeTeam', 'awayTeam', 'analysisResults']));
    }
}
