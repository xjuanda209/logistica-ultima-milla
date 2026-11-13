<?php

namespace Tests\Soporte;

use App\Modules\Asistente\IA\ClienteIA;
use App\Modules\Asistente\IA\IaNoDisponible;

/**
 * Cliente de IA para las pruebas: no llama a ningún proveedor. Devuelve la respuesta que se le
 * indique (o falla) y guarda lo que recibió para poder revisarlo.
 *
 *   $ia = ClienteIASimulado::instalar('Hay 2 pedidos en ruta.');
 *   ...
 *   expect($ia->instrucciones)->toContain('PED-0001');
 */
class ClienteIASimulado implements ClienteIA
{
    public int $llamadas = 0;

    public ?string $instrucciones = null;

    /** @var list<array{rol: string, texto: string}> */
    public array $mensajes = [];

    public function __construct(private readonly ?string $respuesta) {}

    /**
     * Lo registra en el contenedor en lugar del proveedor real. Con null, simula que la IA falla.
     */
    public static function instalar(?string $respuesta = 'Respuesta simulada.'): self
    {
        $simulado = new self($respuesta);
        app()->instance(ClienteIA::class, $simulado);

        return $simulado;
    }

    public function responder(string $instrucciones, array $mensajes): string
    {
        $this->llamadas++;
        $this->instrucciones = $instrucciones;
        $this->mensajes = $mensajes;

        return $this->respuesta ?? throw new IaNoDisponible('Falla simulada.');
    }
}
