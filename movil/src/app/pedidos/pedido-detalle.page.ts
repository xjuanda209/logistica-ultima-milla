import { DatePipe } from '@angular/common';
import type { OnInit } from '@angular/core';
import { Component, inject, input, signal } from '@angular/core';
import {
  IonBackButton,
  IonBadge,
  IonButtons,
  IonContent,
  IonHeader,
  IonItem,
  IonLabel,
  IonList,
  IonListHeader,
  IonNote,
  IonSpinner,
  IonText,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';

import { ApiService } from '../core/api.service';
import { ESTADOS_PEDIDO, PRIORIDADES, mensajeDeError } from '../core/etiquetas';
import type { Pedido, Respuesta } from '../core/modelos';

@Component({
  selector: 'app-pedido-detalle',
  templateUrl: 'pedido-detalle.page.html',
  imports: [
    DatePipe,
    IonHeader,
    IonToolbar,
    IonButtons,
    IonBackButton,
    IonTitle,
    IonContent,
    IonList,
    IonListHeader,
    IonItem,
    IonLabel,
    IonBadge,
    IonNote,
    IonSpinner,
    IonText,
  ],
})
export class PedidoDetallePage implements OnInit {
  private readonly api = inject(ApiService);

  /** Llega desde la URL (/tabs/pedidos/:id) gracias a withComponentInputBinding(). */
  readonly id = input.required<string>();

  protected readonly estados = ESTADOS_PEDIDO;
  protected readonly prioridades = PRIORIDADES;

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
