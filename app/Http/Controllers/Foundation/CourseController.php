<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function show(Request $request, Course $course): JsonResponse
    {
        $this->authorize('view', $course);

        return response()->json([
            'id' => $course->ulid,
            'name' => $course->name,
            'tenant' => $course->tenant_id,
        ]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->authorize('update', $course);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $course->update($validated);

        return response()->json([
            'id' => $course->ulid,
            'name' => $course->name,
        ]);
    }
}
