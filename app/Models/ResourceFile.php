<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResourceFile extends Model
{
    use SoftDeletes;

    protected $table = 'resource_files';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn(self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'folder_id', 'name', 'disk', 'path',
        'size', 'mime_type', 'file_type_group',
        'uploaded_by_type', 'uploaded_by_id', 'uploaded_by_name',
    ];

    protected $casts = ['size' => 'integer'];

    public function folder() { return $this->belongsTo(ResourceFolder::class, 'folder_id'); }

    public function formattedSize(): string
    {
        $bytes = $this->size ?? 0;
        if ($bytes < 1024)         return $bytes . ' B';
        if ($bytes < 1048576)      return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
