<?php namespace GLPI\Telemetry\Models;

use Illuminate\Database\Eloquent\Model;

class Reference extends Model
{
    protected $table = 'reference';
    protected $guarded = [
      'is_displayed'
    ];
}
