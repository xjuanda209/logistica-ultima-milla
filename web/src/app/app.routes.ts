import { inject } from '@angular/core';
import { Routes } from '@angular/router';

import { AuthService } from './core/auth.service';
import { authGuard, invitadoGuard, rolGuard } from './core/guards';
import { rolesDe } from './core/menu';

/**
 * Para agregar una página nueva:
 *  1. Crear el componente en features/<modulo>/.
 *  2. Agregar la opción en core/menu.ts (título, ruta y roles).
 *  3. Agregar aquí la ruta hija con canActivate: [rolGuard] y data: { roles: rolesDe('/ruta') }.
 */
export const routes: Routes = [
  {
    path: 'login',
    canActivate: [invitadoGuard],
    loadComponent: () => import('./features/login/login').then((m) => m.Login),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/layout').then((m) => m.Layout),
    children: [
      { path: '', pathMatch: 'full', redirectTo: () => inject(AuthService).rutaInicial() },
      {
        path: 'panel',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/panel') },
        loadComponent: () => import('./features/panel/panel').then((m) => m.Panel),
      },
      {
        path: 'pedidos',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/pedidos') },
        loadComponent: () => import('./features/pedidos/pedidos-lista').then((m) => m.PedidosLista),
      },
      {
        path: 'pedidos/:id',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/pedidos') },
        loadComponent: () =>
          import('./features/pedidos/pedido-detalle').then((m) => m.PedidoDetalle),
      },
      {
        path: 'clientes',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/clientes') },
        loadComponent: () => import('./features/clientes/clientes').then((m) => m.Clientes),
      },
      {
        path: 'incidencias',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/incidencias') },
        loadComponent: () =>
          import('./features/incidencias/incidencias').then((m) => m.Incidencias),
      },
      {
        path: 'despacho',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/despacho') },
        loadComponent: () => import('./features/despacho/despacho').then((m) => m.Despacho),
      },
      {
        path: 'administracion',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/administracion') },
        loadComponent: () =>
          import('./features/administracion/administracion').then((m) => m.Administracion),
        // Las páginas hijas heredan el rolGuard del padre.
        children: [
          { path: '', pathMatch: 'full', redirectTo: 'usuarios' },
          {
            path: 'usuarios',
            loadComponent: () =>
              import('./features/administracion/usuarios').then((m) => m.Usuarios),
          },
          {
            path: 'zonas',
            loadComponent: () => import('./features/administracion/zonas').then((m) => m.Zonas),
          },
          {
            path: 'tipos-incidencia',
            loadComponent: () =>
              import('./features/administracion/tipos-incidencia').then((m) => m.TiposIncidencia),
          },
          {
            path: 'configuracion',
            loadComponent: () =>
              import('./features/administracion/configuracion').then((m) => m.Configuracion),
          },
        ],
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
