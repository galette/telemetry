<?php namespace GaletteTelemetry\Models;

use Illuminate\Database\Eloquent\Model;

class Reference extends Model
{
    protected $table = 'reference';
    protected $fillable = [
        'uuid',
        'name',
        'url',
        'country',
        'phone',
        'email',
        'referent',
        'num_members',
        'comment'
    ];
}
