import { Component, computed, inject, input, signal } from '@angular/core';
import type { ViewWillEnter } from '@ionic/angular';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonLabel,
  IonSegment,
  IonSegmentButton,
  IonTitle,
  IonToolbar,
  NavController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { cameraOutline, imagesOutline, locationOutline } from 'ionicons/icons';

import { ApiService } from '../core/api.service';
import type { Foto, Ubicacion } from '../core/dispositivo';
import { elegirDeGaleria, obtenerUbicacion, tomarFoto } from '../core/dispositivo';
import { camposDeError, mensajeDeError } from '../core/etiquetas';
import { MensajesService } from '../core/mensajes.service';
import type { Pedido, Respuesta, TipoEvidencia } from '../core/modelos';

/**
 * Registrar evidencia (INT-18): tipo, foto de la cámara o la galería y ubicación. Si el teléfono
 * no da permiso de ubicación, la evidencia se guarda sin coordenadas.
 */
@Component({
  selector: 'app-evidencia',
  imports: [
    IonHeader,
    IonToolbar,
    IonButtons,
    IonBackButton,
    IonTitle,
    IonContent,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonButton,
    IonIcon,
  ],
  template: `
    <ion-header>
      <ion-toolbar color="primary">
        <ion-buttons slot="start">
          <ion-back-button [defaultHref]="destino()" text="" aria-label="Volver"></ion-back-button>
        </ion-buttons>
        <ion-title>Registrar evidencia</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <ion-segment class="ion-margin-bottom" [value]="tipo()" (ionChange)="tipo.set($any($event.detail.value))">
        <ion-segment-button value="foto_entrega">
          <ion-label>Foto de la entrega</ion-label>
        </ion-segment-button>
        <ion-segment-button value="comprobante">
          <ion-label>Comprobante</ion-label>
        </ion-segment-button>
      </ion-segment>

      @if (foto(); as f) {
        <img class="vista-previa" alt="Vista previa de la evidencia" [src]="f.vistaPrevia" />
        <p class="texto-suave ion-margin-bottom">{{ f.descripcion }}</p>
      } @else {
        <div class="zona-foto">
          <ion-icon name="camera-outline" size="large" aria-hidden="true"></ion-icon>
          <span>Aún no hay imagen</span>
        </div>
      }

      @if (error(); as mensaje) {
        <div class="aviso aviso-error" role="alert">{{ mensaje }}</div>
      }

      <div class="fila-botones">
        <ion-button fill="outline" [disabled]="guardando()" (click)="elegir('camara')">
          <ion-icon slot="start" name="camera-outline" aria-hidden="true"></ion-icon>
          Tomar foto
        </ion-button>
        <ion-button fill="outline" [disabled]="guardando()" (click)="elegir('galeria')">
          <ion-icon slot="start" name="images-outline" aria-hidden="true"></ion-icon>
          Galería
        </ion-button>
      </div>

      <p class="ion-margin-top">
        @switch (estadoUbicacion()) {
          @case ('buscando') {
            <span class="chip chip-gris">
              <ion-icon name="location-outline" aria-hidden="true"></ion-icon>
              Buscando ubicación…
            </span>
          }
          @case ('capturada') {
            <span class="chip chip-exito">
              <ion-icon name="location-outline" aria-hidden="true"></ion-icon>
              Ubicación capturada
            </span>
          }
          @default {
            <span class="chip chip-alerta">
              <ion-icon name="location-outline" aria-hidden="true"></ion-icon>
              Sin ubicación (sin permiso): se guardará sin coordenadas
            </span>
          }
        }
      </p>

      <ion-button
        expand="block"
        class="boton-grande ion-margin-top"
        [disabled]="!puedeGuardar() || guardando()"
        (click)="guardar()"
      >
        {{ guardando() ? 'Guardando…' : 'Guardar evidencia' }}
      </ion-button>
    </ion-content>
  `,
})
export class EvidenciaPage implements ViewWillEnter {
  private readonly api = inject(ApiService);
  private readonly mensajes = inject(MensajesService);
  private readonly navegacion = inject(NavController);

  readonly id = input.required<string>();
  /** ?volver=no-entregado cuando se llega desde la pantalla "No entregado". */
  readonly volver = input<string>();

  protected readonly tipo = signal<TipoEvidencia>('foto_entrega');
  protected readonly foto = signal<Foto | null>(null);
  protected readonly ubicacion = signal<Ubicacion | null>(null);
  protected readonly estadoUbicacion = signal<'buscando' | 'capturada' | 'sin'>('buscando');
  protected readonly guardando = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly puedeGuardar = computed(() => this.foto() !== null && this.foto()?.error === null);
  protected readonly destino = computed(
    () => `/tabs/pedidos/${this.id()}${this.volver() === 'no-entregado' ? '/no-entregado' : ''}`,
  );

  constructor() {
    addIcons({ cameraOutline, imagesOutline, locationOutline });
  }

  ionViewWillEnter(): void {
    this.tipo.set('foto_entrega');
    this.foto.set(null);
    this.error.set(null);
    void this.buscarUbicacion();
  }

  protected async elegir(origen: 'camara' | 'galeria'): Promise<void> {
    const foto = origen === 'camara' ? await tomarFoto() : await elegirDeGaleria();

    if (foto) {
      this.foto.set(foto);
      this.error.set(foto.error);
    }
  }

  protected guardar(): void {
    const foto = this.foto();
    if (!foto) {
      return;
    }

    const datos = new FormData();
    datos.append('tipo', this.tipo());
    datos.append('archivo', foto.archivo, foto.nombre);

    const ubicacion = this.ubicacion();
    if (ubicacion) {
      datos.append('latitud', String(ubicacion.latitud));
      datos.append('longitud', String(ubicacion.longitud));
    }

    this.guardando.set(true);
    this.error.set(null);

    this.api.post<Respuesta<Pedido>>(`/pedidos/${this.id()}/evidencias`, datos).subscribe({
      next: () => {
        this.guardando.set(false);
        this.mensajes.exito('Evidencia guardada.');
        void this.navegacion.navigateBack(this.destino());
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.error.set(Object.values(camposDeError(error))[0]?.[0] ?? mensajeDeError(error));
      },
    });
  }

  private async buscarUbicacion(): Promise<void> {
    this.estadoUbicacion.set('buscando');

    const ubicacion = await obtenerUbicacion();

    this.ubicacion.set(ubicacion);
    this.estadoUbicacion.set(ubicacion ? 'capturada' : 'sin');
  }
}
