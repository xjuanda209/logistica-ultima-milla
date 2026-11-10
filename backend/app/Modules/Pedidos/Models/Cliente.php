<?php

namespace App\Modules\Pedidos\Models;

use App\Modules\Administracion\Models\Comercio;
use App\Modules\Administracion\Models\Zona;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['comercio_id', 'nombre', 'cedula', 'telefono', 'correo', 'direccion', 'barrio', 'indicaciones', 'zona_id'])]
#[UseFactory(ClienteFactory::class)]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    protected $table = 'clientes';

    /**
     * @return BelongsTo<Comercio, $this>
     */
    public function comercio(): BelongsTo
    {
        return $this->belongsTo(Comercio::class);
    }

    /**
     * @return BelongsTo<Zona, $this>
     */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
