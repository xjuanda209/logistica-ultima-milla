<?php

namespace App\Shared\Enums;

use App\Shared\Errores\ErrorApi;

/**
 * Estados de la incidencia. ESTE es el único lugar donde se define el flujo:
 * abierta → en revisión → resuelta (final). No se salta ni se devuelve.
 */
enum EstadoIncidencia: string
{
    case Abierta = 'abierta';
    case EnRevision = 'en_revision';
    case Resuelta = 'resuelta';

    /**
     * Estado al que se puede pasar desde este, o null si es final.
     */
    public function siguiente(): ?self
    {
        return match ($this) {
            self::Abierta => self::EnRevision,
            self::EnRevision => self::Resuelta,
            self::Resuelta => null,
        };
    }

    /**
     * Lanza TRANSICION_NO_PERMITIDA (409) si desde este estado no se puede pasar al nuevo.
     */
    public function exigirPasoA(self $nuevo): void
    {
        if ($this->siguiente() === $nuevo) {
            return;
        }

        throw new ErrorApi(
            'TRANSICION_NO_PERMITIDA',
            "La incidencia está «{$this->etiqueta()}» y no puede pasar a «{$nuevo->etiqueta()}».",
            409,
        );
    }

    /**
     * Nombre para los mensajes de error y para el asistente.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierta => 'Abierta',
            self::EnRevision => 'En revisión',
            self::Resuelta => 'Resuelta',
        };
    }
}
