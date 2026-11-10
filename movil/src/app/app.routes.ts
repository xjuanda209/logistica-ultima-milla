import type { Routes } from '@angular/router';

import { authGuard, invitadoGuard } from './core/guards';

/**
 * Para agregar una pantalla nueva: crear la página (carpeta propia, archivo *.page.ts)
 * y registrarla aquí, dentro de "tabs" si debe mostrar las pestañas.
 */
export const routes: Routes = [
  {
    path: 'login',
    canActivate: [invitadoGuard],
    loadComponent: () => import('./login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'tabs',
    canActivate: [authGuard],
    loadComponent: () => import('./tabs/tabs.page').then((m) => m.TabsPage),
    children: [
      {
        path: 'pedidos',
        loadComponent: () => import('./pedidos/pedidos.page').then((m) => m.PedidosPage),
      },
      {
        path: 'pedidos/:id',
        loadComponent: () => import('./pedidos/pedido-detalle.page').then((m) => m.PedidoDetallePage),
      },
      {
        path: 'perfil',
        loadComponent: () => import('./perfil/perfil.page').then((m) => m.PerfilPage),
      },
      { path: '', redirectTo: 'pedidos', pathMatch: 'full' },
    ],
  },
  { path: '', redirectTo: 'tabs/pedidos', pathMatch: 'full' },
  { path: '**', redirectTo: 'tabs/pedidos' },
];
