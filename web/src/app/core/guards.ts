import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from './auth.service';
import { Rol } from './modelos';

/** Solo deja entrar si hay sesión; si no, manda al login. */
export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);

  return auth.autenticado() ? true : inject(Router).createUrlTree(['/login']);
};

/** Si ya hay sesión, no tiene sentido mostrar el login. */
export const invitadoGuard: CanActivateFn = () => {
  const auth = inject(AuthService);

  return auth.autenticado() ? inject(Router).createUrlTree([auth.rutaInicial()]) : true;
};

/**
 * Solo deja entrar a los roles indicados en la ruta:
 *   { path: 'clientes', canActivate: [rolGuard], data: { roles: ['administrador', 'operador'] } }
 */
export const rolGuard: CanActivateFn = (ruta) => {
  const auth = inject(AuthService);
  const roles = (ruta.data['roles'] ?? []) as Rol[];

  return auth.tieneRol(...roles) ? true : inject(Router).createUrlTree([auth.rutaInicial()]);
};
