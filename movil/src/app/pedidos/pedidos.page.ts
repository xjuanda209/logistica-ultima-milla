import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import type { RefresherCustomEvent, ViewWillEnter } from '@ionic/angular';
import {
  IonBadge,
  IonContent,
  IonHeader,
  IonItem,
  IonLabel,
  IonList,
  IonRefresher,
  IonRefresherContent,
  IonRouterLink,
  IonSpinner,
  IonText,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';

import { ApiService } from '../core/api.service';
import { ESTADOS_PEDIDO, mensajeDeError } from '../core/etiquetas';
import type { Paginado, Pedido } from '../core/modelos';

/** "Mis pedidos": la API ya devuelve solo los pedidos asignados al repartidor que inició sesión. */
@Component({
  selector: 'app-pedidos',
  templateUrl: 'pedidos.page.html',
  imports: [
    DatePipe,
    RouterLink,
    IonRouterLink,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonContent,
    IonRefresher,
    IonRefresherContent,
    IonList,
    IonItem,
    IonLabel,
    IonBadge,
    IonSpinner,
    IonText,
  ],
})
export class PedidosPage implements ViewWillEnter {
  private readonly api = inject(ApiService);

  protected readonly estados = ESTADOS_PEDIDO;

  protected readonly pedidos = signal<Pedido[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  /** Se ejecuta cada vez que la pantalla vuelve a mostrarse (por ejemplo al regresar del detalle). */
  ionViewWillEnter(): void {
    this.cargar();
  }

  protected refrescar(evento: RefresherCustomEvent): void {
    this.cargar(() => void evento.target.complete());
  }

  private cargar(alTerminar: () => void = () => undefined): void {
    this.error.set(null);

    this.api.get<Paginado<Pedido>>('/pedidos').subscribe({
      next: (respuesta) => {
        this.pedidos.set(respuesta.data);
        this.cargando.set(false);
        alTerminar();
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
        alTerminar();
      },
    });
  }
}
