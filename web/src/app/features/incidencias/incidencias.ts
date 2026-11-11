import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { mensajeDeError } from '../../core/etiquetas';
import { Incidencia, Paginado } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

@Component({
  selector: 'app-incidencias',
  imports: [DatePipe, RouterLink, Tarjeta, Tabla, BadgeEstado],
  templateUrl: './incidencias.html',
})
export class Incidencias implements OnInit {
  private readonly api = inject(ApiService);

  protected readonly incidencias = signal<Incidencia[]>([]);
  protected readonly meta = signal<Paginado<Incidencia>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.cargar(1);
  }

  protected cargar(pagina: number): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Paginado<Incidencia>>('/incidencias', { page: pagina }).subscribe({
      next: (respuesta) => {
        this.incidencias.set(respuesta.data);
        this.meta.set(respuesta.meta);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }
}
