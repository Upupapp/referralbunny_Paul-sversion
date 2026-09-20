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
            $messages = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString();
        }
        return view('shared.program-messages', $context + compact('tenant','reseller','messages','programUnread','memberUnread'));
    }

    public function send(Request $request, string $tenantId)
    {
        $request->validate(['program_id'=>'required|string', 'body'=>['required','string','max:5000', function ($attribute, $value, $fail) { if (trim($value) === '') $fail('Write a message before sending.'); }]]);
        $context = $this->context($request, $tenantId);
        extract($context);
        abort_unless($program && $recipient, 422, 'Choose an enrolled referrer.');
        if ($admin) $request->validate(['reseller_id'=>'required|string']);
        DB::table('program_messages')->insert([
            'id'=>(string) Str::uuid(), 'tenant_id'=>$tenantId, 'program_id'=>$program->id,
            'reseller_id'=>$recipient->id, 'sender_type'=>$admin ? 'admin' : 'referrer',
            'sender_id'=>(string)$actor->id, 'sender_name'=>$admin ? 'Company admin' : $actor->name,
            'body'=>trim($request->input('body')), 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        return redirect()->route($admin ? 'tenant.messages' : 'reseller.messages', [
            'tenantId'=>$tenantId, 'program_id'=>$program->id, ...($admin ? ['reseller_id'=>$recipient->id] : []),
        ])->with('success', 'Message sent.');
    }
}
