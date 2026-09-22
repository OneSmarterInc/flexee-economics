<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Models\SectionSimulation;
use Illuminate\Http\JsonResponse;

class SectionSimulationController extends Controller
{
    public function show(SectionSimulation $sectionSimulation): JsonResponse
    {
        $this->authorize('view', $sectionSimulation);

        $sectionSimulation->load(['section.course', 'simulation', 'variant', 'version', 'weeks.definition']);

        return response()->json([
            'ulid' => $sectionSimulation->ulid,
            'name' => $sectionSimulation->name,
            'section' => $sectionSimulation->section->name,
            'course' => $sectionSimulation->section->course->name,
            'simulation' => $sectionSimulation->simulation->name,
            'variant' => $sectionSimulation->variant->name,
            'version' => $sectionSimulation->version->version,
            'status' => $sectionSimulation->statusValue(),
            'weeks' => $sectionSimulation->weeks
                ->sortBy(fn ($runtimeWeek) => $runtimeWeek->definition->week_number)
                ->map(fn ($runtimeWeek) => [
                    'ulid' => $runtimeWeek->ulid,
                    'week_number' => $runtimeWeek->definition->week_number,
                    'title' => $runtimeWeek->definition->title,
                    'status' => $runtimeWeek->statusValue(),
                ])
                ->values(),
        ]);
    }
}
