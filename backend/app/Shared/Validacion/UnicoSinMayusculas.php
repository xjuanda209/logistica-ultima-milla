<?php

namespace App\Shared\Validacion;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Como la regla "unique", pero sin distinguir mayúsculas de minúsculas:
 * si existe "Centro", rechaza "centro" y "CENTRO".
 *
 * Uso en un Form Request (el id de la ruta se ignora para poder editar el propio registro):
 *   new UnicoSinMayusculas('zonas', 'nombre', $this->route('id'), 'Ya existe una zona con ese nombre.')
 */
class UnicoSinMayusculas implements ValidationRule
{
    public function __construct(
        private readonly string $tabla,
        private readonly string $columna,
        private readonly mixed $ignorarId,
        private readonly string $mensaje,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $columna = DB::connection()->getQueryGrammar()->wrap($this->columna);

        $existe = DB::table($this->tabla)
            ->whereRaw("lower({$columna}) = ?", [mb_strtolower($value)])
            ->when($this->ignorarId !== null, fn ($query) => $query->where('id', '!=', $this->ignorarId))
            ->exists();

        if ($existe) {
            $fail($this->mensaje);
        }
    }
}
