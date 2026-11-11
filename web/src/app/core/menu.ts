import { Rol } from './modelos';

export interface OpcionMenu {
  titulo: string;
  ruta: string;
  roles: Rol[];
}

/**
 * Opciones del menú lateral, en el orden en que se muestran, y roles que pueden ver cada una
 * (docs/interfaces/README.md, "Menú lateral por rol"). Cada rol ve solo lo suyo:
 *   administrador: Panel, Usuarios, Parametrización, Pedidos, Clientes, Incidencias, Asistente IA
 *   operador:      Panel, Pedidos, Clientes, Repartidores, Incidencias, Asistente IA
 *   comercio:      Pedidos, Clientes, Incidencias
 *   repartidor:    trabaja en la app móvil; en la web solo consulta Pedidos e Incidencias.
 * Las rutas (app.routes.ts) usan esta misma lista para protegerse con rolGuard.
 */
export const MENU: OpcionMenu[] = [
  { titulo: 'Panel', ruta: '/panel', roles: ['administrador', 'operador'] },
  { titulo: 'Usuarios', ruta: '/usuarios', roles: ['administrador'] },
  { titulo: 'Parametrización', ruta: '/parametrizacion', roles: ['administrador'] },
  {
    titulo: 'Pedidos',
    ruta: '/pedidos',
    roles: ['administrador', 'operador', 'comercio', 'repartidor'],
  },
  { titulo: 'Clientes', ruta: '/clientes', roles: ['administrador', 'operador', 'comercio'] },
  { titulo: 'Repartidores', ruta: '/repartidores', roles: ['operador'] },
  {
    titulo: 'Incidencias',
    ruta: '/incidencias',
    roles: ['administrador', 'operador', 'comercio', 'repartidor'],
  },
  { titulo: 'Asistente IA', ruta: '/asistente', roles: ['administrador', 'operador'] },
];

export function rolesDe(ruta: string): Rol[] {
  return MENU.find((opcion) => opcion.ruta === ruta)?.roles ?? [];
}
