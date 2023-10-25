<?php namespace GLPI\Telemetry\Models;

use Illuminate\Database\Eloquent\Model;

class GlpiPlugin extends Model
{
    protected $table = 'glpi_plugin';
    protected $guarded = [
      'id'
    ];
}
