// Tipos de los datos que devuelve la API. Deben coincidir con los Resources del backend
// (es la misma idea que web/src/app/core/modelos.ts, con lo que usa el repartidor).

export type Rol = 'administrador' | 'operador' | 'comercio' | 'repartidor';

export type EstadoPedido = 'creado' | 'asignado' | 'recogido' | 'en_ruta' | 'entregado' | 'no_entregado' | 'cancelado';

export type Prioridad = 'baja' | 'media' | 'alta';

export type EstadoIncidencia = 'abierta' | 'en_revision' | 'resuelta';

export type TipoEvidencia = 'foto_entrega' | 'comprobante';

export type Disponibilidad = 'disponible' | 'no_disponible';

/** Referencia corta a otro registro (id + nombre). */
export interface Referencia {
  id: number;
  nombre: string;
}

export interface Usuario {
  id: number;
  nombre: string;
  correo: string;
  rol: Rol;
  activo: boolean;
  comercio_id: number | null;
  zona_id: number | null;
  disponibilidad: Disponibilidad | null;
  zona?: Referencia | null;
}

export interface RespuestaLogin {
  token: string;
  usuario: Usuario;
}

/** Respuesta de un solo registro: { data: ... } */
export interface Respuesta<T> {
  data: T;
}

/** Respuesta de una lista paginada de Laravel. */
export interface Paginado<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface LineaPedido {
  id: number;
  producto: string;
  cantidad: number;
}

export interface EventoHistorial {
  id: number;
  estado_nuevo: EstadoPedido | null;
  detalle: string | null;
  usuario: string | null;
  created_at: string;
}

/** Evidencia dentro del detalle del pedido (solo lectura). */
export interface EvidenciaPedido {
  id: number;
  tipo: TipoEvidencia;
  url: string;
  con_ubicacion: boolean;
  created_at: string;
}

/** Incidencia dentro del detalle del pedido (solo lectura; sin la nota para el comercio). */
export interface IncidenciaPedido {
  id: number;
  estado: EstadoIncidencia;
  tipo: string | null;
  descripcion: string;
  prioridad: Prioridad | null;
  foto_url: string | null;
  created_at: string;
}

export interface Pedido {
  id: number;
  numero: string;
  estado: EstadoPedido;
  prioridad: Prioridad;
  direccion: string;
  barrio: string;
  indicaciones: string | null;
  franja_inicio: string;
  franja_fin: string;
  observaciones: string | null;
  comercio?: Referencia & { direccion_recogida: string };
  cliente?: Referencia & { telefono: string };
  zona?: Referencia;
  lineas?: LineaPedido[];
  historial?: EventoHistorial[];
  evidencias?: EvidenciaPedido[];
  incidencias?: IncidenciaPedido[];
}

export interface TipoIncidencia {
  id: number;
  nombre: string;
  activo: boolean;
}

/** Respuesta de PUT /repartidores/{id}/disponibilidad. */
export interface Repartidor {
  id: number;
  disponibilidad: Disponibilidad;
}

/** Formato de error de la API: { error: { codigo, mensaje, campos } } */
export interface ErrorApi {
  error: {
    codigo: string;
    mensaje: string;
    campos: Record<string, string[]>;
  };
}
