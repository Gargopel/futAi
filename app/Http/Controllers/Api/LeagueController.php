<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            League::query()
                ->withCount(['teams', 'matches'])
                ->latest()
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $league = League::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'season' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json($league, 201);
    }
}
