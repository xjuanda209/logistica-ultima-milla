import { DatePipe } from '@angular/common';
import { Component, effect, inject, input, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { ESTADOS_PEDIDO, mensajeDeError } from '../../core/etiquetas';
import { EstadoPedido, Paginado, Pedido } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

@Component({
  selector: 'app-pedidos-lista',
  imports: [DatePipe, RouterLink, Tarjeta, Tabla, BadgeEstado],
  templateUrl: './pedidos-lista.html',
})
export class PedidosLista {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);

  /** Filtro que llega por la URL: /pedidos?estado=en_ruta */
  readonly estado = input<EstadoPedido | undefined>();

  protected readonly estados = ESTADOS_PEDIDO;
  protected readonly listaEstados = Object.keys(ESTADOS_PEDIDO) as EstadoPedido[];
  protected readonly columnas: Columna[] = [
    'Número',
    'Cliente',
    'Comercio',
    'Zona',
    'Franja de entrega',
    'Prioridad',
    'Estado',
    'Repartidor',
    { titulo: 'Acciones', clase: 'text-end' },
  ];

  protected readonly pedidos = signal<Pedido[]>([]);
  protected readonly meta = signal<Paginado<Pedido>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  constructor() {
    // Cada vez que cambia el filtro de la URL se vuelve a pedir la primera página.
    effect(() => {
      this.estado();
      untracked(() => this.cargar(1));
    });
  }

  protected cargar(pagina: number): void {
    const estado = this.estado();
    this.cargando.set(true);
    this.error.set(null);

    this.api
      .get<Paginado<Pedido>>('/pedidos', estado ? { page: pagina, estado } : { page: pagina })
      .subscribe({
        next: (respuesta) => {
          this.pedidos.set(respuesta.data);
          this.meta.set(respuesta.meta);
          this.cargando.set(false);
        },
        error: (error: unknown) => {
          this.error.set(mensajeDeError(error));
          this.cargando.set(false);
        },
      });
  }

  protected filtrar(estado: string): void {
    void this.router.navigate(['/pedidos'], { queryParams: estado ? { estado } : {} });
  }
}
