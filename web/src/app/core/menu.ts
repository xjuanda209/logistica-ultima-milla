import { Rol } from './modelos';

export interface OpcionMenu {
  titulo: string;
  ruta: string;
  /** Clase de Bootstrap Icons (https://icons.getbootstrap.com), por ejemplo "bi-box-seam". */
  icono: string;
  roles: Rol[];
}

const TODOS: Rol[] = ['administrador', 'operador', 'comercio', 'repartidor'];

/**
 * Opciones del menú lateral y roles que pueden ver cada una.
 * Las rutas (app.routes.ts) usan esta misma lista para protegerse con rolGuard.
 */
export const MENU: OpcionMenu[] = [
  {
    titulo: 'Panel',
    ruta: '/panel',
    icono: 'bi-grid-1x2',
    roles: ['administrador', 'operador', 'comercio'],
  },
  { titulo: 'Pedidos', ruta: '/pedidos', icono: 'bi-box-seam', roles: TODOS },
  {
    titulo: 'Clientes',
    ruta: '/clientes',
    icono: 'bi-people',
    roles: ['administrador', 'operador', 'comercio'],
  },
  { titulo: 'Incidencias', ruta: '/incidencias', icono: 'bi-exclamation-triangle', roles: TODOS },
  { titulo: 'Despacho', ruta: '/despacho', icono: 'bi-truck', roles: ['administrador', 'operador'] },
  { titulo: 'Administración', ruta: '/administracion', icono: 'bi-gear', roles: ['administrador'] },
];

export function rolesDe(ruta: string): Rol[] {
  return MENU.find((opcion) => opcion.ruta === ruta)?.roles ?? [];
}
