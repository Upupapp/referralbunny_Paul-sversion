@extends($admin ? 'layouts.app' : 'layouts.reseller')
@section('title', 'Messages')
@section('nav')
    @if($admin) @include('tenant._nav') @else @include('reseller._nav') @endif
@endsection
@section('content')
<style>
.rb-inbox{max-width:1100px;margin:auto;color:#28204a}.rb-inbox h1{font-size:25px;font-weight:700}.rb-inbox p.rb-muted{color:#82758f;font-size:13px;margin:8px 0 22px}.rb-inbox .rb-box{background:white;border:1px solid #e7e0ef;border-radius:18px;padding:22px;margin-bottom:18px}.rb-inbox select,.rb-inbox textarea{border:1px solid #dfd5eb;border-radius:11px;padding:11px;font-size:14px;background:#fcfaff;width:100%}.rb-inbox .rb-controls{display:flex;align-items:end;gap:14px;flex-wrap:wrap}.rb-inbox label{display:block;flex:1;min-width:180px;font-size:12px;font-weight:600}.rb-inbox button,.rb-inbox .rb-refresh{background:#8b19df;color:white;border:0;border-radius:10px;padding:11px 18px;font-size:13px;text-decoration:none;display:inline-block}.rb-inbox .rb-refresh{color:#873ac0;background:#f4eaff}.rb-inbox .rb-thread{max-height:460px;overflow:auto;display:flex;flex-direction:column;gap:15px;padding:8px 0}.rb-inbox .rb-bubble{max-width:85%;background:#f3eef9;border-radius:15px;padding:13px 16px;align-self:flex-start}.rb-inbox .rb-bubble.mine{align-self:flex-end;background:#8b19df;color:white}.rb-inbox .rb-bubble p{font-size:14px;white-space:pre-wrap;overflow-wrap:anywhere}.rb-inbox .rb-bubble small{display:block;font-size:10px;opacity:.75;margin-bottom:6px}.rb-inbox :focus-visible{outline:3px solid #c998ef;outline-offset:2px}
</style>
<div class="rb-inbox">
    <h1>Messages</h1><p class="rb-muted">{{ $admin ? 'Talk with the referrers enrolled in your programs.' : 'Choose a program to talk with its company admins.' }} Conversations stay separate for each program.</p>
    @if(session('success'))<p role="status" class="rb-box">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="rb-box">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route($admin ? 'tenant.messages' : 'reseller.messages', $tenant->id) }}" class="rb-box rb-controls">
        <label>Program<select name="program_id" onchange="this.form.querySelector('[name=reseller_id]')?.remove();this.form.submit()">
            @forelse($programs as $option)<option value="{{ $option->id }}" @selected($program?->id === $option->id)>{{ $option->name }}{{ ($programUnread[$option->id] ?? 0) ? ' · '.$programUnread[$option->id].' unread' : '' }}</option>@empty<option>No enrolled programs</option>@endforelse
        </select></label>
        @if($admin && $program)
        <label>Referrer<select name="reseller_id">@forelse($members as $member)<option value="{{ $member->id }}" @selected($recipient?->id === $member->id)>{{ $member->name }}{{ ($memberUnread[$member->id] ?? 0) ? ' · '.$memberUnread[$member->id].' unread' : '' }}</option>@empty<option>No enrolled referrers</option>@endforelse</select></label>
        @endif
        @if($program)<button type="submit">Open conversation</button>@endif
    </form>
    @if($program && $recipient)
    <div class="rb-box">
        <div class="rb-controls" style="justify-content:space-between"><strong>{{ $program->name }} · {{ $admin ? $recipient->name : 'Company admins' }}</strong><a class="rb-refresh" href="{{ request()->fullUrl() }}">Refresh</a></div>
        <div class="rb-thread" role="log" aria-label="Conversation">
            @forelse($messages->getCollection()->reverse() as $message)
            <article class="rb-bubble {{ $message->sender_type === ($admin ? 'admin' : 'referrer') ? 'mine' : '' }}"><small>{{ $message->sender_name }} · {{ \Carbon\Carbon::parse($message->created_at)->format('M j, Y · H:i') }} UTC</small><p>{{ $message->body }}</p></article>
            @empty<p class="rb-muted">No messages yet. Start the conversation below.</p>@endforelse
        </div>
        {{ $messages->links() }}
        <form method="POST" action="{{ route($admin ? 'tenant.messages.program.send' : 'reseller.messages.program.send', $tenant->id) }}" style="margin-top:20px" x-data="{sending:false}" @submit="sending=true">
            @csrf<input type="hidden" name="program_id" value="{{ $program->id }}">
            @if($admin)<input type="hidden" name="reseller_id" value="{{ $recipient->id }}">@endif
            <label for="program-message-body">Message</label><textarea id="program-message-body" name="body" rows="3" maxlength="5000" required placeholder="Write your message…">{{ old('body') }}</textarea>
            <button type="submit" :disabled="sending" style="margin-top:10px" x-text="sending ? 'Sending…' : 'Send message'">Send message</button>
        </form>
    </div>
    @else
    <div class="rb-box"><strong>{{ $admin ? 'No enrolled referrers yet' : 'Join a program to start messaging' }}</strong><p class="rb-muted">{{ $admin ? 'Conversations become available when referrers are enrolled in this program.' : 'Your enrolled programs will appear here. Each conversation goes to the company admins managing that program.' }}</p><a class="rb-refresh" href="{{ route($admin ? 'tenant.programs.index' : 'reseller.programs.index', $tenant->id) }}">View programs →</a></div>
    @endif
</div>
@endsection
