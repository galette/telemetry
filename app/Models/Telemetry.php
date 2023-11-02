<?php namespace GaletteTelemetry\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read  int $id
 */
class Telemetry extends Model
{
    protected $table = 'telemetry';
    protected $guarded = [
      'id'
    ];
}
