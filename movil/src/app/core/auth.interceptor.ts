import type { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from './auth.service';

/**
 * Pasa por aquí toda petición a la API:
 *  - agrega el token y el encabezado "X-Canal: movil" (el backend lo registra en el historial);
 *  - si la API responde 401 (token vencido o borrado), cierra la sesión local y manda al login
 *    con el aviso "Tu sesión venció" (INT-01).
 */
export const authInterceptor: HttpInterceptorFn = (peticion, siguiente) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token();

  const encabezados: Record<string, string> = { Accept: 'application/json', 'X-Canal': 'movil' };
  if (token) {
    encabezados['Authorization'] = `Bearer ${token}`;
  }

  return siguiente(peticion.clone({ setHeaders: encabezados })).pipe(
    catchError((error: HttpErrorResponse) => {
      const esLogin = peticion.url.endsWith('/login');

      if (error.status === 401 && !esLogin && token) {
        auth.limpiarSesion();
        void router.navigate(['/login'], { queryParams: { sesion: 'vencida' } });
      }

      return throwError(() => error);
    }),
  );
};
