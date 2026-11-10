<?php

namespace App\Modules\Administracion\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración general del sistema. La tabla tiene una sola fila (id = 1).
 */
#[Fillable(['id', 'max_pedidos_activos'])]
class Configuracion extends Model
{
    protected $table = 'configuracion';

    public $timestamps = false;

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_pedidos_activos' => 'integer',
        ];
    }

    /**
     * Devuelve la única fila de configuración.
     */
    public static function actual(): self
    {
        return self::query()->firstOrCreate(['id' => 1]);
    }
}
