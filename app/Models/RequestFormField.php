<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RequestFormField extends Model
{
    protected $table = 'request_form_fields';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'request_form_id',
        'label', 'field_key', 'field_type',
        'placeholder', 'helper_text', 'options',
        'validation_rules', 'is_required', 'sort_order', 'is_system_field',
    ];

    protected $casts = [
        'options'          => 'array',
        'validation_rules' => 'array',
        'is_required'      => 'boolean',
        'is_system_field'  => 'boolean',
    ];
}
