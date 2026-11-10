<?php

namespace App\Modules\Administracion\Models;

use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\Pedido;
use Database\Factories\ZonaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'descripcion'])]
#[UseFactory(ZonaFactory::class)]
class Zona extends Model
{
    /** @use HasFactory<ZonaFactory> */
    use HasFactory;

    protected $table = 'zonas';

    public $timestamps = false;

    /**
     * @return HasMany<Usuario, $this>
     */
    public function repartidores(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    /**
     * @return HasMany<Cliente, $this>
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /**
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
