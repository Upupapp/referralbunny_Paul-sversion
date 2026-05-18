<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateResellerPhotoPaths extends Command
{
    protected $signature   = 'resellers:migrate-photo-paths {--dry-run : Show what would be moved without making changes}';
    protected $description = 'Move reseller profile photos from profile-photos/reseller/{id} to profile-photos/{tenant_id}/reseller/{id}';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $rows = DB::table('resellers')
            ->whereNotNull('profile_photo_path')
            ->where('profile_photo_path', 'like', 'profile-photos/reseller/%')
            ->select('id', 'tenant_id', 'profile_photo_path')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No reseller photos need migrating.');
            return 0;
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Found {$rows->count()} photo(s) to migrate.");
        $moved = 0; $missing = 0;

        foreach ($rows as $row) {
            $oldPath = $row->profile_photo_path;
            $filename = basename($oldPath);
            $newPath = "profile-photos/{$row->tenant_id}/reseller/{$row->id}/{$filename}";

            if ($dry) {
                $this->line("  {$oldPath}  →  {$newPath}");
                continue;
            }

            if (!Storage::disk('public')->exists($oldPath)) {
                $this->warn("  Missing: {$oldPath} (DB updated to new path anyway)");
                $missing++;
            } else {
                Storage::disk('public')->move($oldPath, $newPath);
                $moved++;
            }

            DB::table('resellers')->where('id', $row->id)->update(['profile_photo_path' => $newPath]);
        }

        if (!$dry) {
            $this->info("Moved: {$moved} file(s). Missing on disk: {$missing}. DB updated for all.");
        }

        return 0;
    }
}
