import { Component, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
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
  IonTextarea,
  IonTitle,
  IonToolbar,
  NavController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { checkmarkCircle, closeCircle } from 'ionicons/icons';

import { ApiService } from '../core/api.service';
import { camposDeError, mensajeDeError } from '../core/etiquetas';
import { MensajesService } from '../core/mensajes.service';
import type { Pedido, Respuesta } from '../core/modelos';
import { InsigniaComponent } from '../shared/insignia.component';
import { SinConexionComponent } from '../shared/sin-conexion.component';

/**
 * Marcar un pedido como no entregado (INT-17): exige una evidencia, una incidencia y el motivo.
 * El botón se habilita cuando se cumplen los tres; el backend lo vuelve a comprobar.
 */
@Component({
  selector: 'app-no-entregado',
  templateUrl: 'no-entregado.page.html',
  styles: `
    .requisito {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 4px 0;

      ion-icon {
        flex-shrink: 0;
        font-size: 28px;
      }
    }
  `,
  imports: [
    FormsModule,
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
    IonTextarea,
    InsigniaComponent,
    SinConexionComponent,
  ],
})
export class NoEntregadoPage implements ViewWillEnter {
  private readonly api = inject(ApiService);
  private readonly mensajes = inject(MensajesService);
  private readonly navegacion = inject(NavController);

  readonly id = input.required<string>();

  protected readonly pedido = signal<Pedido | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly motivo = signal('');

  protected readonly evidencias = computed(() => this.pedido()?.evidencias?.length ?? 0);
  protected readonly incidencias = computed(() => this.pedido()?.incidencias?.length ?? 0);

  /** Primer requisito que falta, escrito para la persona; null si ya puede confirmar. */
  protected readonly falta = computed(() => {
    if (this.evidencias() === 0) {
      return 'Falta la evidencia: regístrala para marcar el pedido como no entregado.';
    }

    if (this.incidencias() === 0) {
      return 'Falta la incidencia: regístrala para marcar el pedido como no entregado.';
    }

    return this.motivo().trim() === '' ? 'Escribe el motivo del no entregado.' : null;
  });

  constructor() {
    addIcons({ checkmarkCircle, closeCircle });
  }

  /** Al volver de registrar la evidencia o la incidencia se actualizan los requisitos. */
  ionViewWillEnter(): void {
    this.errorFormulario.set(null);
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

  protected confirmar(): void {
    this.guardando.set(true);
    this.errorFormulario.set(null);

    this.api
      .put<Respuesta<Pedido>>(`/pedidos/${this.id()}/estado`, { estado: 'no_entregado', motivo: this.motivo().trim() })
      .subscribe({
        next: () => {
          this.guardando.set(false);
          this.motivo.set('');
          this.mensajes.exito('Estado actualizado.');
          void this.navegacion.navigateBack(['/tabs/pedidos', this.id()]);
        },
        error: (error: unknown) => {
          this.guardando.set(false);
          this.errorFormulario.set(camposDeError(error)['motivo']?.[0] ?? mensajeDeError(error));
        },
      });
  }
}
