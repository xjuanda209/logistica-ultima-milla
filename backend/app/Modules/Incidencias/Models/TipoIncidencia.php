<?php

namespace App\Modules\Incidencias\Models;

use Database\Factories\TipoIncidenciaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'activo'])]
#[UseFactory(TipoIncidenciaFactory::class)]
class TipoIncidencia extends Model
{
    /** @use HasFactory<TipoIncidenciaFactory> */
    use HasFactory;

    protected $table = 'tipos_incidencia';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Incidencia, $this>
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class);
    }
}
