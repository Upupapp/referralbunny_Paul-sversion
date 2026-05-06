<?php

namespace App\Console\Commands;

use App\Models\ExportRequest;
use App\Services\ExportApprovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredExportsCommand extends Command
{
    protected $signature   = 'exports:cleanup-expired';
    protected $description = 'Delete expired export files from storage and mark their requests as expired';

    public function handle(ExportApprovalService $approvalService): int
    {
        // Find all ready requests whose file expiry window has passed
        $requests = ExportRequest::whereIn('status', [
            ExportRequest::STATUS_READY,
            ExportRequest::STATUS_DIRECT_READY,
        ])
        ->where('file_expires_at', '<', now())
        ->get();

        if ($requests->isEmpty()) {
            $this->info('No expired export files found.');
            return self::SUCCESS;
        }

        $deleted = 0;
        $missing = 0;

        foreach ($requests as $request) {
            // Remove the physical file if it exists
            if ($request->file_path && Storage::disk('local')->exists($request->file_path)) {
                Storage::disk('local')->delete($request->file_path);
                $deleted++;
            } else {
                $missing++;
            }
        }

        // Bulk-update statuses and audit-log each through the service
        $count = $approvalService->expireOldRequests();

        $this->info("Expired {$count} export request(s). Files deleted: {$deleted}, already missing: {$missing}.");

        return self::SUCCESS;
    }
}
