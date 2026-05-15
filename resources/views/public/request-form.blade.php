<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $form->title }} — ReferralBunny.ai</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#F0EFFA;min-height:100vh;padding:24px 16px}
.wrap{max-width:600px;margin:0 auto}
.logo{text-align:center;margin-bottom:24px;font-size:15px;font-weight:700;color:#7B61FF}
.card{background:white;border-radius:20px;box-shadow:0 4px 24px rgba(123,97,255,.1);overflow:hidden}
.card-header{background:linear-gradient(135deg,#7B61FF,#5b4cdb);padding:28px 28px 24px;text-align:center}
.card-header h1{color:white;font-size:22px;font-weight:800;margin-bottom:6px}
.card-header p{color:rgba(255,255,255,.8);font-size:14px;line-height:1.5}
.card-body{padding:28px}
.field{margin-bottom:20px}
label.field-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.required-star{color:#ef4444;margin-left:2px}
.helper{font-size:11px;color:#9ca3af;margin-top:4px}
input[type=text],input[type=email],input[type=number],input[type=date],textarea,select{
    width:100%;padding:11px 14px;border:1.5px solid #e5e7eb;border-radius:12px;
    font-size:14px;color:#1E1B4B;background:white;outline:none;transition:border-color .15s;
    font-family:inherit
}
input:focus,textarea:focus,select:focus{border-color:#7B61FF;box-shadow:0 0 0 3px rgba(123,97,255,.1)}
textarea{resize:vertical;min-height:100px}
.checkbox-group{display:flex;flex-direction:column;gap:8px}
.checkbox-label{display:flex;align-items:flex-start;gap:10px;padding:10px 14px;background:#f9fafb;border-radius:10px;cursor:pointer;border:1.5px solid transparent;transition:all .15s}
.checkbox-label:hover{border-color:#c4b5fd;background:#f5f3ff}
.checkbox-label input[type=checkbox],.checkbox-label input[type=radio]{width:16px;height:16px;flex-shrink:0;margin-top:1px;accent-color:#7B61FF;cursor:pointer}
.checkbox-label.checked{border-color:#7B61FF;background:#f5f3ff}
.error{font-size:12px;color:#dc2626;margin-top:4px;padding:6px 10px;background:#fef2f2;border-radius:6px}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;border-radius:14px;font-size:16px;font-weight:700;cursor:pointer;transition:opacity .15s;box-shadow:0 4px 18px rgba(123,97,255,.35)}
.btn:hover{opacity:.9}
.btn:disabled{opacity:.5;cursor:not-allowed}
.honeypot{position:absolute;left:-9999px;opacity:0;pointer-events:none}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <a href="/" style="text-decoration:none;color:#7B61FF">ReferralBunny.ai</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h1>{{ $form->title }}</h1>
            @if($form->description)
            <p>{{ $form->description }}</p>
            @endif
        </div>

        <div class="card-body">
            {{-- Error summary --}}
            @if($errors->any())
            <div style="padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:12px;margin-bottom:20px">
                <p style="font-size:13px;font-weight:700;color:#dc2626;margin-bottom:6px">Please fix the following:</p>
                <ul style="font-size:12px;color:#dc2626;padding-left:16px">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('public.request-form.submit', $form->public_token) }}" id="request-form">
                @csrf

                {{-- Honeypot --}}
                <div class="honeypot" aria-hidden="true">
                    <input type="text" name="_hp_name" tabindex="-1" autocomplete="off">
                </div>

                {{-- Render form fields --}}
                @foreach($form->fields as $field)
                @php $key = 'field_' . $field->field_key; @endphp

                <div class="field">
                    <label class="field-label" for="{{ $key }}">
                        {{ $field->label }}
                        @if($field->is_required)<span class="required-star">*</span>@endif
                    </label>

                    @if($field->field_type === 'text')
                        <input type="text" id="{{ $key }}" name="{{ $key }}"
                               placeholder="{{ $field->placeholder }}"
                               value="{{ old($key) }}"
                               {{ $field->is_required ? 'required' : '' }}>

                    @elseif($field->field_type === 'email')
                        <input type="email" id="{{ $key }}" name="{{ $key }}"
                               placeholder="{{ $field->placeholder }}"
                               value="{{ old($key) }}"
                               {{ $field->is_required ? 'required' : '' }}>

                    @elseif($field->field_type === 'textarea')
                        <textarea id="{{ $key }}" name="{{ $key }}"
                                  placeholder="{{ $field->placeholder }}"
                                  {{ $field->is_required ? 'required' : '' }}>{{ old($key) }}</textarea>

                    @elseif($field->field_type === 'number')
                        <input type="number" id="{{ $key }}" name="{{ $key }}"
                               value="{{ old($key) }}"
                               {{ $field->is_required ? 'required' : '' }}>

                    @elseif($field->field_type === 'date')
                        <input type="date" id="{{ $key }}" name="{{ $key }}"
                               value="{{ old($key) }}"
                               {{ $field->is_required ? 'required' : '' }}>

                    @elseif($field->field_type === 'select')
                        <select id="{{ $key }}" name="{{ $key }}" {{ $field->is_required ? 'required' : '' }}>
                            <option value="">Select an option...</option>
                            @foreach($field->options ?? [] as $opt)
                            <option value="{{ $opt }}" {{ old($key) == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>

                    @elseif($field->field_type === 'radio')
                        <div class="checkbox-group">
                            @foreach($field->options ?? [] as $opt)
                            <label class="checkbox-label {{ old($key) == $opt ? 'checked' : '' }}">
                                <input type="radio" name="{{ $key }}" value="{{ $opt }}"
                                       {{ old($key) == $opt ? 'checked' : '' }}
                                       {{ $field->is_required ? 'required' : '' }}
                                       onchange="this.closest('.checkbox-group').querySelectorAll('.checkbox-label').forEach(l=>l.classList.remove('checked'));this.closest('.checkbox-label').classList.add('checked')">
                                <span style="font-size:14px;color:#1E1B4B">{{ $opt }}</span>
                            </label>
                            @endforeach
                        </div>

                    @elseif($field->field_type === 'checkbox')
                        <div class="checkbox-group">
                            @foreach($field->options ?? [] as $opt)
                            @php $cbKey = $key . '[]'; $cbChecked = in_array($opt, (array) old($key, [])); @endphp
                            <label class="checkbox-label {{ $cbChecked ? 'checked' : '' }}">
                                <input type="checkbox" name="{{ $cbKey }}" value="{{ $opt }}"
                                       {{ $cbChecked ? 'checked' : '' }}
                                       onchange="this.closest('.checkbox-label').classList.toggle('checked', this.checked)">
                                <span style="font-size:14px;color:#1E1B4B">{{ $opt }}</span>
                            </label>
                            @endforeach
                        </div>

                    @elseif($field->field_type === 'multi_select')
                        <select id="{{ $key }}" name="{{ $key }}[]" multiple
                                {{ $field->is_required ? 'required' : '' }}
                                style="height:auto;min-height:80px">
                            @foreach($field->options ?? [] as $opt)
                            <option value="{{ $opt }}" {{ in_array($opt, (array) old($key, [])) ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        <p class="helper" style="margin-top:4px">Hold Ctrl / Cmd to select multiple options.</p>
                    @endif

                    @if($field->helper_text)
                    <p class="helper">{{ $field->helper_text }}</p>
                    @endif

                    @error($key)
                    <p class="error">{{ $message }}</p>
                    @enderror
                </div>
                @endforeach

                {{-- Request To (recipient selection) --}}
                @if($form->recipientOptions->count())
                <div class="field">
                    <label class="field-label">
                        Request To
                        <span class="required-star">*</span>
                    </label>
                    <p class="helper" style="margin-bottom:8px">Select one or more people who should receive this request.</p>

                    <div class="checkbox-group">
                        @foreach($form->recipientOptions as $recipient)
                        <label class="checkbox-label" onclick="this.classList.toggle('checked',this.querySelector('input').checked)">
                            <input type="checkbox" name="request_to[]" value="{{ $recipient->id }}"
                                   {{ is_array(old('request_to')) && in_array($recipient->id, old('request_to')) ? 'checked' : '' }}>
                            <div>
                                <p style="font-size:14px;font-weight:600;color:#1E1B4B">{{ $recipient->display_name }}</p>
                                <p style="font-size:12px;color:#9ca3af">{{ $recipient->email }}</p>
                            </div>
                        </label>
                        @endforeach
                    </div>

                    @error('request_to')
                    <p class="error">{{ $message }}</p>
                    @enderror
                </div>
                @endif

                <button type="submit" class="btn" id="submit-btn">
                    Submit Request
                </button>

                <p style="text-align:center;font-size:11px;color:#9ca3af;margin-top:14px">
                    Your submission will be reviewed by the selected recipient(s).
                </p>
            </form>
        </div>
    </div>

    <p style="text-align:center;font-size:11px;color:#9ca3af;margin-top:20px">
        Powered by <a href="/" style="color:#7B61FF;text-decoration:none">ReferralBunny.ai</a>
    </p>
</div>

<script>
document.getElementById('request-form').addEventListener('submit', function(e) {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Submitting...';
});
</script>
</body>
</html>
