<style>
.rb-program-list{max-width:1280px;margin:0 auto;color:#24204f;padding:4px 0 28px}
.rb-program-list .rb-list-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:22px;padding:28px;border:1px solid #e9e2f3;border-radius:20px;background:linear-gradient(115deg,#fff 55%,#f8f1ff);margin-bottom:24px}
.rb-program-list .rb-eyebrow{font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#9743df;font-weight:700;margin-bottom:8px}
.rb-program-list h1{font-size:28px;font-weight:700;letter-spacing:-.6px;line-height:1.2}
.rb-program-list .rb-intro{font-size:14px;color:#837c95;margin-top:9px;line-height:1.6}
.rb-program-list .rb-add{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 18px;border-radius:12px;background:#8b19df;color:#fff;font-weight:600;font-size:13px;text-decoration:none;box-shadow:0 4px 10px #8b19df18;white-space:nowrap}
.rb-program-list .rb-add:hover{background:#7611c4}
.rb-program-list .rb-list-summary{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:0 0 20px;padding:0 2px}
.rb-program-list .rb-summary-item{font-size:12px;color:#837c95;padding:7px 12px;border:1px solid #e5deee;border-radius:9px;background:#ffffffa8}
.rb-program-list .rb-summary-item strong{color:#332653;margin-right:5px;font-weight:700}
.rb-program-list .rb-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.rb-program-list .rb-program-card{display:flex;flex-direction:column;min-width:0;padding:24px;border:1px solid #e8e2f0;border-radius:20px;background:white;text-decoration:none;color:inherit;box-shadow:0 3px 10px #24204f04;transition:border-color .18s,box-shadow .18s,transform .18s}
.rb-program-list .rb-program-card:hover{border-color:#cea9ee;box-shadow:0 8px 22px #6526990b;transform:translateY(-2px)}
.rb-program-list .rb-card-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px}
.rb-program-list .rb-program-icon{display:flex;align-items:center;justify-content:center;background:#f3e9ff;color:#9340de;border-radius:13px;width:46px;height:46px;flex-shrink:0}
.rb-program-list .rb-status{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:6px 10px;border-radius:20px;background:#f1eff5;color:#746b87;white-space:nowrap}
.rb-program-list .rb-status:before{content:'';width:5px;height:5px;border-radius:50%;background:currentColor}
.rb-program-list .rb-status[data-status=active]{background:#eafaf2;color:#13865a}
.rb-program-list .rb-status[data-status=paused]{background:#fff6e4;color:#a56b13}
.rb-program-list .rb-status[data-status=scheduled]{background:#eff3ff;color:#566bc4}
.rb-program-list .rb-status[data-status=ended]{background:#fff0f0;color:#b26363}
.rb-program-list h2{font-size:18px;font-weight:700;line-height:1.45;letter-spacing:-.25px;overflow-wrap:anywhere}
.rb-program-list .rb-card-description{font-size:13px;line-height:1.7;color:#898198;margin-top:9px;min-height:44px;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.rb-program-list .rb-card-tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;margin-bottom:24px}
.rb-program-list .rb-card-tags span{background:#faf7fd;border:1px solid #eee8f5;border-radius:7px;padding:4px 8px;font-size:11px;color:#7c6b92}
.rb-program-list .rb-card-footer{margin-top:auto;padding-top:18px;border-top:1px solid #f0ebf6;display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:11px;color:#a298af}
.rb-program-list .rb-open{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#9030d6;white-space:nowrap}
.rb-program-list .rb-empty{text-align:center;padding:64px 24px;background:white;border:1px dashed #d9c8ea;border-radius:20px}
.rb-program-list .rb-empty .rb-program-icon{margin:0 auto 20px;width:60px;height:60px;border-radius:18px}
.rb-program-list .rb-empty p{max-width:390px;margin:10px auto 24px;font-size:14px;line-height:1.7;color:#898198}
.rb-program-list a:focus-visible{outline:3px solid #bd83ec;outline-offset:4px}
.rb-program-list .rb-success{margin-bottom:20px;border:1px solid #c8ecda;background:#effbf5;color:#28734f;border-radius:12px;padding:14px 18px;font-size:13px}
@media(max-width:1200px){.rb-program-list .rb-card-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:640px){.rb-program-list .rb-card-grid{grid-template-columns:1fr}.rb-program-list .rb-list-header{padding:22px;border-radius:16px}.rb-program-list h1{font-size:24px}.rb-program-list .rb-program-card{padding:22px}.rb-program-list .rb-add{width:100%}}
@media(prefers-reduced-motion:reduce){.rb-program-list .rb-program-card{transition:none;transform:none}}
</style>
<div class="rb-program-list">
    <header class="rb-list-header">
        <div>
            <p class="rb-eyebrow">Grow through referrals</p>
            <h1>Your programs</h1>
            <p class="rb-intro">A home for your referral, affiliate, and partner programs.</p>
        </div>
        @can('create', \App\Models\Program::class)
        <a href="{{ route('tenant.programs.create', $tenant->id) }}" class="rb-add">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
            Add Program
        </a>
        @endcan
    </header>
    @if(session('success'))<div class="rb-success" role="status">{{ session('success') }}</div>@endif
    @if($programs->isNotEmpty())
    <div class="rb-list-summary" aria-label="Program summary">
        <span class="rb-summary-item"><strong>{{ $programs->count() }}</strong> {{ $programs->count() === 1 ? 'program' : 'programs' }}</span>
        <span class="rb-summary-item"><strong>{{ $programs->where('status', 'active')->count() }}</strong> active</span>
        <span class="rb-summary-item"><strong>{{ $programs->where('status', 'draft')->count() }}</strong> drafts</span>
    </div>
    <div class="rb-card-grid">
        @foreach($programs as $program)
        <a href="{{ route('tenant.programs.workspace', [$tenant->id, $program->id]) }}" class="rb-program-card">
            <div class="rb-card-top">
                <span class="rb-program-icon" aria-hidden="true"><svg width="23" height="23" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M20 12v8H4v-8m-1-4h18v4H3zM12 8v12m0-12H8a3 3 0 113-3l1 3zm0 0h4a3 3 0 10-3-3l-1 3z"/></svg></span>
                <span class="rb-status" data-status="{{ $program->status }}">{{ ucfirst($program->status) }}</span>
            </div>
            <h2>{{ $program->name }}</h2>
            <p class="rb-card-description">{{ $program->short_description ?: 'Manage the rewards, members, and settings for this program.' }}</p>
            <div class="rb-card-tags">
                <span>{{ ucfirst(str_replace('_', ' ', $program->program_type)) }}</span>
                @if($program->is_default)<span>Default program</span>@endif
                <span>{{ ucfirst($program->public_visibility ?? 'private') }}</span>
            </div>
            <div class="rb-card-footer">
                <span>{{ $program->launched_at ? 'Launched '.$program->launched_at->diffForHumans() : 'Not launched yet' }}</span>
                <span class="rb-open">Open program <span aria-hidden="true">→</span></span>
            </div>
        </a>
        @endforeach
    </div>
    @else
    <div class="rb-empty">
        <span class="rb-program-icon" aria-hidden="true"><svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.6" d="M12 5v14M5 12h14"/></svg></span>
        <h2>Your next customer could come from a referral.</h2>
        <p>Create your first program, choose your rewards, and give people a reason to spread the word.</p>
        @can('create', \App\Models\Program::class)
        <a href="{{ route('tenant.programs.create', $tenant->id) }}" class="rb-add">Create your first program</a>
        @endcan
    </div>
    @endif
</div>
