<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostalCode extends Model
{
    protected $fillable = [
        'postcode',
        'settlement',
        'settlement_type',
        'municipality',
        'state',
        'city',
        'zone',
        'settlement_key',
    ];
}
