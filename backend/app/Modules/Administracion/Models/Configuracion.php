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
     * Devuelve la única fila de configuración. Si aún no existe la crea y la vuelve a
     * leer, para que traiga los valores por defecto que pone la base de datos.
     */
    public static function actual(): self
    {
        return self::query()->find(1) ?? self::query()->create(['id' => 1])->fresh();
    }
}
