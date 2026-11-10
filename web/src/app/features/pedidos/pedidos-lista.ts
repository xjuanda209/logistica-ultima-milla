import { DatePipe } from '@angular/common';
import { Component, effect, inject, input, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { ESTADOS_PEDIDO, PRIORIDADES, mensajeDeError } from '../../core/etiquetas';
import { EstadoPedido, Paginado, Pedido } from '../../core/modelos';
import { Paginacion } from '../../shared/paginacion';

@Component({
  selector: 'app-pedidos-lista',
  imports: [DatePipe, RouterLink, Paginacion],
  templateUrl: './pedidos-lista.html',
})
export class PedidosLista {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);

  /** Filtro que llega por la URL: /pedidos?estado=en_ruta */
  readonly estado = input<EstadoPedido | undefined>();
  /** Búsqueda que llega del buscador de la barra superior: /pedidos?numero=PED-0012 */
  readonly numero = input<string | undefined>();

  protected readonly estados = ESTADOS_PEDIDO;
  protected readonly prioridades = PRIORIDADES;
  protected readonly listaEstados = Object.keys(ESTADOS_PEDIDO) as EstadoPedido[];

  protected readonly pedidos = signal<Pedido[]>([]);
  protected readonly meta = signal<Paginado<Pedido>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  constructor() {
    // Cada vez que cambia el filtro de la URL se vuelve a pedir la primera página.
    effect(() => {
      this.estado();
      this.numero();
      untracked(() => this.cargar(1));
    });
  }

  protected cargar(pagina: number): void {
    const estado = this.estado();
    const numero = this.numero();
    this.cargando.set(true);
    this.error.set(null);

    this.api
      .get<Paginado<Pedido>>('/pedidos', {
        page: pagina,
        ...(estado ? { estado } : {}),
        ...(numero ? { numero } : {}),
      })
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
    void this.router.navigate(['/pedidos'], {
      queryParams: { estado: estado || null },
      queryParamsHandling: 'merge',
    });
  }
}
