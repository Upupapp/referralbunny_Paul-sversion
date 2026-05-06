<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboarding) {}

    public function status(): JsonResponse
    {
        return response()->json($this->onboarding->getStatus());
    }

    public function start(): JsonResponse
    {
        return response()->json($this->onboarding->startWalkthrough());
    }

    public function advance(Request $request): JsonResponse
    {
        $step = (int) $request->input('step', 0);
        return response()->json($this->onboarding->advanceWalkthrough($step));
    }

    public function complete(): JsonResponse
    {
        return response()->json($this->onboarding->completeWalkthrough());
    }

    public function skip(): JsonResponse
    {
        return response()->json($this->onboarding->skipWalkthrough());
    }

    public function completeTask(Request $request): JsonResponse
    {
        $request->validate(['task_key' => ['required', 'string', 'max:80']]);
        return response()->json($this->onboarding->completeTask($request->input('task_key')));
    }

    public function dismissTask(Request $request): JsonResponse
    {
        $request->validate(['task_key' => ['required', 'string', 'max:80']]);
        return response()->json($this->onboarding->dismissTask($request->input('task_key')));
    }

    public function snooze(Request $request): JsonResponse
    {
        $hours = (int) $request->input('hours', 24);
        $hours = max(1, min(168, $hours)); // 1h – 7 days
        return response()->json($this->onboarding->snooze($hours));
    }

    public function wakeUp(): JsonResponse
    {
        return response()->json($this->onboarding->clearSnooze());
    }
}
