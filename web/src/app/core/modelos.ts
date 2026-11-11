// Tipos de los datos que devuelve la API. Deben coincidir con los Resources del backend.

export type Rol = 'administrador' | 'operador' | 'comercio' | 'repartidor';

export type EstadoPedido =
  'creado' | 'asignado' | 'recogido' | 'en_ruta' | 'entregado' | 'no_entregado' | 'cancelado';

export type Prioridad = 'baja' | 'media' | 'alta';

export type EstadoIncidencia = 'abierta' | 'en_revision' | 'resuelta';

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
  created_at: string;
  updated_at: string;
  comercio?: Referencia | null;
  zona?: Referencia | null;
}

export interface Zona {
  id: number;
  nombre: string;
  descripcion: string | null;
  /** Solo con GET /zonas?con_repartidores=1 (administrador y operador). */
  repartidores?: Referencia[];
}

export interface Comercio {
  id: number;
  nombre: string;
  direccion_recogida: string;
  activo: boolean;
}

export interface TipoIncidencia {
  id: number;
  nombre: string;
  activo: boolean;
}

export interface Configuracion {
  max_pedidos_activos: number;
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
  tipo_evento: 'creacion' | 'edicion' | 'asignacion' | 'cambio_estado';
  estado_anterior: EstadoPedido | null;
  estado_nuevo: EstadoPedido | null;
  detalle: string | null;
  canal: 'web' | 'movil';
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
  created_at: string;
  comercio?: Referencia & { direccion_recogida: string };
  cliente?: Referencia & { telefono: string };
  repartidor?: Referencia | null;
  zona?: Referencia;
  lineas?: LineaPedido[];
  historial?: EventoHistorial[];
}

export interface Cliente {
  id: number;
  comercio_id: number;
  zona_id: number;
  nombre: string;
  cedula: string;
  telefono: string;
  correo: string | null;
  direccion: string;
  barrio: string;
  indicaciones: string | null;
  comercio?: Referencia;
  zona?: Referencia;
}

export interface Incidencia {
  id: number;
  descripcion: string;
  estado: EstadoIncidencia;
  prioridad: Prioridad | null;
  resolucion: string | null;
  nota_comercio: string | null;
  created_at: string;
  pedido?: { id: number; numero: string };
  tipo?: Referencia;
  usuario?: Referencia;
}

export interface ResumenPanel {
  total: number;
  por_estado: Record<EstadoPedido, number>;
}

/** Formato de error de la API: { error: { codigo, mensaje, campos } } */
export interface ErrorApi {
  error: {
    codigo: string;
    mensaje: string;
    campos: Record<string, string[]>;
  };
}
