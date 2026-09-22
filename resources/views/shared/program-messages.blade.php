@extends($admin ? 'layouts.app' : 'layouts.reseller')
@section('title', 'Messages')
@section('nav')
@if($admin) @include('tenant._nav') @else @include('reseller._nav') @endif
@endsection
@section('content')
@php
 $inboxRoute=$admin?'tenant.messages':'reseller.messages';
 $scope=['program_id'=>$program?->id]+($admin?['reseller_id'=>$recipient?->id]:[]);
 $inboxUrl=route($inboxRoute,['tenantId'=>$tenant->id]+$scope);
 $options=$admin?$members:$programs;
 $selected=$admin?$recipient?->id:$program?->id;
 $counts=$admin?$memberUnread:$programUnread;
 $chatConfig=['messages'=>$messageRows,'draft'=>old('body',$referralDraft??''),'poll'=>$program&&$recipient&&request('page',1)==1?$inboxUrl:null,'send'=>route($admin?'tenant.messages.program.send':'reseller.messages.program.send',$tenant->id),'scope'=>$scope,'csrf'=>csrf_token(),'conversations'=>$options->map(fn($o)=>['name'=>$o->name,'unread'=>(int)($counts[$o->id]??0)])->values()->all()];
@endphp
<div class="msg-page {{ $admin?'msg-admin':'msg-referrer' }}" x-data="programMessenger({{ \Illuminate\Support\Js::from($chatConfig) }})" @keydown.escape.window="if($refs.details.open)closeDetails()">
 <header class="msg-page-heading"><div><h1>Messages</h1><p>{{ $admin?'Chat with referrers in your programs.':'Chat with the company team for your referral programs.' }}</p></div>
 @if($admin)<form method="GET" action="{{ route($inboxRoute,$tenant->id) }}"><label class="sr-only" for="msg-program">Program</label><select id="msg-program" name="program_id" onchange="this.form.submit()">@foreach($programs as $p)<option value="{{ $p->id }}" @selected($p->id===$program?->id)>{{ $p->name }}</option>@endforeach</select></form>@endif
 </header>
 @if($errors->any())<p role="alert" class="msg-error">{{ $errors->first() }}</p>@endif
 <div class="msg-workspace" :class="{'msg-list-mode':mobileList}">
  <aside class="msg-conversations" aria-label="Conversations"><div class="msg-list-heading"><h2>{{ $admin?'Referrers':'Program conversations' }}</h2><small>{{ $options->count() }} {{ $admin?'referrers':'programs' }}</small></div>
  @if($options->count()>1)<label class="msg-search"><span class="sr-only">Search conversations</span><input type="search" placeholder="Search conversations…" x-model="search"></label>@endif
  <div class="msg-filters"><button type="button" @click="unreadOnly=false" :aria-pressed="!unreadOnly">All</button><button type="button" @click="unreadOnly=true" :aria-pressed="unreadOnly">Unread <span>{{ $counts->sum() }}</span></button></div>
  <div class="msg-list">
  @foreach($options as $option)
   @php $count=(int)($counts[$option->id]??0); $choiceScope=$admin?['program_id'=>$program->id,'reseller_id'=>$option->id]:['program_id'=>$option->id]; @endphp
   <a class="msg-row {{ $selected===$option->id?'selected':'' }}" @if($selected===$option->id) aria-current="page" @endif href="{{ route($inboxRoute,['tenantId'=>$tenant->id]+$choiceScope) }}" x-show="(!unreadOnly || {{ $count }}>0) && {{ \Illuminate\Support\Js::from(mb_strtolower($option->name)) }}.includes(search.toLowerCase())">
    <span class="msg-avatar">{{ mb_strtoupper(mb_substr($option->name,0,2)) }}</span><span class="msg-row-copy"><strong>{{ $option->name }}</strong><small>{{ $admin?'Program conversation':'Company admins' }}</small></span>@if($count)<span class="msg-unread" aria-label="{{ $count }} unread messages">{{ $count }}</span>@endif
   </a>
  @endforeach
  <p class="msg-search-empty" x-cloak x-show="(search || unreadOnly) && !visibleConversations()">No conversations match your filters.</p>
  @if($options->isEmpty())<p class="msg-no-options">{{ $admin?'No enrolled referrers yet.':'Join a program to start messaging.' }}</p>@endif
  </div></aside>
  <section class="msg-chat" aria-label="Selected conversation">
   <header class="msg-thread-heading"><button type="button" class="msg-back" @click="mobileList=true" aria-label="Back to conversations">←</button><span class="msg-avatar">{{ mb_strtoupper(mb_substr($admin?($recipient?->name??'?'):($program?->name??'?'),0,2)) }}</span><div><h2>{{ $admin?($recipient?->name??'Choose a referrer'):($program?->name??'Choose a program') }}</h2><p>{{ $admin?'Referrer · '.$program?->name:'Company admins · In-app messaging' }}</p></div>@if($program)<button type="button" x-ref="detailsTrigger" class="msg-details-toggle" @click="showDetails()">Details</button>@endif</header>
   <p class="msg-network" role="status" x-show="network" x-cloak><span x-text="network"></span> <button type="button" @click="poll()">Retry now</button></p>
   <div class="msg-history" x-ref="history" @scroll="if(nearBottom())newMessages=false" aria-label="Message history" tabindex="0">
    @if($messages && $messages->hasPages())<nav class="msg-history-pages" aria-label="Message history pages">@if($messages->nextPageUrl())<a href="{{ $messages->nextPageUrl() }}">← Older messages</a>@endif @if($messages->previousPageUrl())<a href="{{ $messages->previousPageUrl() }}">Newer messages →</a>@endif</nav>@endif
    <template x-for="(message,i) in messages" :key="message.id"><div><div class="msg-day" x-show="separator(i)"><span x-text="day(message.at)"></span></div><article class="msg-message" :class="{'mine':message.mine,'grouped':grouped(i)}"><div class="msg-bubble"><small x-show="!grouped(i)" x-text="message.mine?'You':message.sender"></small><p x-text="message.body"></p><time :datetime="message.at" x-text="time(message.at)"></time></div></article></div></template>
    <div class="msg-empty" x-show="!messages.length"><img src="{{ asset('images/mascots/r-bunny-celebration.webp') }}" width="80" height="80" alt="R Bunny"><h3>{{ $program&&$recipient?'Start the conversation':'No conversation selected' }}</h3><p>{{ $admin?'Select an enrolled referrer and send a message about this program.':'Have a question about the program, your referrals, or your rewards? Send the company team a message.' }}</p></div>
   </div>
   <button type="button" class="msg-new" x-show="newMessages" x-cloak @click="bottom()">↓ New messages</button>
   @if($program && $recipient)
   <form class="msg-composer" @submit.prevent="send()" method="POST" action="{{ $chatConfig['send'] }}">@csrf<input type="hidden" name="program_id" value="{{ $program->id }}">@if($admin)<input type="hidden" name="reseller_id" value="{{ $recipient->id }}">@endif
    <p role="alert" class="msg-error" x-text="error" x-show="error" x-cloak></p>
    <label for="msg-body" class="sr-only">Write a message</label><textarea id="msg-body" name="body" x-ref="composer" x-model="body" :readonly="sending" @input="grow($el)" @keydown.enter="if(!$event.shiftKey && !$event.isComposing){$event.preventDefault();send()}" rows="2" maxlength="5000" required placeholder="Write a message…">{{ old('body',$referralDraft??'') }}</textarea><div class="msg-compose-footer"><small>Enter to send · Shift+Enter for a new line <span x-text="body.length+'/5000'"></span></small><button type="submit" :disabled="sending || !body.trim() || body.length>5000" x-text="sending?'Sending…':'Send →'">Send →</button></div>
   </form>@endif
  </section>
  <aside class="msg-context" aria-label="Program details">@include('shared.messaging.details')</aside>
 </div>
 <dialog x-ref="details" class="msg-details-dialog" @click="if($event.target===$el)closeDetails()" @cancel.prevent="closeDetails()"><button type="button" @click="closeDetails()" aria-label="Close details">Close ×</button>@include('shared.messaging.details')</dialog>
 <noscript><p>Enable JavaScript to use the live messenger. Your existing messages remain saved.</p></noscript>
</div>
@endsection
