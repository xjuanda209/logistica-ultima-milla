<?php

namespace App\Modules\Asistente\IA;

/**
 * Puerta única hacia el proveedor de IA. El resto del sistema solo conoce esta interfaz:
 * el proveedor se elige en el .env (IA_PROVEEDOR=gemini|groq, IA_MODELO, IA_API_KEY) y se
 * conecta en AppServiceProvider. Las pruebas usan Tests\Soporte\ClienteIASimulado.
 */
interface ClienteIA
{
    /** Rol de un mensaje escrito por la persona. */
    public const USUARIO = 'usuario';

    /** Rol de una respuesta anterior del asistente. */
    public const ASISTENTE = 'asistente';

    /**
     * Envía la conversación y devuelve el texto de la respuesta.
     *
     * @param  string  $instrucciones  Reglas del asistente y datos de la operación (ya anonimizados).
     * @param  list<array{rol: string, texto: string}>  $mensajes  Conversación en orden; el último es la pregunta.
     *
     * @throws IaNoDisponible si el proveedor falla, responde 429, tarda más del límite o no devuelve texto.
     */
    public function responder(string $instrucciones, array $mensajes): string;
}
