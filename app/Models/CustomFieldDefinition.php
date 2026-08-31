<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    protected $fillable = ['entity','key','label','field_type','options','is_required','sort_order'];
    protected $casts = ['options'=>'array','is_required'=>'boolean'];
}
