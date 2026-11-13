// Filtros por fecha: la API recibe instantes en ISO (UTC); la persona elige días de su dispositivo.

/** "2026-11-01" (día del dispositivo) → instante en que empieza ese día, en ISO (UTC). */
export function inicioDelDia(dia: string | undefined): string | null {
  return aIso(dia, 'T00:00:00');
}

/** "2026-11-09" → último instante de ese día en el dispositivo, en ISO (UTC). */
export function finDelDia(dia: string | undefined): string | null {
  return aIso(dia, 'T23:59:59.999');
}

function aIso(dia: string | undefined, hora: string): string | null {
  if (!dia || !/^\d{4}-\d{2}-\d{2}$/.test(dia)) {
    return null;
  }

  const fecha = new Date(dia + hora);

  return Number.isNaN(fecha.getTime()) ? null : fecha.toISOString();
}
