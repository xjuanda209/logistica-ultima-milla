import { HttpErrorResponse } from '@angular/common/http';

import { ErrorApi, EstadoIncidencia, EstadoPedido, Prioridad, Rol } from './modelos';

/**
 * Texto y colores de cada valor fijo del sistema. ESTE es el único lugar donde se definen
 * (docs/interfaces/README.md, "Badges de estado"): <app-badge-estado> y el panel leen de aquí.
 * Los nombres de los estados son fijos: no se editan desde la aplicación.
 */
export interface Etiqueta {
  texto: string;
  /** Fondo suave de la insignia. */
  fondo: string;
  /** Texto del mismo tono, con contraste suficiente sobre el fondo. */
  color: string;
}

// Tonos de los mockups: fondo suave + texto del mismo color.
const GRIS: Omit<Etiqueta, 'texto'> = { fondo: '#e8edf1', color: '#4a5a66' };
const GRIS_OSCURO: Omit<Etiqueta, 'texto'> = { fondo: '#e2e5e8', color: '#2f3b44' };
const AZUL: Omit<Etiqueta, 'texto'> = { fondo: '#e4eef9', color: '#2b6cb0' };
const MORADO: Omit<Etiqueta, 'texto'> = { fondo: '#ede7f8', color: '#6b46c1' };
const AMBAR: Omit<Etiqueta, 'texto'> = { fondo: '#fcf0d9', color: '#8a5208' };
const VERDE: Omit<Etiqueta, 'texto'> = { fondo: '#e3f4ea', color: '#276749' };
const ROJO: Omit<Etiqueta, 'texto'> = { fondo: '#fde8e8', color: '#c53030' };

/** Estados del pedido, en el orden del flujo. */
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

/** Registros que se activan y desactivan (usuarios, tipos de incidencia…). */
export const ACTIVO: Record<'activo' | 'inactivo', Etiqueta> = {
  activo: { texto: 'Activo', ...VERDE },
  inactivo: { texto: 'Inactivo', ...GRIS_OSCURO },
};

/** Qué catálogo de etiquetas usa cada tipo de badge. */
export const BADGES = {
  pedido: ESTADOS_PEDIDO,
  prioridad: PRIORIDADES,
  incidencia: ESTADOS_INCIDENCIA,
  activo: ACTIVO,
} as const;

export type TipoBadge = keyof typeof BADGES;

/** Busca la etiqueta de un valor. Para 'activo' acepta true / false. */
export function etiquetaDe(
  tipo: TipoBadge,
  valor: string | boolean | null | undefined,
): Etiqueta | null {
  if (valor === null || valor === undefined || valor === '') {
    return null;
  }

  const clave = typeof valor === 'boolean' ? (valor ? 'activo' : 'inactivo') : valor;

  return (BADGES[tipo] as Record<string, Etiqueta>)[clave] ?? null;
}

export const ROLES: Record<Rol, string> = {
  administrador: 'Administrador',
  operador: 'Operador',
  comercio: 'Comercio',
  repartidor: 'Repartidor',
};

/** Mensaje cuando la API no responde (sin conexión o servidor apagado). */
export const MENSAJE_SIN_CONEXION = 'No se pudo contactar al servidor.';

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

/** Código del error de la API ("VALIDACION", "NO_PUEDES_DESACTIVARTE"…) o null si no lo hay. */
export function codigoDeError(error: unknown): string | null {
  if (error instanceof HttpErrorResponse) {
    return (error.error as ErrorApi | null)?.error?.codigo ?? null;
  }

  return null;
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
