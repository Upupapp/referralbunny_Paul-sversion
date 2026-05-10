<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DealNoteAttachment extends Model
{
    protected $table = 'deal_note_attachments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'deal_comment_id', 'uploaded_by_id', 'uploaded_by_role',
        'disk', 'path', 'original_filename', 'stored_filename',
        'mime_type', 'file_size', 'file_type_group',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(DealComment::class, 'deal_comment_id');
    }

    public static function typeGroup(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (in_array($mime, ['application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])) return 'document';
        if (in_array($mime, ['application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv'])) return 'spreadsheet';
        return 'other';
    }
}
