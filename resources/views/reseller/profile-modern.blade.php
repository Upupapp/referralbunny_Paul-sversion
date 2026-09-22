@php
    $tenantId = $tenant->id;
    $photoUrl = \App\Services\UserDisplayNameService::photoUrl($reseller, false);
    $initials = \App\Services\UserDisplayNameService::initials($reseller, false);
    // The shared scorer uses phone_number; the referrer model persists phone.
    // Adapt this view only, without changing other portals or the protected tenant.
    $completionUser = clone $reseller;
    $completionUser->phone_number = $reseller->phone;
    $completion = \App\Services\UserDisplayNameService::completionPercent($completionUser);
    $checks = ['name'=>['Display name','name'], 'nickname'=>['Nickname','nickname'], 'phone'=>['Phone number','phone'], 'timezone'=>['Timezone','timezone'], 'bio'=>['Short bio','bio'], 'profile_photo_path'=>['Profile photo','photo']];
@endphp
<div class="rp-page" data-profile-modern>
    <section class="rp-hero rp-card">
        <div><span class="rp-eyebrow">YOUR PROFILE</span><h1>My Profile</h1><p>Manage your personal information and referrer details.</p></div>
        <div class="rp-hero-helper"><img src="{{ asset('images/mascots/r-bunny-waving.webp') }}" alt="" width="96" height="104"><div><strong>Keep your profile up to date!</strong><p>A complete profile helps the company recognize and support you better.</p></div></div>
    </section>
    @foreach(['success','password_success'] as $flash)
    @if(session($flash))<div class="rp-flash" role="status">✓ {{ session($flash) }}</div>@endif
    @endforeach
    @if($errors->any())<div class="rp-errors" role="alert"><strong>Please check the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="rp-grid">
        <section class="rp-card rp-photo" id="profile-photo">
            <h2><span class="rp-icon"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0M4 21a8 8 0 0116 0"/></svg></span> Profile Photo</h2>
            @if($photoUrl)<img class="rp-avatar" src="{{ $photoUrl }}" alt="Your profile photo" width="100" height="100">
            @else<div class="rp-avatar">{{ $initials }}</div>@endif
            <form method="POST" action="{{ route('reseller.profile.photo',$tenantId) }}" enctype="multipart/form-data" x-data="{uploading:false}" @submit="if(uploading){$event.preventDefault()}else{uploading=true}">
                @csrf
                <input class="sr-only" id="profile-photo-input" name="photo" type="file" accept="image/jpeg,image/png,image/webp" @change="if($event.target.files.length) $event.target.form.requestSubmit()" aria-describedby="photo-help photo-error">
                <button id="profile-photo-button" class="rp-btn" type="button" :disabled="uploading" @click="document.getElementById('profile-photo-input').click()" x-text="uploading ? 'Uploading…' : 'Upload Photo'">Upload Photo</button>
            </form>
            <p id="photo-help">JPG, PNG or WebP · Max 2 MB</p>
            @error('photo')<p id="photo-error" class="rp-error">{{ $message }}</p>@enderror
            @if($photoUrl)<form method="POST" action="{{ route('reseller.profile.photo.destroy',$tenantId) }}">@csrf @method('DELETE')<button class="rp-remove" type="submit">Remove photo</button></form>@endif
            @if($reseller->is_anonymous)<p class="rp-privacy-note">Hidden from other referrers while anonymous.</p>@endif
        </section>
        <section class="rp-card rp-completion">
            <h2><span class="rp-icon"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 4v6c0 4-8 8-8 8s-8-4-8-8V7l8-4m-4 9l3 3 5-6"/></svg></span> Profile Completion</h2>
            <div class="rp-progress-row"><div class="rp-progress" role="progressbar" aria-label="Profile completion" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $completion }}%"></span></div><strong>{{ $completion }}%</strong></div>
            <p>{{ $completion === 100 ? 'Your profile is complete. Keep your details up to date.' : 'Complete these details so the company can get to know you.' }}</p>
            <div class="rp-checklist">
                @foreach($checks as $key=>[$label,$anchor])
                <a href="#profile-{{ $anchor }}" @click.prevent="document.getElementById('profile-{{ $anchor === 'photo' ? 'photo-button' : $anchor }}').focus()"><span class="rp-check {{ filled($reseller->$key) ? 'is-complete' : '' }}">{{ filled($reseller->$key) ? '✓' : '○' }}</span>{{ $label }}<span class="sr-only">{{ filled($reseller->$key) ? 'complete' : 'incomplete' }}</span><span class="rp-chevron">›</span></a>
                @endforeach
            </div>
        </section>
        <section class="rp-card rp-overview">
            <h2><span class="rp-icon"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0M4 21a8 8 0 0116 0"/></svg></span> Referrer Overview</h2><p>Your existing account activity summary.</p>
            <dl><div><dt>Assigned deals</dt><dd>{{ $reseller->assigned_leads ?? 0 }}</dd></div><div><dt>Closed value · PHP</dt><dd>{{ number_format($reseller->closed_value ?? 0,2) }}</dd></div><div><dt>Performance score</dt><dd>{{ $reseller->performance_score ?? 0 }}%</dd></div></dl>
            <p class="rp-joined">Joined {{ $reseller->joined_date?->format('M j, Y') ?? '—' }}</p>
        </section>
        <section class="rp-card rp-information">
            <h2><span class="rp-icon"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0M4 21a8 8 0 0116 0"/></svg></span> Personal Information</h2><p>This information helps the company identify and communicate with you.</p>
            <form method="POST" action="{{ route('reseller.profile.update',$tenantId) }}" x-data="{saving:false, count:{{ mb_strlen(old('bio',$reseller->bio) ?? '') }}}" @submit="if(saving){$event.preventDefault()}else{saving=true}" @reset="saving=false; $nextTick(()=>count=$refs.bio.value.length)">
                @csrf
                <div class="rp-fields">
                    @foreach(['name'=>['Display name *','text',150], 'nickname'=>['Nickname','text',50], 'phone'=>['Phone number','tel',30], 'organization'=>['Organization','text',150], 'territory'=>['Territory / Service area','text',150], 'location'=>['Location','text',150]] as $key=>[$label,$type,$limit])
                    <div><label for="profile-{{ $key }}">{{ $label }}</label><input id="profile-{{ $key }}" name="{{ $key }}" type="{{ $type }}" value="{{ old($key,$reseller->$key) }}" maxlength="{{ $limit }}" @required($key==='name') aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}" aria-describedby="{{ $key }}-error @if($key==='nickname') nickname-help @endif">
                    @if($key==='nickname')<p id="nickname-help">How R Bunny greets you.</p>@endif
                    @error($key)<p class="rp-error" id="{{ $key }}-error">{{ $message }}</p>@enderror</div>
                    @endforeach
                    <div class="rp-wide"><label for="profile-email">Email address</label><input id="profile-email" type="email" value="{{ $reseller->email }}" readonly aria-describedby="email-help"><p id="email-help">Your sign-in email cannot be changed here.</p></div>
                    <div><label for="profile-timezone">Timezone</label><select id="profile-timezone" name="timezone">@foreach(\App\Services\UserDisplayNameService::timezones() as $tz)<option value="{{ $tz }}" @selected(old('timezone',$reseller->timezone ?? 'Asia/Manila')===$tz)>{{ $tz }}</option>@endforeach</select>@error('timezone')<p class="rp-error">{{ $message }}</p>@enderror</div>
                    <div><label for="profile-language">Language</label><select id="profile-language" name="language">@foreach(['en'=>'English','fil'=>'Filipino'] as $key=>$label)<option value="{{ $key }}" @selected(old('language',$reseller->language ?? 'en')===$key)>{{ $label }}</option>@endforeach</select>@error('language')<p class="rp-error">{{ $message }}</p>@enderror</div>
                    <div class="rp-wide"><label for="profile-bio">Short bio</label><textarea id="profile-bio" name="bio" rows="3" maxlength="500" x-ref="bio" @input="count=$event.target.value.length" placeholder="A short intro about yourself…" aria-describedby="bio-count bio-error">{{ old('bio',$reseller->bio) }}</textarea><p id="bio-count" class="rp-counter"><span x-text="count">{{ mb_strlen(old('bio',$reseller->bio) ?? '') }}</span> / 500</p>@error('bio')<p class="rp-error" id="bio-error">{{ $message }}</p>@enderror</div>
                </div>
                <div class="rp-actions"><button class="rp-btn" type="reset">Cancel</button><button class="rp-btn rp-primary" :disabled="saving" type="submit" x-text="saving ? 'Saving…' : 'Save Changes'">Save Changes</button></div>
            </form>
        </section>
        <aside class="rp-side">
            <section class="rp-card" x-data="{anon:{{ $reseller->is_anonymous ? 'true' : 'false' }},saving:false}">
                <h2><span class="rp-icon"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8a4 4 0 100 8 4 4 0 000-8M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10l2 2M5 19l2-2M17 7l2-2"/></svg></span> Preferences</h2><p>Control your privacy and display settings.</p>
                <div class="rp-preference"><div><strong>Anonymous Mode</strong><p>{{ $reseller->is_anonymous ? 'Your name and photo are hidden from other referrers. Admins can still see your identity.' : 'Your name and photo are visible to other referrers on this platform.' }}</p></div><button class="rp-toggle" :class="anon ? 'is-on' : ''" type="button" role="switch" :aria-checked="anon" aria-label="Anonymous mode" x-ref="privacyTrigger" @click="$refs.privacy.showModal()"><span></span></button></div>
                <dialog x-ref="privacy" class="rp-dialog" aria-labelledby="privacy-title" @close="$refs.privacyTrigger.focus()" @click="if($event.target===$refs.privacy) $refs.privacy.close()">
                    <h2 id="privacy-title">{{ $reseller->is_anonymous ? 'Disable Anonymous Mode?' : 'Enable Anonymous Mode?' }}</h2><p>{{ $reseller->is_anonymous ? 'Your real name and photo will be visible to other referrers in shared views.' : 'Your name will be replaced with an alias and your photo hidden from other referrers in shared views.' }}</p><p>Company admins can always see your identity. Your referrals and rewards are not affected.</p>
                    <form method="POST" action="{{ route('reseller.profile.anonymous',$tenantId) }}" @submit="if(saving){$event.preventDefault()}else{saving=true}">@csrf<input type="hidden" name="is_anonymous" value="{{ $reseller->is_anonymous ? '0' : '1' }}"><div class="rp-actions"><button class="rp-btn" type="button" @click="$refs.privacy.close()">Cancel</button><button class="rp-btn rp-primary" type="submit" :disabled="saving" x-text="saving ? 'Updating…' : 'Confirm'">Confirm</button></div></form>
                </dialog>
            </section>
            <section class="rp-card">
                <h2><span class="rp-icon"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10V7a5 5 0 0110 0v3M5 10h14v11H5zM12 14v3"/></svg></span> Account Security</h2><p>Keep your account secure.</p>
                <details class="rp-password" @if($errors->hasAny(['current_password','new_password','new_password_confirmation'])) open @endif><summary>Change Password <span>›</span></summary>
                    <form method="POST" action="{{ route('reseller.profile.password',$tenantId) }}" x-data="{saving:false}" @submit="if(saving){$event.preventDefault()}else{saving=true}">@csrf
                        @foreach(['current_password'=>'Current Password','new_password'=>'New Password (min 8 chars)','new_password_confirmation'=>'Confirm New Password'] as $key=>$label)
                        <label for="profile-{{ $key }}">{{ $label }}</label><input id="profile-{{ $key }}" name="{{ $key }}" type="password" required autocomplete="{{ $key==='current_password' ? 'current-password' : 'new-password' }}" @if($key!=='current_password') minlength="8" @endif aria-invalid="{{ $errors->has($key)?'true':'false' }}" aria-describedby="{{ $key }}-error">@error($key)<p class="rp-error" id="{{ $key }}-error">{{ $message }}</p>@enderror
                        @endforeach
                        <button class="rp-btn rp-primary" type="submit" :disabled="saving" x-text="saving ? 'Updating…' : 'Update Password'">Update Password</button>
                    </form>
                </details>
            </section>
            <section class="rp-card rp-help"><div><h2>Need help?</h2><p>Ask your program’s company admins about your referrer profile.</p><a class="rp-btn" href="{{ route('reseller.messages',$tenantId) }}">Message company admins →</a></div><img src="{{ asset('images/mascots/r-bunny-helper-question.webp') }}" alt="" width="72" height="80" loading="lazy"></section>
        </aside>
    </div>
</div>
