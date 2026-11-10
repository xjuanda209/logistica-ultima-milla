import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, finalize, of, tap } from 'rxjs';

import { ApiService } from './api.service';
import { RespuestaLogin, Rol, Usuario } from './modelos';

const CLAVE_TOKEN = 'lum_token';
const CLAVE_USUARIO = 'lum_usuario';

/**
 * Maneja la sesión: guarda el token y el usuario en localStorage
 * y los expone como signals para que las pantallas reaccionen.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiService);

  private readonly _token = signal<string | null>(localStorage.getItem(CLAVE_TOKEN));
  private readonly _usuario = signal<Usuario | null>(leerUsuarioGuardado());

  readonly token = this._token.asReadonly();
  readonly usuario = this._usuario.asReadonly();
  readonly autenticado = computed(() => this._token() !== null && this._usuario() !== null);

  login(correo: string, password: string): Observable<RespuestaLogin> {
    return this.api.post<RespuestaLogin>('/login', { correo, password }).pipe(
      tap((respuesta) => {
        localStorage.setItem(CLAVE_TOKEN, respuesta.token);
        localStorage.setItem(CLAVE_USUARIO, JSON.stringify(respuesta.usuario));
        this._token.set(respuesta.token);
        this._usuario.set(respuesta.usuario);
      }),
    );
  }

  /** Avisa al backend (para que borre el token) y limpia la sesión local pase lo que pase. */
  logout(): Observable<unknown> {
    return this.api.post('/logout').pipe(
      catchError(() => of(null)),
      finalize(() => this.limpiarSesion()),
    );
  }

  limpiarSesion(): void {
    localStorage.removeItem(CLAVE_TOKEN);
    localStorage.removeItem(CLAVE_USUARIO);
    this._token.set(null);
    this._usuario.set(null);
  }

  tieneRol(...roles: Rol[]): boolean {
    const usuario = this._usuario();
    return usuario !== null && roles.includes(usuario.rol);
  }

  /** Primera pantalla que ve cada rol después de entrar. */
  rutaInicial(): string {
    return this.tieneRol('repartidor') ? '/pedidos' : '/panel';
  }
}

function leerUsuarioGuardado(): Usuario | null {
  try {
    const guardado = localStorage.getItem(CLAVE_USUARIO);
    return guardado ? (JSON.parse(guardado) as Usuario) : null;
  } catch {
    return null;
  }
}
