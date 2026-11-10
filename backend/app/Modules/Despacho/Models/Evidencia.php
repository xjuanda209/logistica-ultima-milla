<?php

namespace App\Modules\Despacho\Models;

use App\Modules\Administracion\Models\Usuario;
use App\Modules\Pedidos\Models\Pedido;
use App\Shared\Enums\TipoEvidencia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pedido_id', 'usuario_id', 'tipo', 'ruta_archivo', 'latitud', 'longitud'])]
class Evidencia extends Model
{
    /** La tabla solo tiene created_at. */
    public const UPDATED_AT = null;

    protected $table = 'evidencias';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEvidencia::class,
            'latitud' => 'decimal:6',
            'longitud' => 'decimal:6',
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
     * Repartidor que subió la evidencia.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
