<?php

namespace App\Http\Controllers;

use App\Models\RequestForm;
use App\Services\RequestFormSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicRequestFormController extends Controller
{
    public function show(string $token): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $form = RequestForm::where('public_token', $token)
            ->whereNull('deleted_at')
            ->with(['fields', 'recipientOptions'])
            ->first();

        if (!$form || !$form->isPublished()) {
            return view('public.request-form-unavailable');
        }

        return view('public.request-form', compact('form'));
    }

    public function submit(Request $request, string $token, RequestFormSubmissionService $service): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $form = RequestForm::where('public_token', $token)
            ->whereNull('deleted_at')
            ->with(['fields', 'recipientOptions'])
            ->first();

        if (!$form || !$form->isPublished()) {
            return view('public.request-form-unavailable');
        }

        // Rate limiting: 5 submissions per IP per form per 5 minutes
        $limiterKey = 'form-submit:' . $token . ':' . sha1($request->ip());
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            return back()->withErrors(['general' => 'Too many submissions. Please wait a few minutes before trying again.']);
        }
        RateLimiter::hit($limiterKey, 300);

        // Honeypot
        if ($request->filled('_hp_name')) {
            // Silently succeed — likely a bot
            return view('public.request-form-success', compact('form'));
        }

        // Build validation rules dynamically from form fields
        $rules    = ['_hp_name' => 'nullable|max:0'];
        $messages = [];

        foreach ($form->fields as $field) {
            $key    = 'field_' . $field->field_key;
            $fieldRules = [];

            if ($field->is_required) {
                $fieldRules[] = $field->field_type === 'multi_select' ? 'required|array|min:1' : 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if ($field->field_type === 'email') $fieldRules[] = 'email';
            if ($field->field_type === 'number') $fieldRules[] = 'numeric';
            if ($field->field_type === 'textarea') $fieldRules[] = 'string|max:10000';
            if ($field->field_type === 'text')     $fieldRules[] = 'string|max:1000';

            $rules[$key] = implode('|', $fieldRules);
            $messages[$key . '.required'] = $field->label . ' is required.';
        }

        // Request To field — only allow IDs from this form's published recipient options
        $allowedIds = $form->recipientOptions->pluck('id')->map(fn($id) => (string) $id)->all();
        $rules['request_to']   = 'required|array|min:1|max:' . $form->max_recipients;
        $rules['request_to.*'] = ['required', 'uuid', Rule::in($allowedIds)];

        $validated = $request->validate($rules, $messages);

        // Extract standard fields
        $mapped = [
            'name'         => $validated['field_name'] ?? '',
            'email'        => $validated['field_email'] ?? '',
            'request_for'  => $validated['field_request_for'] ?? null,
            'notes'        => $validated['field_notes'] ?? null,
            'request_to'   => $request->input('request_to', []),
        ];
        foreach ($validated as $k => $v) $mapped[$k] = $v;

        $ipHash = hash('sha256', $request->ip());
        $uaHash = hash('sha256', $request->userAgent() ?? '');

        $submission = $service->process($form, $mapped, $ipHash, $uaHash);

        return view('public.request-form-success', compact('form', 'submission'));
    }
}
