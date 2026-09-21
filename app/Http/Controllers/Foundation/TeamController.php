<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function show(Request $request, Team $team): JsonResponse
    {
        $this->authorize('view', $team);
        $team->load('section');

        return response()->json([
            'id' => $team->ulid,
            'name' => $team->name,
            'section' => $team->section->name,
        ]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($validated);

        return response()->json([
            'id' => $team->ulid,
            'name' => $team->name,
        ]);
    }
}
