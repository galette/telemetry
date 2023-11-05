<?php namespace GaletteTelemetry\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read  int $id
 */
class Plugin extends Model
{
    protected $table = 'plugins';
    protected $guarded = [
      'id'
    ];
}
