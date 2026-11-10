<?php

namespace App\Modules\Asistente\Models;

use App\Modules\Administracion\Models\Usuario;
use App\Shared\Enums\ResultadoConsulta;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usuario_id', 'resultado'])]
class ConsultaAsistente extends Model
{
    /** La tabla solo tiene created_at. */
    public const UPDATED_AT = null;

    protected $table = 'consultas_asistente';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoConsulta::class,
        ];
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
