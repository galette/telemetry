<?php namespace GaletteTelemetry\Models;

use Illuminate\Database\Eloquent\Model;

class Telemetry extends Model
{
    protected $table = 'telemetry';
    protected $guarded = [
      'id'
    ];
}
