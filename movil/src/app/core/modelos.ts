// Tipos de los datos que devuelve la API. Deben coincidir con los Resources del backend
// (es la misma idea que web/src/app/core/modelos.ts, con lo que usa el repartidor).

export type Rol = 'administrador' | 'operador' | 'comercio' | 'repartidor';

export type EstadoPedido = 'creado' | 'asignado' | 'recogido' | 'en_ruta' | 'entregado' | 'no_entregado' | 'cancelado';

export type Prioridad = 'baja' | 'media' | 'alta';

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
  disponibilidad: 'disponible' | 'no_disponible' | null;
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
}

/** Formato de error de la API: { error: { codigo, mensaje, campos } } */
export interface ErrorApi {
  error: {
    codigo: string;
    mensaje: string;
    campos: Record<string, string[]>;
  };
}
