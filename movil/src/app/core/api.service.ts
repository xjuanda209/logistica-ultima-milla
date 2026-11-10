import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import type { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

type Parametros = Record<string, string | number | boolean>;

/**
 * Única puerta de entrada a la API. Las páginas no usan HttpClient directamente:
 *   this.api.get<Paginado<Pedido>>('/pedidos', { page: 1 })
 */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly base = environment.apiUrl;

  get<T>(ruta: string, params: Parametros = {}): Observable<T> {
    return this.http.get<T>(this.base + ruta, { params });
  }

  post<T>(ruta: string, cuerpo: unknown = {}): Observable<T> {
    return this.http.post<T>(this.base + ruta, cuerpo);
  }

  put<T>(ruta: string, cuerpo: unknown = {}): Observable<T> {
    return this.http.put<T>(this.base + ruta, cuerpo);
  }

  delete<T>(ruta: string): Observable<T> {
    return this.http.delete<T>(this.base + ruta);
  }
}
