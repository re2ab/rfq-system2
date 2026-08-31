<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    protected $fillable = ['entity','entity_id','key','value','show_in_info'];
}
