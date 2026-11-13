import { DatePipe } from '@angular/common';
import { Component, computed, inject, input, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import type { ViewWillEnter } from '@ionic/angular';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonRouterLink,
  IonSpinner,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { cameraOutline, timeOutline, warningOutline } from 'ionicons/icons';

import { ApiService } from '../core/api.service';
import { esSinConexion, mensajeDeError } from '../core/etiquetas';
import { MensajesService } from '../core/mensajes.service';
import type { EstadoPedido, Pedido, Respuesta } from '../core/modelos';
import { InsigniaComponent } from '../shared/insignia.component';
import { SinConexionComponent } from '../shared/sin-conexion.component';

/**
 * Detalle del pedido y cambio de estado (INT-16). Desde la lista son 2 toques: abrir el pedido y
 * pulsar el botón del siguiente estado. "No entregado" tiene su propia pantalla (INT-17).
 */
@Component({
  selector: 'app-pedido-detalle',
  templateUrl: 'pedido-detalle.page.html',
  styles: `
    .numero {
      font-size: 1.25rem;
    }

    .franja {
      justify-content: flex-start;
    }

    ion-icon {
      vertical-align: -2px;
    }
  `,
  imports: [
    DatePipe,
    RouterLink,
    IonRouterLink,
    IonHeader,
    IonToolbar,
    IonButtons,
    IonBackButton,
    IonTitle,
    IonContent,
    IonButton,
    IonIcon,
    IonSpinner,
    InsigniaComponent,
    SinConexionComponent,
  ],
})
export class PedidoDetallePage implements ViewWillEnter {
  private readonly api = inject(ApiService);
  private readonly mensajes = inject(MensajesService);
  private readonly contenido = viewChild(IonContent);

  /** Llega desde la URL (/tabs/pedidos/:id) gracias a withComponentInputBinding(). */
  readonly id = input.required<string>();

  protected readonly pedido = signal<Pedido | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly guardando = signal(false);
  /** Regla de negocio que impidió el cambio de estado (por ejemplo, falta la evidencia). */
  protected readonly errorAccion = signal<string | null>(null);

  /** Evidencias e incidencias solo se registran en recogido o en ruta. */
  protected readonly admiteRegistros = computed(() => ['recogido', 'en_ruta'].includes(this.pedido()?.estado ?? ''));

  constructor() {
    addIcons({ cameraOutline, timeOutline, warningOutline });
  }

  /** Al volver de registrar una evidencia o una incidencia se vuelve a leer el pedido. */
  ionViewWillEnter(): void {
    this.errorAccion.set(null);
    this.cargar();
  }

  protected cargar(): void {
    this.error.set(null);

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

  protected cambiarEstado(estado: EstadoPedido): void {
    this.guardando.set(true);
    this.errorAccion.set(null);

    this.api.put<Respuesta<Pedido>>(`/pedidos/${this.id()}/estado`, { estado }).subscribe({
      next: (respuesta) => {
        this.pedido.set(respuesta.data);
        this.guardando.set(false);
        this.mensajes.exito('Estado actualizado.');
      },
      error: (error: unknown) => {
        this.guardando.set(false);

        if (esSinConexion(error)) {
          this.mensajes.error(mensajeDeError(error));
          return;
        }

        this.errorAccion.set(mensajeDeError(error));
        void this.contenido()?.scrollToTop(300);
      },
    });
  }
}
