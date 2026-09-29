<?php

namespace App\Http\Controllers\Student;

use App\Domain\Assessment\Week14BoardDefenseService;
use App\Http\Controllers\Controller;
use App\Models\SectionSimulationWeek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardDefenseController extends Controller
{
    public function show(Request $request, SectionSimulationWeek $sectionSimulationWeek, Week14BoardDefenseService $service): JsonResponse
    {
        return response()->json($service->studentView($request->user(), $sectionSimulationWeek));
    }

    public function draft(Request $request, SectionSimulationWeek $sectionSimulationWeek, Week14BoardDefenseService $service): JsonResponse
    {
        $payload = $this->validatedSubmissionPayload($request);

        $submission = $service->saveSubmissionDraft(
            $request->user(),
            $sectionSimulationWeek,
            $payload['final_synthesis_memo'] ?? '',
            $payload['artifact_references'] ?? [],
        );

        return response()->json(['submission' => $submission->only(['ulid', 'status'])]);
    }

    public function submit(Request $request, SectionSimulationWeek $sectionSimulationWeek, Week14BoardDefenseService $service): JsonResponse
    {
        $payload = $this->validatedSubmissionPayload($request);

        $submission = $service->submitDefense(
            $request->user(),
            $sectionSimulationWeek,
            $payload['final_synthesis_memo'] ?? '',
            $payload['artifact_references'] ?? [],
        );

        return response()->json(['submission' => $submission->only(['ulid', 'status'])]);
    }

    /**
     * @return array{final_synthesis_memo?: string, artifact_references?: list<array{type?: string, label?: string|null, reference?: string}>}
     */
    private function validatedSubmissionPayload(Request $request): array
    {
        return $request->validate([
            'final_synthesis_memo' => ['nullable', 'string'],
            'artifact_references' => ['array'],
            'artifact_references.*.type' => ['required_with:artifact_references', 'string'],
            'artifact_references.*.label' => ['nullable', 'string'],
            'artifact_references.*.reference' => ['required_with:artifact_references', 'string'],
        ]);
    }
}
