<?php namespace GLPI\Telemetry\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicReference extends Model
{
    protected $table = 'dynamic_reference';
    protected $guarded = [
      'reference_id'
    ];

    /**
     * Set the table associated with the model.
     *
     * @param  string  $table
     * @return void
     */
    public function setTable($table)
    {
        $this->table = $table;
    }
}
