<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomReport extends Model
{
    protected $fillable = ['name','entity','criteria','created_by'];
    protected $casts = ['criteria'=>'array'];
}
