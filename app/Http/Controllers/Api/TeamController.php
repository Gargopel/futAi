<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Team::query()
                ->with('league:id,name,season')
                ->latest()
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $team = Team::create($request->validate([
            'league_id' => ['nullable', 'exists:leagues,id'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json($team->load('league:id,name,season'), 201);
    }
}
