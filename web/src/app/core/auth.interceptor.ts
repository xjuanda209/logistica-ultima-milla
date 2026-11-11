import { HttpErrorResponse, HttpInterceptorFn, HttpResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, tap, throwError } from 'rxjs';

import { AuthService } from './auth.service';
import { codigoDeError } from './etiquetas';
import { MensajesService } from './mensajes.service';

/**
 * Agrega el token a cada petición y reacciona a las respuestas que valen para toda la app:
 *  - 401 (token vencido o borrado): cierra la sesión local y manda al login con el aviso
 *    "Tu sesión venció" (INT-01).
 *  - 403 NO_AUTORIZADO: aviso "Acceso denegado" (INT-22).
 *  - sin respuesta del servidor: enciende la franja "Sin conexión"; la apaga la siguiente respuesta.
 * La página que hizo la petición recibe el error igual, por si quiere mostrar algo más.
 */
export const authInterceptor: HttpInterceptorFn = (peticion, siguiente) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const mensajes = inject(MensajesService);
  const token = auth.token();

  const conToken = token
    ? peticion.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : peticion;

  return siguiente(conToken.clone({ setHeaders: { Accept: 'application/json' } })).pipe(
    tap((evento) => {
      if (evento instanceof HttpResponse) {
        mensajes.marcarConectado();
      }
    }),
    catchError((error: HttpErrorResponse) => {
      const esLogin = peticion.url.endsWith('/login');

      if (error.status === 0) {
        mensajes.marcarSinConexion();
      } else {
        mensajes.marcarConectado();
      }

      // Había sesión y la API ya no acepta el token: el login muestra "Tu sesión venció".
      if (error.status === 401 && !esLogin && token) {
        auth.limpiarSesion();
        void router.navigate(['/login'], { queryParams: { sesion: 'vencida' } });
      }

      if (error.status === 403 && codigoDeError(error) === 'NO_AUTORIZADO') {
        mensajes.accesoDenegado();
      }

      return throwError(() => error);
    }),
  );
};
