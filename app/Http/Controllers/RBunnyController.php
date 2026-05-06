<?php

namespace App\Http\Controllers;

use App\Services\RBunnyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RBunnyController extends Controller
{
    public function __construct(private RBunnyService $bunny) {}

    public function status(Request $request): JsonResponse
    {
        $page = $request->query('page');
        return response()->json($this->bunny->getStatus($page));
    }

    public function dismiss(Request $request): JsonResponse
    {
        $request->validate(['key' => ['required', 'string', 'max:100']]);
        $this->bunny->dismiss($request->input('key'));
        return response()->json(['ok' => true]);
    }

    public function snooze(Request $request): JsonResponse
    {
        $request->validate(['key' => ['required', 'string', 'max:100'], 'hours' => ['nullable', 'integer', 'min:1', 'max:168']]);
        $hours = (int) $request->input('hours', 24);
        $this->bunny->snooze($request->input('key'), $hours);
        return response()->json(['ok' => true]);
    }

    public function handoff(Request $request): JsonResponse
    {
        $issue = $request->input('issue');
        return response()->json($this->bunny->handoff($issue));
    }

    public function preferences(): JsonResponse
    {
        return response()->json($this->bunny->getPreferences());
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'show_proactive_tips'    => ['sometimes', 'boolean'],
            'show_onboarding_tips'   => ['sometimes', 'boolean'],
            'show_task_reminders'    => ['sometimes', 'boolean'],
            'show_message_reminders' => ['sometimes', 'boolean'],
            'collapsed_by_default'   => ['sometimes', 'boolean'],
        ]);
        return response()->json($this->bunny->updatePreferences($data));
    }
}
