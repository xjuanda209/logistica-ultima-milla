import { HttpErrorResponse } from '@angular/common/http';

import { ErrorApi, EstadoIncidencia, EstadoPedido, Prioridad, Rol } from './modelos';

/** Texto y color (clase de Bootstrap) para mostrar cada valor fijo del sistema. */
interface Etiqueta {
  texto: string;
  clase: string;
}

export const ESTADOS_PEDIDO: Record<EstadoPedido, Etiqueta> = {
  creado: { texto: 'Creado', clase: 'text-bg-secondary' },
  asignado: { texto: 'Asignado', clase: 'text-bg-info' },
  recogido: { texto: 'Recogido', clase: 'text-bg-primary' },
  en_ruta: { texto: 'En ruta', clase: 'text-bg-warning' },
  entregado: { texto: 'Entregado', clase: 'text-bg-success' },
  no_entregado: { texto: 'No entregado', clase: 'text-bg-danger' },
  cancelado: { texto: 'Cancelado', clase: 'text-bg-dark' },
};

export const PRIORIDADES: Record<Prioridad, Etiqueta> = {
  baja: { texto: 'Baja', clase: 'text-bg-light' },
  media: { texto: 'Media', clase: 'text-bg-secondary' },
  alta: { texto: 'Alta', clase: 'text-bg-danger' },
};

export const ESTADOS_INCIDENCIA: Record<EstadoIncidencia, Etiqueta> = {
  abierta: { texto: 'Abierta', clase: 'text-bg-danger' },
  en_revision: { texto: 'En revisión', clase: 'text-bg-warning' },
  resuelta: { texto: 'Resuelta', clase: 'text-bg-success' },
};

export const ROLES: Record<Rol, string> = {
  administrador: 'Administrador',
  operador: 'Operador',
  comercio: 'Comercio',
  repartidor: 'Repartidor',
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
