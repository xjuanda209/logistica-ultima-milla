import { Rol } from './modelos';

export interface OpcionMenu {
  titulo: string;
  ruta: string;
  roles: Rol[];
}

const TODOS: Rol[] = ['administrador', 'operador', 'comercio', 'repartidor'];

/**
 * Opciones del menú lateral y roles que pueden ver cada una.
 * Las rutas (app.routes.ts) usan esta misma lista para protegerse con rolGuard.
 */
export const MENU: OpcionMenu[] = [
  { titulo: 'Panel', ruta: '/panel', roles: ['administrador', 'operador', 'comercio'] },
  { titulo: 'Pedidos', ruta: '/pedidos', roles: TODOS },
  { titulo: 'Clientes', ruta: '/clientes', roles: ['administrador', 'operador', 'comercio'] },
  { titulo: 'Incidencias', ruta: '/incidencias', roles: TODOS },
  { titulo: 'Despacho', ruta: '/despacho', roles: ['administrador', 'operador'] },
  { titulo: 'Administración', ruta: '/administracion', roles: ['administrador'] },
];

export function rolesDe(ruta: string): Rol[] {
  return MENU.find((opcion) => opcion.ruta === ruta)?.roles ?? [];
}
