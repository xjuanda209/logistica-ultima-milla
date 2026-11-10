<?php

namespace App\Shared\Errores;

use RuntimeException;

/**
 * Error de negocio que la API devuelve con el formato estándar.
 *
 * Uso: throw new ErrorApi('PEDIDO_NO_EDITABLE', 'El pedido ya fue entregado.', 409);
 */
class ErrorApi extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $campos
     */
    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly int $estadoHttp = 400,
        public readonly array $campos = [],
    ) {
        parent::__construct($mensaje);
    }
}
