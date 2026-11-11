import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { mensajeDeError } from '../../core/etiquetas';
import { Pedido, Respuesta } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';

@Component({
  selector: 'app-pedido-detalle',
  imports: [DatePipe, RouterLink, BadgeEstado],
  templateUrl: './pedido-detalle.html',
})
export class PedidoDetalle implements OnInit {
  private readonly api = inject(ApiService);

  /** Llega desde la URL (/pedidos/:id) gracias a withComponentInputBinding(). */
  readonly id = input.required<string>();

  protected readonly pedido = signal<Pedido | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.api.get<Respuesta<Pedido>>(`/pedidos/${this.id()}`).subscribe({
      next: (respuesta) => {
        this.pedido.set(respuesta.data);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }
}
