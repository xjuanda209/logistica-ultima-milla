import { inject } from '@angular/core';
import type { CanActivateFn } from '@angular/router';
import { Router } from '@angular/router';

import { AuthService } from './auth.service';

/** Solo deja entrar si hay sesión; si no, manda al login. */
export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);

  return auth.autenticado() ? true : inject(Router).createUrlTree(['/login']);
};

/** Si ya hay sesión, no tiene sentido mostrar el login. */
export const invitadoGuard: CanActivateFn = () => {
  const auth = inject(AuthService);

  return auth.autenticado() ? inject(Router).createUrlTree(['/tabs/pedidos']) : true;
};
