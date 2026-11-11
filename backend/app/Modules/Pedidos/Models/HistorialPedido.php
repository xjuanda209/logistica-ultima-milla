<?php

namespace App\Modules\Pedidos\Models;

use App\Modules\Administracion\Models\Usuario;
use App\Shared\Enums\Canal;
use App\Shared\Enums\EstadoPedido;
use App\Shared\Enums\TipoEvento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora del pedido. Solo se insertan filas: nunca se editan ni se borran.
 */
#[Fillable(['pedido_id', 'usuario_id', 'tipo_evento', 'estado_anterior', 'estado_nuevo', 'detalle', 'canal', 'created_at'])]
class HistorialPedido extends Model
{
    /** La tabla solo tiene created_at. */
    public const UPDATED_AT = null;

    protected $table = 'historial_pedido';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_evento' => TipoEvento::class,
            'estado_anterior' => EstadoPedido::class,
            'estado_nuevo' => EstadoPedido::class,
            'canal' => Canal::class,
        ];
    }

    /**
     * En un evento "edicion" el detalle guarda, como JSON, los campos que cambiaron:
     *   {"cambios": [{"campo": "prioridad", "anterior": "media", "nuevo": "alta"}]}
     * Las fechas van en ISO (UTC). En los demás eventos el detalle es texto libre y esto da null.
     *
     * @return list<array{campo: string, anterior: ?string, nuevo: ?string}>|null
     */
    public function cambios(): ?array
    {
        if ($this->tipo_evento !== TipoEvento::Edicion || $this->detalle === null) {
            return null;
        }

        $detalle = json_decode($this->detalle, true);
        $cambios = is_array($detalle) ? ($detalle['cambios'] ?? null) : null;

        return is_array($cambios) ? $cambios : null;
    }

    /**
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
