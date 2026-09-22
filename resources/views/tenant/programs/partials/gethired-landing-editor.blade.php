@php
 $landing=app(\App\Services\Programs\GetHiredPublicLanding::class);
 $page=\App\Models\ProgramLandingPage::where('tenant_id',$tenant->id)->find($program->id);
 $copy=$landing->content();$ready=$landing->terms($program)['ready'];
@endphp
<section class="rounded-xl border border-purple-100 bg-white p-6 space-y-4">
 <h2 class="font-semibold">GetHired public referrer page</h2>
 <p class="text-sm text-gray-500">Edit the page text below. The signup fields and invitation process are fixed.</p>
 <div class="flex gap-2"><input aria-label="Public referral page link" readonly class="input w-full text-sm" value="{{ route('public.gethired.referrers') }}" onclick="this.select()"><button type="button" class="btn btn-ghost" x-data="{copied:false}" @click="navigator.clipboard.writeText(@js(route('public.gethired.referrers'))).then(()=>copied=true)" x-text="copied?'Copied':'Copy link'">Copy link</button></div>
 <div class="flex gap-3"><a href="{{ route('tenant.programs.landing.preview',[$tenant->id,$program->id]) }}" target="_blank" rel="noopener" class="btn btn-ghost">Preview page ↗</a>@if($page?->published)<a href="{{ route('public.gethired.referrers') }}" target="_blank" rel="noopener" class="btn btn-primary">Open public page ↗</a>@endif</div>
 <p class="text-sm">Status: <strong>{{ $page?->published?'Published':'Draft — link not public yet' }}</strong></p>
 @unless($ready)<p class="text-sm text-amber-800 rounded-lg bg-amber-50 p-3">The approved one-year GetHired reward offer must be activated before this page can be published. Existing historical rewards keep their original terms.</p>@endunless
 @if($errors->any())<div role="alert" class="text-red-700 text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
</section>
<form method="POST" action="{{ route('tenant.programs.landing.update',[$tenant->id,$program->id]) }}" class="rounded-xl border border-gray-200 bg-white p-6 space-y-4" x-data="{saving:false}" @submit="if(saving){$event.preventDefault()}else{saving=true}">
 @csrf @method('PATCH')
 @foreach(\App\Services\Programs\GetHiredPublicLanding::FIELDS as $field=>$limit)
 <div><label class="block text-sm font-medium mb-1" for="landing-{{ $field }}">{{ ucwords(str_replace('_',' ',$field)) }}</label><textarea class="input w-full" id="landing-{{ $field }}" name="content[{{ $field }}]" maxlength="{{ $limit }}" rows="{{ $limit>150?2:1 }}" required>{{ old('content.'.$field,$copy[$field]) }}</textarea><p class="text-xs text-gray-400">Up to {{ $limit }} characters · plain text</p></div>
 @endforeach
 <input type="hidden" name="published" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="published" value="1" @checked(old('published',$page?->published))> Publish this page and accept referrer invitations</label>
 <p class="text-xs text-gray-500">Only text and publication can be edited here. Name, email, optional phone, the CTA, backend package selection, calculator rules and confirmed-email success states are locked. Publishing allows visitors to request an invitation into this program.</p>
 @can('update',$program)<button class="btn btn-primary" type="submit" :disabled="saving" x-text="saving?'Saving…':'Save public page'">Save public page</button>@endcan
</form>
