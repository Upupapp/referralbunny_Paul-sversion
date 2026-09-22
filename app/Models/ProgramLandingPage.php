<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProgramLandingPage extends Model {
 protected $primaryKey='program_id';public $incrementing=false;protected $keyType='string';
 protected $fillable=['program_id','tenant_id','content','published','updated_by'];
 protected $casts=['content'=>'array','published'=>'boolean'];
}
