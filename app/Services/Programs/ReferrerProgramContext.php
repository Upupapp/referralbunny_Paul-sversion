<?php
namespace App\Services\Programs;
use App\Models\{Program, ReferrerProgramMembership, Reseller};
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
class ReferrerProgramContext
{
    public function resolve(string $tenantId, Request $request): array
    {
        abort_if(ProtectedTenants::isProtected($tenantId), 404);
        $reseller = auth('reseller')->user();
        abort_unless($reseller instanceof Reseller && $reseller->tenant_id === $tenantId, 403);
        $programs = Program::forTenant($tenantId)->visible()->whereIn('id',
            ReferrerProgramMembership::where('tenant_id',$tenantId)->where('reseller_id',$reseller->id)
                ->whereIn('status',['active','approved'])->select('program_id'))->orderBy('name')->get();
        $key = 'referrer_program.'.$tenantId.'.'.$reseller->id;
        $data = $request->validate(['program_id'=>'nullable|string']);
        $program = isset($data['program_id']) ? $programs->firstWhere('id',$data['program_id'])
            : ($programs->firstWhere('id', $request->session()->get($key)) ?? $programs->first());
        abort_if(isset($data['program_id']) && !$program, 404);
        if ($program) $request->session()->put($key,$program->id);
        else $request->session()->forget($key);
        return compact('reseller','programs','program');
    }
}
