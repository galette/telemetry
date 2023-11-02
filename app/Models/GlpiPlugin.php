<?php namespace GaletteTelemetry\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read  int $id
 */
class GlpiPlugin extends Model
{
    protected $table = 'glpi_plugin';
    protected $guarded = [
      'id'
    ];
}
