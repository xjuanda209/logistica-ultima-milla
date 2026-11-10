<?php

namespace App\Shared\Enums;

enum TipoEvento: string
{
    case Creacion = 'creacion';
    case Edicion = 'edicion';
    case Asignacion = 'asignacion';
    case CambioEstado = 'cambio_estado';
}
