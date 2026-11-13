import { DatePipe } from '@angular/common';
import { Component, inject, input, signal } from '@angular/core';
import type { ViewWillEnter } from '@ionic/angular';
import {
  IonBackButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonLabel,
  IonSegment,
  IonSegmentButton,
  IonSpinner,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';

import { ApiService } from '../core/api.service';
import { TIPOS_EVIDENCIA, mensajeDeError } from '../core/etiquetas';
import type { Pedido, Respuesta } from '../core/modelos';
import { InsigniaComponent } from '../shared/insignia.component';
import { SinConexionComponent } from '../shared/sin-conexion.component';

/**
 * Evidencias e incidencias del pedido, solo lectura (INT-20). La nota para el comercio no llega
 * al repartidor: el backend no la envía.
 */
@Component({
  selector: 'app-registros',
  imports: [
    DatePipe,
    IonHeader,
    IonToolbar,
    IonButtons,
    IonBackButton,
    IonTitle,
    IonContent,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonSpinner,
    InsigniaComponent,
    SinConexionComponent,
  ],
  template: `
    <ion-header>
      <ion-toolbar color="primary">
        <ion-buttons slot="start">
          <ion-back-button [defaultHref]="'/tabs/pedidos/' + id()" text="" aria-label="Volver"></ion-back-button>
        </ion-buttons>
        <ion-title>Evidencias e incidencias</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      @if (error(); as mensaje) {
        <app-sin-conexion [mensaje]="mensaje" (reintentar)="cargar()" />
      }

      @if (cargando()) {
        <div class="centrado">
          <ion-spinner aria-label="Cargando"></ion-spinner>
        </div>
      } @else if (pedido(); as p) {
        <ion-segment class="ion-margin-bottom" [value]="pestana()" (ionChange)="pestana.set($any($event.detail.value))">
          <ion-segment-button value="evidencias">
            <ion-label>Evidencias ({{ p.evidencias?.length ?? 0 }})</ion-label>
          </ion-segment-button>
          <ion-segment-button value="incidencias">
            <ion-label>Incidencias ({{ p.incidencias?.length ?? 0 }})</ion-label>
          </ion-segment-button>
        </ion-segment>

        @if (pestana() === 'evidencias') {
          <div class="galeria">
            @for (evidencia of p.evidencias ?? []; track evidencia.id) {
              <figure>
                <img loading="lazy" [src]="evidencia.url" [alt]="tiposEvidencia[evidencia.tipo]" />
                <figcaption>
                  {{ tiposEvidencia[evidencia.tipo] }}
                  <span class="texto-suave">{{ evidencia.created_at | date: 'dd/MM HH:mm' }}</span>
                  @if (!evidencia.con_ubicacion) {
                    <span class="chip chip-alerta">Sin ubicación</span>
                  }
                </figcaption>
              </figure>
            } @empty {
              <p class="texto-suave">Sin evidencias.</p>
            }
          </div>
        } @else {
          @for (incidencia of p.incidencias ?? []; track incidencia.id) {
            <div class="tarjeta">
              <div class="fila">
                <strong>#{{ incidencia.id }} · {{ incidencia.tipo }}</strong>
                <app-insignia tipo="incidencia" [valor]="incidencia.estado" />
              </div>
              <p>{{ incidencia.descripcion }}</p>
              <p class="texto-suave">
                @if (incidencia.prioridad) {
                  Prioridad <app-insignia tipo="prioridad" [valor]="incidencia.prioridad" /> ·
                }
                {{ incidencia.created_at | date: 'dd/MM HH:mm' }}
              </p>
              @if (incidencia.foto_url; as foto) {
                <img class="vista-previa ion-margin-top" loading="lazy" alt="Foto de la incidencia" [src]="foto" />
              }
            </div>
          } @empty {
            <p class="texto-suave">Sin incidencias.</p>
          }
          <p class="texto-suave">Las notas dirigidas al comercio no se muestran al repartidor.</p>
        }
      }
    </ion-content>
  `,
  styles: `
    .galeria {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    figure {
      margin: 0;
    }

    figure img {
      display: block;
      width: 100%;
      aspect-ratio: 4 / 3;
      border-radius: 12px;
      background: #dfe8e8;
      object-fit: cover;
    }

    figcaption span {
      display: block;
      width: fit-content;
      margin-top: 2px;
    }
  `,
})
export class RegistrosPage implements ViewWillEnter {
  private readonly api = inject(ApiService);

  readonly id = input.required<string>();

  protected readonly tiposEvidencia = TIPOS_EVIDENCIA;

  protected readonly pedido = signal<Pedido | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly pestana = signal<'evidencias' | 'incidencias'>('evidencias');

  ionViewWillEnter(): void {
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
}
