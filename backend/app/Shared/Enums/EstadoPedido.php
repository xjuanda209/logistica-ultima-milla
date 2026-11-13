<?php

namespace App\Shared\Enums;

use App\Shared\Errores\ErrorApi;

/**
 * Estados del pedido y su máquina de estados. ESTE es el único lugar donde se define qué cambio
 * de estado se permite: todo cambio pasa por Pedido::cambiarEstado(), que consulta aquí.
 */
enum EstadoPedido: string
{
    case Creado = 'creado';
    case Asignado = 'asignado';
    case Recogido = 'recogido';
    case EnRuta = 'en_ruta';
    case Entregado = 'entregado';
    case NoEntregado = 'no_entregado';
    case Cancelado = 'cancelado';

    /**
     * Estados a los que se puede pasar desde este. Asignado → Asignado es la reasignación.
     * Entregado, no entregado y cancelado son finales: no salen a ninguno.
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Creado => [self::Asignado, self::Cancelado],
            self::Asignado => [self::Asignado, self::Recogido, self::Cancelado],
            self::Recogido => [self::EnRuta],
            self::EnRuta => [self::Entregado, self::NoEntregado],
            self::Entregado, self::NoEntregado, self::Cancelado => [],
        };
    }

    public function puedePasarA(self $nuevo): bool
    {
        return in_array($nuevo, $this->siguientes(), true);
    }

    public function esFinal(): bool
    {
        return $this->siguientes() === [];
    }

    /**
     * Lanza el error TRANSICION_NO_PERMITIDA (409) si desde este estado no se puede pasar al nuevo.
     */
    public function exigirPasoA(self $nuevo): void
    {
        if ($this->puedePasarA($nuevo)) {
            return;
        }

        $mensaje = match ($nuevo) {
            self::Cancelado => "No se puede cancelar: el pedido está «{$this->etiqueta()}».",
            self::Asignado => "No se puede asignar: el pedido está «{$this->etiqueta()}».",
            default => "El pedido está «{$this->etiqueta()}» y no puede pasar a «{$nuevo->etiqueta()}».",
        };

        throw new ErrorApi('TRANSICION_NO_PERMITIDA', $mensaje, 409);
    }

    /**
     * Estados en los que el pedido ocupa cupo del repartidor (cuentan para max_pedidos_activos).
     *
     * @return list<self>
     */
    public static function activos(): array
    {
        return [self::Asignado, self::Recogido, self::EnRuta];
    }

    /**
     * Nombre para los mensajes de error (los de la interfaz están en la web y el móvil).
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Asignado => 'Asignado',
            self::Recogido => 'Recogido',
            self::EnRuta => 'En ruta',
            self::Entregado => 'Entregado',
            self::NoEntregado => 'No entregado',
            self::Cancelado => 'Cancelado',
        };
    }
}
