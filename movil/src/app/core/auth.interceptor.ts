import type { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from './auth.service';

/**
 * Agrega el token a cada petición. Si la API responde 401 (token vencido o borrado),
 * cierra la sesión local y manda al login.
 */
export const authInterceptor: HttpInterceptorFn = (peticion, siguiente) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token();

  const conToken = token ? peticion.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : peticion;

  return siguiente(conToken.clone({ setHeaders: { Accept: 'application/json' } })).pipe(
    catchError((error: HttpErrorResponse) => {
      const esLogin = peticion.url.endsWith('/login');

      if (error.status === 401 && !esLogin) {
        auth.limpiarSesion();
        void router.navigate(['/login']);
      }

      return throwError(() => error);
    }),
  );
};
