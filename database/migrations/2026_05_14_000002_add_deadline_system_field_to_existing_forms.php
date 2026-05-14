<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a required "Deadline" date system field to every existing published
 * RequestForm that does not already have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = DB::table('request_forms')->get(['id', 'tenant_id']);

        foreach ($forms as $form) {
            $alreadyHas = DB::table('request_form_fields')
                ->where('request_form_id', $form->id)
                ->where('field_key', 'deadline')
                ->exists();

            if ($alreadyHas) continue;

            // Place deadline first (sort_order 0); shift existing fields up
            DB::table('request_form_fields')
                ->where('request_form_id', $form->id)
                ->increment('sort_order');

            DB::table('request_form_fields')->insert([
                'id'              => (string) Str::uuid(),
                'tenant_id'       => $form->tenant_id,
                'request_form_id' => $form->id,
                'label'           => 'Deadline',
                'field_key'       => 'deadline',
                'field_type'      => 'date',
                'placeholder'     => null,
                'helper_text'     => 'When do you need this completed?',
                'options'         => null,
                'is_required'     => true,
                'is_system_field' => true,
                'sort_order'      => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('request_form_fields')
            ->where('field_key', 'deadline')
            ->where('is_system_field', true)
            ->delete();
    }
};
