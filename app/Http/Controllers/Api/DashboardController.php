<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisResult;
use App\Models\FootballMatch;
use App\Models\League;
use App\Models\Prediction;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $withTeams = ['league:id,name', 'homeTeam:id,name,short_name', 'awayTeam:id,name,short_name'];

        return response()->json([
            'totals' => [
                'leagues' => League::count(),
                'teams' => Team::count(),
                'matches' => FootballMatch::count(),
                'analysis_predictions' => AnalysisResult::count() + Prediction::count(),
            ],
            'upcoming_matches' => FootballMatch::query()
                ->with($withTeams)
                ->where('status', 'scheduled')
                ->orderBy('starts_at')
                ->limit(5)
                ->get(),
            'latest_matches' => FootballMatch::query()
                ->with($withTeams)
                ->orderByDesc('starts_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
