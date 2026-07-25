<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Borrar una categoria con productos o con subcategorias romperia el
        // catalogo. La clave foranea de productos ya lo impide, pero el error de
        // base de datos no explica nada; asi se falla con un motivo claro.
        //
        // Va en el modelo y no solo en la Policy porque el superadministrador se
        // salta cualquier Policy por el Gate::before de AuthServiceProvider.
        static::deleting(function (self $category): void {
            if ($category->products()->exists()) {
                throw new RuntimeException('Esta categoria tiene productos. Reasignalos antes de eliminarla.');
            }

            if ($category->children()->exists()) {
                throw new RuntimeException('Esta categoria tiene subcategorias. Eliminalas o reasignalas primero.');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
