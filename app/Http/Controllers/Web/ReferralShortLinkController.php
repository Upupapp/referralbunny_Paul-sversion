<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReferrerProgramMembership;
use App\Services\Programs\ReferrerReferralLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralShortLinkController extends Controller
{
    public function __invoke(Request $request, string $code, ReferrerReferralLink $links)
    {
        abort_unless(config('programs.enabled'), 404);
        $id = DB::table('program_referral_links')->where('code', $code)->value('membership_id');
        $membership = $id ? ReferrerProgramMembership::find($id) : null;
        $program = $membership?->program;
        $destination = $program ? $links->destination($program, $membership) : null;
        abort_unless($destination, 404);
        $canonical = route('referral.short', ['code' => $code]);
        $host = parse_url($destination, PHP_URL_HOST);
        // Never expose referrer identity, private program descriptions or reward terms.
        $title = $program->name;
        $description = 'You’re invited to explore '.$host.'. Shared through Referral Bunny.';
        return response()->view('referral.short', compact('destination', 'canonical', 'host', 'title', 'description') + [
            'preview' => $request->boolean('preview'),
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow')
          ->header('Referrer-Policy', 'no-referrer');
    }
}
