import { inject } from '@angular/core';
import { Routes } from '@angular/router';

import { AuthService } from './core/auth.service';
import { authGuard, invitadoGuard, rolGuard } from './core/guards';
import { rolesDe } from './core/menu';

const enConstruccion = () =>
  import('./features/en-construccion/en-construccion').then((m) => m.EnConstruccion);

/**
 * Para agregar una página nueva:
 *  1. Crear el componente en features/<modulo>/ siguiendo su mockup (docs/interfaces).
 *  2. Agregar la opción en core/menu.ts (título, ruta y roles).
 *  3. Agregar aquí la ruta hija con `title` (sale en la barra superior), canActivate: [rolGuard]
 *     y data: { roles: rolesDe('/ruta') }.
 */
export const routes: Routes = [
  {
    path: 'login',
    title: 'Iniciar sesión',
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
        title: 'Panel operativo e indicadores',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/panel') },
        loadComponent: () => import('./features/panel/panel').then((m) => m.Panel),
      },
      {
        path: 'usuarios',
        title: 'Gestión de usuarios',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/usuarios') },
        loadComponent: () =>
          import('./features/administracion/usuarios').then((m) => m.Usuarios),
      },
      {
        path: 'parametrizacion',
        title: 'Parametrización del sistema',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/parametrizacion') },
        loadComponent: () =>
          import('./features/administracion/parametrizacion').then((m) => m.Parametrizacion),
      },
      {
        path: 'pedidos',
        title: 'Pedidos',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/pedidos') },
        loadComponent: () => import('./features/pedidos/pedidos-lista').then((m) => m.PedidosLista),
      },
      {
        path: 'pedidos/:id',
        title: 'Detalle del pedido',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/pedidos') },
        loadComponent: () =>
          import('./features/pedidos/pedido-detalle').then((m) => m.PedidoDetalle),
      },
      {
        path: 'clientes',
        title: 'Clientes',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/clientes') },
        loadComponent: () => import('./features/clientes/clientes').then((m) => m.Clientes),
      },
      {
        path: 'repartidores',
        title: 'Repartidores',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/repartidores'), modulo: 'el módulo 3 (Despacho)' },
        loadComponent: enConstruccion,
      },
      {
        path: 'incidencias',
        title: 'Incidencias',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/incidencias') },
        loadComponent: () =>
          import('./features/incidencias/incidencias').then((m) => m.Incidencias),
      },
      {
        path: 'asistente',
        title: 'Asistente IA',
        canActivate: [rolGuard],
        data: { roles: rolesDe('/asistente'), modulo: 'el módulo 5 (Incidencias)' },
        loadComponent: enConstruccion,
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
