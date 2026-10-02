<?php

namespace App\Http\Controllers\Faculty;

use App\Domain\Assessment\Week14BoardDefenseService;
use App\Http\Controllers\Controller;
use App\Models\BoardDefenseAssessment;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardDefenseAssessmentController extends Controller
{
    public function show(
        Request $request,
        SectionSimulationWeek $sectionSimulationWeek,
        TeamSimulation $teamSimulation,
        Week14BoardDefenseService $service,
    ): JsonResponse {
        return response()->json($service->facultyView($request->user(), $sectionSimulationWeek, $teamSimulation));
    }

    public function save(
        Request $request,
        SectionSimulationWeek $sectionSimulationWeek,
        TeamSimulation $teamSimulation,
        Week14BoardDefenseService $service,
    ): JsonResponse {
        $payload = $request->validate([
            'dimensions' => ['array'],
            'dimensions.*.faculty_evaluation' => ['nullable', 'string'],
            'dimensions.*.comments' => ['nullable', 'string'],
            'reasoning_outcome_tier' => ['nullable', 'string'],
            'faculty_private_notes' => ['nullable', 'string'],
            'feedback_body' => ['nullable', 'string'],
            'complete' => ['boolean'],
        ]);

        $assessment = $service->saveAssessment(
            $request->user(),
            $sectionSimulationWeek,
            $teamSimulation,
            $payload['dimensions'] ?? [],
            $payload['reasoning_outcome_tier'] ?? null,
            $payload['faculty_private_notes'] ?? null,
            $payload['feedback_body'] ?? null,
            (bool) ($payload['complete'] ?? false),
        );

        return response()->json(['assessment' => $assessment->only(['ulid', 'status', 'reasoning_outcome_tier'])]);
    }

    public function publish(Request $request, BoardDefenseAssessment $boardDefenseAssessment, Week14BoardDefenseService $service): JsonResponse
    {
        $assessment = $service->publishFeedback($request->user(), $boardDefenseAssessment);

        return response()->json(['assessment' => $assessment->only(['ulid', 'status', 'reasoning_outcome_tier'])]);
    }
}
