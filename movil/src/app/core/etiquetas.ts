import { HttpErrorResponse } from '@angular/common/http';

import type { ErrorApi, EstadoPedido, Prioridad } from './modelos';

/** Texto y color de Ionic para mostrar cada valor fijo del sistema. */
interface Etiqueta {
  texto: string;
  color: string;
}

export const ESTADOS_PEDIDO: Record<EstadoPedido, Etiqueta> = {
  creado: { texto: 'Creado', color: 'medium' },
  asignado: { texto: 'Asignado', color: 'tertiary' },
  recogido: { texto: 'Recogido', color: 'primary' },
  en_ruta: { texto: 'En ruta', color: 'warning' },
  entregado: { texto: 'Entregado', color: 'success' },
  no_entregado: { texto: 'No entregado', color: 'danger' },
  cancelado: { texto: 'Cancelado', color: 'dark' },
};

export const PRIORIDADES: Record<Prioridad, Etiqueta> = {
  baja: { texto: 'Baja', color: 'medium' },
  media: { texto: 'Media', color: 'primary' },
  alta: { texto: 'Alta', color: 'danger' },
};

/** Saca el mensaje legible de un error HTTP con el formato de la API. */
export function mensajeDeError(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    if (error.status === 0) {
      return 'No se pudo conectar con el servidor. Verifica que el backend esté corriendo.';
    }

    const cuerpo = error.error as ErrorApi | null;
    if (cuerpo?.error?.mensaje) {
      return cuerpo.error.mensaje;
    }
  }

  return 'Ocurrió un error inesperado.';
}
