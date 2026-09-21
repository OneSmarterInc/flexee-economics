<?php

namespace App\Http\Controllers\Foundation;

use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Http\Controllers\Controller;
use App\Models\SectionSimulationWeek;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class SectionSimulationWeekController extends Controller
{
    public function show(SectionSimulationWeek $sectionSimulationWeek): JsonResponse
    {
        $this->authorize('view', $sectionSimulationWeek);

        $sectionSimulationWeek->load(['sectionSimulation.section.course', 'definition']);

        return response()->json([
            'ulid' => $sectionSimulationWeek->ulid,
            'status' => $sectionSimulationWeek->status->value,
            'section_simulation' => $sectionSimulationWeek->sectionSimulation->name,
            'section' => $sectionSimulationWeek->sectionSimulation->section->name,
            'course' => $sectionSimulationWeek->sectionSimulation->section->course->name,
            'week' => [
                'number' => $sectionSimulationWeek->definition->week_number,
                'title' => $sectionSimulationWeek->definition->title,
                'pattern' => $sectionSimulationWeek->definition->pattern,
            ],
        ]);
    }

    public function transition(
        Request $request,
        SectionSimulationWeek $sectionSimulationWeek,
        SimulationLifecycleService $service,
    ): JsonResponse {
        $this->authorize('update', $sectionSimulationWeek);

        $validated = $request->validate([
            'status' => ['required', new Enum(SectionSimulationWeekStatus::class)],
            'closes_at' => ['nullable', 'date'],
        ]);

        $updated = $service->transitionWeek(
            $sectionSimulationWeek,
            SectionSimulationWeekStatus::from($validated['status']),
            $request->user(),
            isset($validated['closes_at']) ? Carbon::parse($validated['closes_at']) : null,
        );

        return response()->json([
            'ulid' => $updated->ulid,
            'status' => $updated->status->value,
        ]);
    }
}
