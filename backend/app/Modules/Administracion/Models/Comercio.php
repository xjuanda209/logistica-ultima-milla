<?php

namespace App\Modules\Administracion\Models;

use App\Modules\Pedidos\Models\Cliente;
use App\Modules\Pedidos\Models\Pedido;
use Database\Factories\ComercioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'direccion_recogida', 'activo'])]
#[UseFactory(ComercioFactory::class)]
class Comercio extends Model
{
    /** @use HasFactory<ComercioFactory> */
    use HasFactory;

    protected $table = 'comercios';

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
     * @return HasMany<Usuario, $this>
     */
    public function usuarios(): HasMany
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
