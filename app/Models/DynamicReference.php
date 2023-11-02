<?php namespace GaletteTelemetry\Models;

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
     *
     * @return self
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }
}
