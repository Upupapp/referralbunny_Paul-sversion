<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramLogoController extends Controller
{
    public function store(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);
        abort_if(ProtectedTenants::isProtected($tenantId), 404);
        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('update', $program);
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']]);
        $path = $request->file('logo')->store("program-logos/{$tenantId}/{$programId}", 'public');
        abort_unless($path, 500, 'The logo could not be saved. Please try again.');
        $old = $program->logo_path;
        try {
            $program->forceFill(['logo_path' => $path])->save();
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
        if ($old && str_starts_with($old, "program-logos/{$tenantId}/{$programId}/")) {
            Storage::disk('public')->delete($old);
        }
        return redirect()->route('tenant.programs.workspace', [$tenantId, $programId])->with('success', 'Program logo updated.');
    }
}
