<?php

namespace App\Shared\Enums;

enum Rol: string
{
    case Administrador = 'administrador';
    case Operador = 'operador';
    case Comercio = 'comercio';
    case Repartidor = 'repartidor';
}
