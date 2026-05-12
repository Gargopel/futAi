<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Services\FootballAnalysis\FootballAnalysisService;
use Illuminate\Http\JsonResponse;

class MatchAnalysisController extends Controller
{
    public function __invoke(int $match, FootballAnalysisService $analysisService): JsonResponse
    {
        $footballMatch = FootballMatch::query()
            ->with(['league:id,name', 'homeTeam:id,name', 'awayTeam:id,name'])
            ->findOrFail($match);

        return response()->json($analysisService->analyze($footballMatch));
    }
}
