import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from './auth.service';
import { MensajesService } from './mensajes.service';
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
 * A los demás les muestra el aviso "Acceso denegado" (INT-22) y los lleva a su pantalla de inicio.
 */
export const rolGuard: CanActivateFn = (ruta) => {
  const auth = inject(AuthService);
  const roles = (ruta.data['roles'] ?? []) as Rol[];

  if (auth.tieneRol(...roles)) {
    return true;
  }

  inject(MensajesService).accesoDenegado();

  return inject(Router).createUrlTree([auth.rutaInicial()]);
};
