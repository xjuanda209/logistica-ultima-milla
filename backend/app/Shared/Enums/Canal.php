<?php

namespace App\Shared\Enums;

use Illuminate\Http\Request;

enum Canal: string
{
    case Web = 'web';
    case Movil = 'movil';

    /**
     * Canal desde el que llega la petición, para registrarlo en historial_pedido.
     * La app móvil lo indica con el encabezado "X-Canal: movil"; sin encabezado es la web.
     */
    public static function deLaPeticion(Request $request): self
    {
        return self::tryFrom((string) $request->header('X-Canal')) ?? self::Web;
    }
}
