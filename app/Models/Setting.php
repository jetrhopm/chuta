<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            // json y no array: un valor de configuracion tambien puede ser un
            // numero, una bandera o un texto, no solo una lista.
            'value' => 'json',
            'is_encrypted' => 'boolean',
        ];
    }
}
