import { HttpErrorResponse } from '@angular/common/http';

import { ErrorApi, EstadoIncidencia, EstadoPedido, Prioridad, Rol } from './modelos';

/**
 * Texto y color de cada valor fijo del sistema. ESTE es el único lugar donde se definen:
 * las insignias (app-insignia, app-badge-estado) y el gráfico del panel leen de aquí.
 */
export interface Etiqueta {
  texto: string;
  /** Color base en hexadecimal. La insignia deriva de él su fondo claro y su texto oscuro. */
  color: string;
  /** Clase de Bootstrap Icons, para donde el valor se muestra con ícono. */
  icono?: string;
}

// Colores de apoyo (los mismos valores del tema: ver estilos/_variables.scss).
const GRIS = '#64748b';
const GRIS_OSCURO = '#475569';
const VERDE = '#047857';
const ROJO = '#dc2626';
const NARANJA = '#f28c28';

/** Un color por estado, en el orden del flujo del pedido. */
export const ESTADOS_PEDIDO: Record<EstadoPedido, Etiqueta> = {
  creado: { texto: 'Creado', color: GRIS, icono: 'bi-plus-circle' },
  asignado: { texto: 'Asignado', color: '#7c3aed', icono: 'bi-person-check' },
  recogido: { texto: 'Recogido', color: '#0891b2', icono: 'bi-box-seam' },
  en_ruta: { texto: 'En ruta', color: NARANJA, icono: 'bi-truck' },
  entregado: { texto: 'Entregado', color: VERDE, icono: 'bi-check-circle' },
  no_entregado: { texto: 'No entregado', color: ROJO, icono: 'bi-x-circle' },
  cancelado: { texto: 'Cancelado', color: GRIS_OSCURO, icono: 'bi-slash-circle' },
};

export const PRIORIDADES: Record<Prioridad, Etiqueta> = {
  baja: { texto: 'Baja', color: GRIS },
  media: { texto: 'Media', color: '#0891b2' },
  alta: { texto: 'Alta', color: ROJO },
};

export const ESTADOS_INCIDENCIA: Record<EstadoIncidencia, Etiqueta> = {
  abierta: { texto: 'Abierta', color: ROJO },
  en_revision: { texto: 'En revisión', color: NARANJA },
  resuelta: { texto: 'Resuelta', color: VERDE },
};

/** Para registros que se activan y desactivan (usuarios, tipos de incidencia…). */
export const ACTIVO: Record<'activo' | 'inactivo', Etiqueta> = {
  activo: { texto: 'Activo', color: VERDE },
  inactivo: { texto: 'Inactivo', color: GRIS },
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

/** Errores de validación por campo (error.campos) para pintarlos junto a cada input. */
export type ErroresDeCampos = Record<string, string[] | undefined>;

/** Saca los errores por campo de un error 422 de la API. Si no hay, devuelve {}. */
export function camposDeError(error: unknown): ErroresDeCampos {
  if (error instanceof HttpErrorResponse) {
    const campos = (error.error as ErrorApi | null)?.error?.campos;

    if (campos && !Array.isArray(campos)) {
      return campos;
    }
  }

  return {};
}
