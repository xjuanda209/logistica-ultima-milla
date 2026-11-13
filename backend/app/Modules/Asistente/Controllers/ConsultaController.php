<?php

namespace App\Modules\Asistente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administracion\Models\Usuario;
use App\Modules\Asistente\IA\ClienteIA;
use App\Modules\Asistente\IA\ContextoOperacion;
use App\Modules\Asistente\IA\IaNoDisponible;
use App\Modules\Asistente\Models\ConsultaAsistente;
use App\Modules\Asistente\Requests\ConsultarAsistenteRequest;
use App\Modules\Incidencias\Models\Incidencia;
use App\Shared\Enums\ResultadoConsulta;
use App\Shared\Errores\ErrorApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Asistente de IA (INT-14): solo informa. Lee los datos que el usuario puede ver, los anonimiza
 * (ContextoOperacion) y se los pasa a la IA junto con la pregunta. Nunca modifica datos.
 * Cada consulta queda en consultas_asistente como respondida o fallida.
 */
class ConsultaController extends Controller
{
    /**
     * POST /api/asistente/consultas — responde { "data": { "respuesta": "..." } }.
     * Si no hay datos sobre lo que se pregunta responde "No se encontró información…" sin
     * llamar a la IA. Si la IA falla o tarda más de 30 s: 503 ASISTENTE_NO_DISPONIBLE.
     */
    public function store(ConsultarAsistenteRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $pregunta = $request->validated('pregunta');
        $datos = ContextoOperacion::general($usuario, $pregunta);

        if ($datos === null) {
            return $this->respondida($usuario, ContextoOperacion::MENSAJE_SIN_DATOS);
        }

        return $this->preguntar($usuario, $datos, [
            ...$request->validated('historial') ?? [],
            ['rol' => ClienteIA::USUARIO, 'texto' => $pregunta],
        ]);
    }

    /**
     * POST /api/incidencias/{id}/resumen — "Resumir con el asistente" (INT-13).
     */
    public function resumirIncidencia(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();
        $incidencia = Incidencia::query()->visiblesPara($usuario)->findOrFail($id);

        return $this->preguntar($usuario, ContextoOperacion::deIncidencia($incidencia), [[
            'rol' => ClienteIA::USUARIO,
            'texto' => "Resume la incidencia #{$incidencia->id} en máximo cuatro frases: qué pasó, en qué estado está "
                .'y qué se ha hecho. No recomiendes decisiones: la decisión sobre el pedido la toma el usuario.',
        ]]);
    }

    /**
     * @param  list<array{rol: string, texto: string}>  $mensajes
     */
    private function preguntar(Usuario $usuario, string $datos, array $mensajes): JsonResponse
    {
        // El límite de 30 s es del cliente de IA; PHP no debe cortar la petición antes de que responda.
        set_time_limit(config('services.ia.tiempo_limite') + 15);

        try {
            $respuesta = app(ClienteIA::class)->responder(ContextoOperacion::instrucciones($datos), $mensajes);
        } catch (IaNoDisponible $e) {
            Log::warning('El asistente de IA no está disponible.', ['motivo' => $e->getMessage()]);
            ConsultaAsistente::query()->create(['usuario_id' => $usuario->id, 'resultado' => ResultadoConsulta::Fallida]);

            throw new ErrorApi('ASISTENTE_NO_DISPONIBLE', 'El asistente no está disponible.', 503);
        }

        return $this->respondida($usuario, $respuesta);
    }

    private function respondida(Usuario $usuario, string $respuesta): JsonResponse
    {
        ConsultaAsistente::query()->create(['usuario_id' => $usuario->id, 'resultado' => ResultadoConsulta::Respondida]);

        return response()->json(['data' => ['respuesta' => $respuesta]]);
    }
}
