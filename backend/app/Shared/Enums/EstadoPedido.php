<?php

namespace App\Shared\Enums;

enum EstadoPedido: string
{
    case Creado = 'creado';
    case Asignado = 'asignado';
    case Recogido = 'recogido';
    case EnRuta = 'en_ruta';
    case Entregado = 'entregado';
    case NoEntregado = 'no_entregado';
    case Cancelado = 'cancelado';
}
