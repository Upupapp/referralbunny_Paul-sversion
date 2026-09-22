<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Program, ReferrerProgramMembership, Reseller, Tenant};
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;

class ProgramMessageController extends Controller
{
    private function context(Request $request, string $tenantId): array
    {
        abort_if(ProtectedTenants::isProtected($tenantId), 404);
        abort_unless(config('programs.enabled'), 404);
        $admin = !$request->routeIs('reseller.*');
        $actor = $admin ? (auth('web')->user() ?? auth('tenant')->user()) : auth('reseller')->user();
        abort_unless($actor, 403);
        if ($admin) {
            abort_unless(Gate::forUser($actor)->allows('create', Program::class), 403);
        } else {
            abort_unless($actor->tenant_id === $tenantId && in_array($actor->status, ['active', 'nda_signed']), 403);
        }
        $programs = Program::forTenant($tenantId)->visible();
        if (!$admin) {
            $programs->whereIn('id', ReferrerProgramMembership::where('tenant_id', $tenantId)
                ->where('reseller_id', $actor->id)->whereIn('status', ['active','approved'])->select('program_id'));
        }
        $programs = $programs->orderBy('name')->get();
        $referrerContext = !$admin ? app(\App\Services\Programs\ReferrerProgramContext::class)->resolve($tenantId, $request) : null;
        $request->validate(['thread_id'=>'prohibited', 'partner_id'=>'prohibited']);
        $data = $request->validate(['program_id'=>'nullable|string', 'reseller_id'=>'nullable|string']);
        $program = isset($data['program_id']) ? $programs->firstWhere('id', $data['program_id']) : ($referrerContext['program'] ?? $programs->first());
        abort_if(isset($data['program_id']) && !$program, 404);
        $members = collect();
        if ($program && $admin) {
            $members = Reseller::where('tenant_id', $tenantId)->whereIn('status', ['active','nda_signed'])
                ->whereIn('id', ReferrerProgramMembership::where('tenant_id', $tenantId)
                    ->where('program_id', $program->id)->whereIn('status', ['active','approved'])->select('reseller_id'))
                ->orderBy('name')->get(['id','name']);
        }
        $recipient = $admin ? (isset($data['reseller_id']) ? $members->firstWhere('id', $data['reseller_id']) : $members->first()) : $actor;
        abort_if($admin && isset($data['reseller_id']) && !$recipient, 404);
        // A referrer can never address another referrer's conversation.
        abort_if(!$admin && isset($data['reseller_id']) && $data['reseller_id'] !== (string)$actor->id, 403);
        return compact('admin','actor','programs','program','members','recipient');
    }

