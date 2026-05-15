<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResourceFolder extends Model
{
    protected $table = 'resource_folders';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn(self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'name', 'parent_id',
        'created_by_type', 'created_by_id', 'created_by_name',
    ];

    public function parent()      { return $this->belongsTo(ResourceFolder::class, 'parent_id'); }
    public function children()    { return $this->hasMany(ResourceFolder::class, 'parent_id'); }
    public function files()       { return $this->hasMany(ResourceFile::class, 'folder_id'); }
}
