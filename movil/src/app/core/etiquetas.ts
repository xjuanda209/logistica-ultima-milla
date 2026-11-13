import { HttpErrorResponse } from '@angular/common/http';

import type { ErrorApi, EstadoIncidencia, EstadoPedido, Prioridad, Rol, TipoEvidencia } from './modelos';

/**
 * Texto y colores de cada valor fijo del sistema: los mismos que la web (web/src/app/core/etiquetas.ts
 * y docs/interfaces/README.md, "Badges de estado"). <app-insignia> lee de aquí.
 */
export interface Etiqueta {
  texto: string;
  /** Fondo suave de la insignia. */
  fondo: string;
  /** Texto del mismo tono, con contraste suficiente sobre el fondo. */
  color: string;
}

const GRIS: Omit<Etiqueta, 'texto'> = { fondo: '#e8edf1', color: '#4a5a66' };
const GRIS_OSCURO: Omit<Etiqueta, 'texto'> = { fondo: '#e2e5e8', color: '#2f3b44' };
const AZUL: Omit<Etiqueta, 'texto'> = { fondo: '#e4eef9', color: '#2b6cb0' };
const MORADO: Omit<Etiqueta, 'texto'> = { fondo: '#ede7f8', color: '#6b46c1' };
const AMBAR: Omit<Etiqueta, 'texto'> = { fondo: '#fcf0d9', color: '#8a5208' };
const VERDE: Omit<Etiqueta, 'texto'> = { fondo: '#e3f4ea', color: '#276749' };
const ROJO: Omit<Etiqueta, 'texto'> = { fondo: '#fde8e8', color: '#c53030' };

export const ESTADOS_PEDIDO: Record<EstadoPedido, Etiqueta> = {
  creado: { texto: 'Creado', ...GRIS },
  asignado: { texto: 'Asignado', ...AZUL },
  recogido: { texto: 'Recogido', ...MORADO },
  en_ruta: { texto: 'En ruta', ...AMBAR },
  entregado: { texto: 'Entregado', ...VERDE },
  no_entregado: { texto: 'No entregado', ...ROJO },
  cancelado: { texto: 'Cancelado', ...GRIS_OSCURO },
};

export const PRIORIDADES: Record<Prioridad, Etiqueta> = {
  baja: { texto: 'Baja', ...GRIS },
  media: { texto: 'Media', ...AMBAR },
  alta: { texto: 'Alta', ...ROJO },
};

export const ESTADOS_INCIDENCIA: Record<EstadoIncidencia, Etiqueta> = {
  abierta: { texto: 'Abierta', ...ROJO },
  en_revision: { texto: 'En revisión', ...AMBAR },
  resuelta: { texto: 'Resuelta', ...VERDE },
};

/** Qué catálogo de etiquetas usa cada tipo de insignia. */
export const INSIGNIAS = {
  pedido: ESTADOS_PEDIDO,
  prioridad: PRIORIDADES,
  incidencia: ESTADOS_INCIDENCIA,
} as const;

export type TipoInsignia = keyof typeof INSIGNIAS;

export const TIPOS_EVIDENCIA: Record<TipoEvidencia, string> = {
  foto_entrega: 'Foto de la entrega',
  comprobante: 'Comprobante',
};

export const ROLES: Record<Rol, string> = {
  administrador: 'Administrador',
  operador: 'Operador',
  comercio: 'Comercio',
  repartidor: 'Repartidor',
};

/** Mensaje cuando la API no responde (sin conexión o servidor apagado). */
export const MENSAJE_SIN_CONEXION = 'Sin conexión. No se pudo contactar al servidor.';

/** true si la petición no llegó al servidor (sin datos, sin wifi o backend apagado). */
export function esSinConexion(error: unknown): boolean {
  return error instanceof HttpErrorResponse && error.status === 0;
}

/** Saca el mensaje legible de un error HTTP con el formato de la API. Nunca muestra textos técnicos. */
export function mensajeDeError(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    if (error.status === 0) {
      return MENSAJE_SIN_CONEXION;
    }

    const cuerpo = error.error as ErrorApi | null;
    if (cuerpo?.error?.mensaje) {
      return cuerpo.error.mensaje;
    }
  }

  return 'Ocurrió un error inesperado.';
}

/** Código del error de la API ("VALIDACION", "FALTA_EVIDENCIA"…) o null si no lo hay. */
export function codigoDeError(error: unknown): string | null {
  if (error instanceof HttpErrorResponse) {
    return (error.error as ErrorApi | null)?.error?.codigo ?? null;
  }

  return null;
}

/** Errores de validación por campo (error.campos) para pintarlos junto a cada campo. */
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

/**
 * Mensaje general de un formulario que falló (va en un aviso dentro del formulario). Si la API
 * devolvió errores por campo, no hace falta: cada error ya aparece debajo de su campo.
 */
export function mensajeDeFormulario(error: unknown): string | null {
  return Object.keys(camposDeError(error)).length > 0 ? null : mensajeDeError(error);
}