    public function index(Request $request, string $tenantId)
    {
        $context = $this->context($request, $tenantId);
        extract($context);
        $tenant = Tenant::findOrFail($tenantId);
        $reseller = $admin ? null : $actor;
        $referralDraft = null;
        if (!$admin && $program && $program->effectiveOperatingMode() === 'automated') {
            if ($request->filled('referral_reference')) {
                $request->validate(['referral_reference'=>'required|string|max:100']);
                $account = app(\App\Services\Programs\ReferralAccountReadModel::class)->detail($program, $actor, $request->referral_reference, $program->default_currency ?: 'PHP');
                $referralDraft = 'Please review referral '.$account['reference'].' in '.$program->name.'. My question: ';
            } elseif ($request->boolean('missing_referral')) {
                $referralDraft = 'Please review a missing referral in '.$program->name.'. Approximate referral date: \nDetails I can share: ';
            }
        }
        $messages = null;
        $unread = DB::table('program_messages')->where('tenant_id', $tenantId)
            ->whereIn('program_id', $programs->pluck('id'))->whereNull('read_at')
            ->where('sender_type', $admin ? 'referrer' : 'admin');
        if (!$admin) $unread->where('reseller_id', $actor->id);
        $programUnread = (clone $unread)->selectRaw('program_id, COUNT(*) as total')->groupBy('program_id')->pluck('total','program_id');
        $memberUnread = $program && $admin ? (clone $unread)->where('program_id', $program->id)
            ->selectRaw('reseller_id, COUNT(*) as total')->groupBy('reseller_id')->pluck('total','reseller_id') : collect();
        if ($program && $recipient) {
            $query = DB::table('program_messages')->where('tenant_id', $tenantId)
                ->where('program_id', $program->id)->where('reseller_id', $recipient->id);
            (clone $query)->where('sender_type', $admin ? 'referrer' : 'admin')->whereNull('read_at')->update(['read_at'=>now()]);
            if ($admin) $memberUnread->put($recipient->id,0);
            else $programUnread->put($program->id,0);
            $messages = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString();
        }
        $messageRows = $messages ? $messages->getCollection()->reverse()->values()->map(fn($m)=>[
            'id'=>$m->id,'body'=>$m->body,'sender'=>$m->sender_name,'mine'=>$m->sender_type===($admin?'admin':'referrer'),
            'at'=>\Carbon\Carbon::parse($m->created_at,'UTC')->toIso8601String(),
        ])->all() : [];
        if ($request->expectsJson()) return response()->json(['messages'=>$messageRows,'program_unread'=>$programUnread,'member_unread'=>$memberUnread])->header('Cache-Control','no-store');
        $messagingPage = true;
        return view('shared.program-messages', $context + compact('tenant','reseller','messages','programUnread','memberUnread','referralDraft','messageRows','messagingPage'));
    }

    public function send(Request $request, string $tenantId)
    {
        $request->validate(['client_id'=>'nullable|uuid','program_id'=>'required|string', 'body'=>['required','string','max:5000', function ($attribute, $value, $fail) { if (trim($value) === '') $fail('Write a message before sending.'); }]]);
        $context = $this->context($request, $tenantId);
        extract($context);
        abort_unless($program && $recipient, 422, 'Choose an enrolled referrer.');
        if ($admin) $request->validate(['reseller_id'=>'required|string']);
        // Stable per-attempt IDs prevent duplicate messages after ambiguous network failures.
        $key = $request->input('client_id');
        $hash = $key ? hash_hmac('sha256',implode('|',[$tenantId,$program->id,$recipient->id,$admin?'admin':'referrer',$actor->id,$key]),config('app.key')) : null;
        $id = $hash ? substr($hash,0,8).'-'.substr($hash,8,4).'-4'.substr($hash,13,3).'-a'.substr($hash,17,3).'-'.substr($hash,20,12) : (string) Str::uuid();
        DB::table('program_messages')->insertOrIgnore([
            'id'=>$id, 'tenant_id'=>$tenantId, 'program_id'=>$program->id,
            'reseller_id'=>$recipient->id, 'sender_type'=>$admin ? 'admin' : 'referrer',
            'sender_id'=>(string)$actor->id, 'sender_name'=>$admin ? 'Company admin' : $actor->name,
            'body'=>trim($request->input('body')), 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $saved=DB::table('program_messages')->where('tenant_id',$tenantId)->where('program_id',$program->id)->where('reseller_id',$recipient->id)->where('id',$id)->first();
        abort_unless($saved && $saved->body===trim($request->input('body')),409,'This send attempt already exists. Refresh before sending changed text.');
        if ($request->expectsJson()) return response()->json(['message'=>['id'=>$saved->id,'body'=>$saved->body,'sender'=>$saved->sender_name,'mine'=>true,'at'=>\Carbon\Carbon::parse($saved->created_at,'UTC')->toIso8601String()]])->header('Cache-Control','no-store');
        return redirect()->route($admin ? 'tenant.messages' : 'reseller.messages', [
            'tenantId'=>$tenantId, 'program_id'=>$program->id, ...($admin ? ['reseller_id'=>$recipient->id] : []),
        ])->with('success', 'Message sent.');
    }
}
