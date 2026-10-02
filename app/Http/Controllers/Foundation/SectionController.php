<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function show(Request $request, Section $section): JsonResponse
    {
        $this->authorize('view', $section);
        $section->load('course');

        return response()->json([
            'id' => $section->ulid,
            'name' => $section->name,
            'course' => $section->course->name,
        ]);
    }

    public function update(Request $request, Section $section): JsonResponse
    {
        $this->authorize('update', $section);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $section->update($validated);

        return response()->json([
            'id' => $section->ulid,
            'name' => $section->name,
        ]);
    }
}
