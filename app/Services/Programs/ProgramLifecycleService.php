<?php

namespace App\Services\Programs;

use App\Models\ActivityLog;
use App\Models\Program;
use App\Models\ProgramConfigurationVersion;
use Illuminate\Support\Facades\DB;

class ProgramLifecycleService
{
    /**
     * Transition a program's status and snapshot the current configuration.
     *
     * Valid transitions:
     *   draft      → active | scheduled | archived
     *   scheduled  → active | draft | archived
     *   active     → paused | ended | archived
     *   paused     → active | ended | archived
     *   ended      → archived
     *
     * @throws \InvalidArgumentException  on an illegal transition
     */
    public function transition(
        Program $program,
        string  $newStatus,
        int|string|null $actorId = null,
        string  $actorGuard = 'tenant'
    ): Program {
        $this->assertLegalTransition($program->status, $newStatus);

        DB::transaction(function () use ($program, $newStatus, $actorId, $actorGuard) {
            $oldStatus = $program->status;

            $program->status = $newStatus;

            if ($newStatus === 'active' && !$program->launched_at) {
                $program->launched_at = now();
            }
            if ($newStatus === 'ended' && !$program->ended_at) {
                $program->ended_at = now();
            }
            if ($newStatus === 'archived' && !$program->archived_at) {
                $program->archived_at = now();
            }

            $program->save();

            // Snapshot configuration on every state change for audit trail
            if ($newStatus === 'active') {
                $this->snapshotConfiguration($program, $actorId);
            }

            $this->log($program, "status_changed:{$oldStatus}→{$newStatus}", $actorId, $actorGuard);
        });

        return $program->fresh();
    }

    /**
     * Soft-delete a draft program. Only drafts with no leads may be deleted.
     *
     * @throws \RuntimeException  if program has operational history
     */
    public function delete(Program $program, int|string|null $actorId = null, string $actorGuard = 'tenant'): void
    {
        if ($program->status !== 'draft') {
            throw new \RuntimeException('Only draft programs can be deleted.');
        }
        if ($program->leads()->exists()) {
            throw new \RuntimeException('Cannot delete a program that has associated leads.');
        }

        DB::transaction(function () use ($program, $actorId, $actorGuard) {
            $this->log($program, 'deleted', $actorId, $actorGuard);
            $program->delete();
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function snapshotConfiguration(Program $program, int|string|null $actorId): void
    {
        try {
            $lastVersion = ProgramConfigurationVersion::where('program_id', $program->id)
                ->orderByDesc('version_number')
                ->value('version_number') ?? 0;

            ProgramConfigurationVersion::create([
                'tenant_id'       => $program->tenant_id,
                'program_id'      => $program->id,
                'version_number'  => $lastVersion + 1,
                'status'          => 'published',
                'published_at'    => now(),
                'published_by'    => $actorId,
                'snapshot'        => $program->attributesToArray(),
                'change_summary'  => "Status changed to {$program->status}",
            ]);
        } catch (\Throwable) {
            // Non-fatal: don't block the status transition
        }
    }

    private function log(Program $program, string $action, int|string|null $actorId, string $actorGuard): void
    {
        try {
            $meta = ['program_id' => $program->id, 'program_name' => $program->name];

            $log = [
                'tenant_id'   => $program->tenant_id,
                'action'      => "program.{$action}",
                'entity_type' => 'program',
                'entity_id'   => $program->id,
                'metadata'    => json_encode($meta),
            ];

            // activity_logs.user_id is bigint FK to staff users table only.
            // Tenant guard actors go into metadata to avoid type mismatch.
            if ($actorGuard === 'web' && $actorId) {
                $log['user_id'] = $actorId;
            } else {
                $meta['actor_id']    = $actorId;
                $meta['actor_guard'] = $actorGuard;
                $log['metadata']     = json_encode($meta);
            }

            ActivityLog::create($log);
        } catch (\Throwable) {}
    }

    private function assertLegalTransition(string $from, string $to): void
    {
        $allowed = [
            'draft'     => ['active', 'scheduled', 'archived'],
            'scheduled' => ['active', 'draft', 'archived'],
            'active'    => ['paused', 'ended', 'archived'],
            'paused'    => ['active', 'ended', 'archived'],
            'ended'     => ['archived'],
            'archived'  => [],
        ];

        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new \InvalidArgumentException(
                "Illegal program status transition: {$from} → {$to}"
            );
        }
    }
}
