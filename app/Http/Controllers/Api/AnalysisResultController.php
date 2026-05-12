<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AnalysisResult::query()
            ->with(['match.league:id,name', 'match.homeTeam:id,name,short_name', 'match.awayTeam:id,name,short_name'])
            ->when($request->filled('market'), fn ($query) => $query->where('suggested_market', $request->string('market')))
            ->when($request->filled('risk_level'), fn ($query) => $query->where('risk_level', $request->string('risk_level')))
            ->when($request->filled('result_status'), fn ($query) => $query->where('result_status', $request->string('result_status')))
            ->when($request->filled('league_id'), fn ($query) => $query->whereHas('match', fn ($matchQuery) => $matchQuery->where('league_id', $request->integer('league_id'))))
            ->when($request->filled('team_id'), function ($query) use ($request) {
                $teamId = $request->integer('team_id');

                $query->whereHas('match', fn ($matchQuery) => $matchQuery
                    ->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId));
            })
            ->when($request->filled('date_from'), fn ($query) => $query->whereHas('match', fn ($matchQuery) => $matchQuery->whereDate('starts_at', '>=', $request->date('date_from'))))
            ->when($request->filled('date_to'), fn ($query) => $query->whereHas('match', fn ($matchQuery) => $matchQuery->whereDate('starts_at', '<=', $request->date('date_to'))))
            ->latest('generated_at');

        return response()->json($query->get()->map(fn (AnalysisResult $analysisResult) => $this->serialize($analysisResult))->values());
    }

    private function serialize(AnalysisResult $analysisResult): array
    {
        $match = $analysisResult->match;

        return [
            'id' => $analysisResult->id,
            'match' => [
                'id' => $match?->id,
                'status' => $match?->status,
            ],
            'league' => $match?->league,
            'home_team' => $match?->homeTeam,
            'away_team' => $match?->awayTeam,
            'starts_at' => $match?->starts_at?->toISOString(),
            'suggested_market' => $analysisResult->suggested_market,
            'suggested_selection' => $analysisResult->suggested_selection,
            'confidence' => $analysisResult->confidence === null ? null : (float) $analysisResult->confidence,
            'risk_level' => $analysisResult->risk_level,
            'result_status' => $analysisResult->result_status ?? 'pending',
            'summary' => $analysisResult->summary,
            'generated_at' => $analysisResult->generated_at?->toISOString(),
            'evaluated_at' => $analysisResult->evaluated_at?->toISOString(),
            'evaluation_reason' => $analysisResult->evaluation_reason,
            'match_score' => $match?->status === 'finished' ? [
                'home_goals' => $match->home_goals,
                'away_goals' => $match->away_goals,
                'label' => "{$match->home_goals} - {$match->away_goals}",
            ] : null,
        ];
    }
}
