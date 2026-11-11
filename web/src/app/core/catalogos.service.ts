import { Injectable, inject } from '@angular/core';
import { Observable, catchError, map, shareReplay, throwError } from 'rxjs';

import { ApiService } from './api.service';
import { Comercio, Paginado, TipoIncidencia, Zona } from './modelos';

type Catalogo = 'zonas' | 'comercios' | 'tipos-incidencia';

interface EnMemoria {
  datos: Observable<unknown[]>;
  pedidoEn: number;
}

/** Pasado este tiempo se vuelve a pedir, por si otra persona cambió el catálogo. */
const VIGENCIA_MS = 5 * 60 * 1000;

/**
 * Catálogos para los <select> (zonas, comercios, tipos de incidencia activos), guardados en
 * memoria: la primera pantalla que los pide hace la petición y las demás reutilizan la respuesta.
 *
 *   this.catalogos.zonas().subscribe((zonas) => this.zonas.set(zonas));
 *
 * Quien crea o edita un registro de un catálogo llama a invalidar('zonas') para que la próxima
 * pantalla lo vea. Al iniciar o cerrar sesión se vacía todo (cada rol ve catálogos distintos:
 * el comercio solo recibe el suyo).
 */
@Injectable({ providedIn: 'root' })
export class CatalogosService {
  private readonly api = inject(ApiService);
  private readonly memoria = new Map<Catalogo, EnMemoria>();

  zonas(): Observable<Zona[]> {
    return this.obtener<Zona>('zonas', '/zonas');
  }

  comercios(): Observable<Comercio[]> {
    return this.obtener<Comercio>('comercios', '/comercios');
  }

  /** Solo los activos: es lo que debe ofrecer un formulario para reportar una incidencia. */
  tiposIncidencia(): Observable<TipoIncidencia[]> {
    return this.obtener<TipoIncidencia>('tipos-incidencia', '/tipos-incidencia', { activos: 1 });
  }

  invalidar(catalogo: Catalogo): void {
    this.memoria.delete(catalogo);
  }

  limpiar(): void {
    this.memoria.clear();
  }

  private obtener<T>(
    catalogo: Catalogo,
    ruta: string,
    filtros: Record<string, number> = {},
  ): Observable<T[]> {
    const guardado = this.memoria.get(catalogo);
    if (guardado && Date.now() - guardado.pedidoEn < VIGENCIA_MS) {
      return guardado.datos as Observable<T[]>;
    }

    const datos = this.api.get<Paginado<T>>(ruta, { ...filtros, por_pagina: 100 }).pipe(
      map((respuesta) => respuesta.data),
      // Si falla no se guarda: la próxima pantalla lo vuelve a intentar.
      catchError((error: unknown) => {
        this.memoria.delete(catalogo);
        return throwError(() => error);
      }),
      shareReplay(1),
    );

    this.memoria.set(catalogo, { datos, pedidoEn: Date.now() });

    return datos;
  }
}
