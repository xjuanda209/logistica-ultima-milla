<?php

namespace App\Shared\Enums;

use App\Shared\Errores\ErrorApi;

/**
 * Por qué un repartidor no puede recibir un pedido (INT-09). Lo calcula
 * Usuario::motivoParaNoRecibir(); no se guarda en la base.
 */
enum MotivoNoElegible: string
{
    case YaAsignado = 'ya_asignado';
    case Desactivado = 'desactivado';
    case NoDisponible = 'no_disponible';
    case FueraDeZona = 'fuera_de_zona';
    case MaximoAlcanzado = 'maximo_alcanzado';

    /**
     * Texto corto que se muestra en rojo junto al repartidor en la ventana de asignación.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::YaAsignado => 'Ya tiene asignado este pedido',
            self::Desactivado => 'Cuenta desactivada',
            self::NoDisponible => 'No disponible',
            self::FueraDeZona => 'Fuera de la zona del pedido',
            self::MaximoAlcanzado => 'Alcanzó el máximo de pedidos activos',
        };
    }

    /**
     * Error 409 con el que se rechaza la asignación.
     */
    public function error(): ErrorApi
    {
        [$codigo, $mensaje] = match ($this) {
            self::YaAsignado => ['MISMO_REPARTIDOR', 'El pedido ya está asignado a ese repartidor.'],
            self::Desactivado => ['REPARTIDOR_DESACTIVADO', 'El repartidor no puede recibir el pedido: tiene la cuenta desactivada.'],
            self::NoDisponible => ['REPARTIDOR_NO_DISPONIBLE', 'El repartidor no puede recibir el pedido: no está disponible.'],
            self::FueraDeZona => ['REPARTIDOR_FUERA_DE_ZONA', 'El repartidor no puede recibir el pedido: está fuera de la zona del pedido.'],
            self::MaximoAlcanzado => ['REPARTIDOR_AL_MAXIMO', 'El repartidor no puede recibir el pedido: alcanzó el máximo de pedidos activos.'],
        };

        return new ErrorApi($codigo, $mensaje, 409);
    }
}
