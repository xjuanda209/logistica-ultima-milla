<?php

namespace App\Modules\Incidencias\Models;

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\EstadoIncidencia;
use App\Shared\Enums\Prioridad;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'pedido_id', 'tipo_incidencia_id', 'usuario_id', 'descripcion', 'ruta_foto',
    'estado', 'prioridad', 'resolucion', 'nota_comercio',
])]
class Incidencia extends Model
{
    protected $table = 'incidencias';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoIncidencia::class,
            'prioridad' => Prioridad::class,
        ];
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<TipoIncidencia, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoIncidencia::class, 'tipo_incidencia_id');
    }

    /**
     * Usuario que reportó la incidencia.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
