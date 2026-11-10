<?php

namespace App\Shared\Enums;

enum EstadoIncidencia: string
{
    case Abierta = 'abierta';
    case EnRevision = 'en_revision';
    case Resuelta = 'resuelta';
}
