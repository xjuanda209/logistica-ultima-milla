import { Component, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import type { ViewWillEnter } from '@ionic/angular';
import {
  IonBackButton,
  IonButton,
  IonButtons,
  IonContent,
  IonHeader,
  IonIcon,
  IonSelect,
  IonSelectOption,
  IonTextarea,
  IonTitle,
  IonToolbar,
  NavController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { addOutline } from 'ionicons/icons';

import { ApiService } from '../core/api.service';
import type { Foto } from '../core/dispositivo';
import { elegirDeGaleria } from '../core/dispositivo';
import type { ErroresDeCampos } from '../core/etiquetas';
import { camposDeError, mensajeDeFormulario } from '../core/etiquetas';
import { MensajesService } from '../core/mensajes.service';
import type { Paginado, TipoIncidencia } from '../core/modelos';

/**
 * Registrar incidencia (INT-19, versión móvil): tipo (solo los activos), descripción obligatoria
 * y foto opcional. La incidencia queda "abierta"; la prioridad la pone el operador en la web.
 */
@Component({
  selector: 'app-incidencia',
  imports: [
    FormsModule,
    IonHeader,
    IonToolbar,
    IonButtons,
    IonBackButton,
    IonTitle,
    IonContent,
    IonSelect,
    IonSelectOption,
    IonTextarea,
    IonButton,
    IonIcon,
  ],
  template: `
    <ion-header>
      <ion-toolbar color="primary">
        <ion-buttons slot="start">
          <ion-back-button [defaultHref]="destino()" text="" aria-label="Volver"></ion-back-button>
        </ion-buttons>
        <ion-title>Registrar incidencia</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <label class="etiqueta-campo" for="tipo">Tipo de incidencia *</label>
      <ion-select
        id="tipo"
        class="caja"
        aria-label="Tipo de incidencia"
        interface="action-sheet"
        placeholder="Selecciona un tipo"
        cancelText="Cancelar"
        [class.con-error]="errores()['tipo_incidencia_id']"
        [ngModel]="tipoId()"
        (ngModelChange)="tipoId.set($event)"
      >
        @for (tipo of tipos(); track tipo.id) {
          <ion-select-option [value]="tipo.id">{{ tipo.nombre }}</ion-select-option>
        }
      </ion-select>
      @if (errores()['tipo_incidencia_id']?.[0]; as mensaje) {
        <p class="error-campo">{{ mensaje }}</p>
      }

      <label class="etiqueta-campo" for="descripcion">Descripción *</label>
      <ion-textarea
        id="descripcion"
        class="caja"
        aria-label="Descripción"
        [rows]="4"
        [maxlength]="1000"
        [class.con-error]="errores()['descripcion']"
        [ngModel]="descripcion()"
        (ngModelChange)="descripcion.set($event ?? '')"
      ></ion-textarea>
      @if (errores()['descripcion']?.[0]; as mensaje) {
        <p class="error-campo">{{ mensaje }}</p>
      }

      <span class="etiqueta-campo">Foto (opcional)</span>
      @if (foto(); as f) {
        <img class="vista-previa" alt="Foto de la incidencia" [src]="f.vistaPrevia" />
        <p class="fila texto-suave">
          {{ f.descripcion }}
          <ion-button fill="clear" size="small" (click)="foto.set(null)">Quitar</ion-button>
        </p>
      } @else {
        <button type="button" class="zona-foto adjuntar" (click)="adjuntar()">
          <span><ion-icon name="add-outline" aria-hidden="true"></ion-icon> Adjuntar foto</span>
        </button>
      }
      @if (foto()?.error ?? errores()['foto']?.[0]; as mensaje) {
        <p class="error-campo">{{ mensaje }}</p>
      }

      @if (errorFormulario(); as mensaje) {
        <div class="aviso aviso-error ion-margin-top" role="alert">{{ mensaje }}</div>
      }

      <ion-button expand="block" class="boton-grande ion-margin-top" [disabled]="guardando()" (click)="guardar()">
        {{ guardando() ? 'Registrando…' : 'Registrar incidencia' }}
      </ion-button>
    </ion-content>
  `,
  styles: `
    .adjuntar {
      width: 100%;
      min-height: 96px;
      font: inherit;
      cursor: pointer;
    }

    ion-icon {
      vertical-align: -2px;
    }
  `,
})
export class IncidenciaPage implements ViewWillEnter {
  private readonly api = inject(ApiService);
  private readonly mensajes = inject(MensajesService);
  private readonly navegacion = inject(NavController);

  readonly id = input.required<string>();
  /** ?volver=no-entregado cuando se llega desde la pantalla "No entregado". */
  readonly volver = input<string>();

  protected readonly tipos = signal<TipoIncidencia[]>([]);
  protected readonly tipoId = signal<number | null>(null);
  protected readonly descripcion = signal('');
  protected readonly foto = signal<Foto | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});

  protected readonly destino = computed(
    () => `/tabs/pedidos/${this.id()}${this.volver() === 'no-entregado' ? '/no-entregado' : ''}`,
  );

  constructor() {
    addIcons({ addOutline });
  }

  ionViewWillEnter(): void {
    this.tipoId.set(null);
    this.descripcion.set('');
    this.foto.set(null);
    this.errores.set({});
    this.errorFormulario.set(null);

    this.api.get<Paginado<TipoIncidencia>>('/tipos-incidencia', { activos: 1, por_pagina: 100 }).subscribe({
      next: (respuesta) => this.tipos.set(respuesta.data),
      error: (error: unknown) => this.errorFormulario.set(mensajeDeFormulario(error)),
    });
  }

  protected async adjuntar(): Promise<void> {
    const foto = await elegirDeGaleria();

    if (foto) {
      this.foto.set(foto);
    }
  }

  protected guardar(): void {
    const foto = this.foto();
    if (foto?.error) {
      return;
    }

    const datos = new FormData();
    datos.append('tipo_incidencia_id', this.tipoId() === null ? '' : String(this.tipoId()));
    datos.append('descripcion', this.descripcion().trim());
    if (foto) {
      datos.append('foto', foto.archivo, foto.nombre);
    }

    this.guardando.set(true);
    this.errores.set({});
    this.errorFormulario.set(null);

    this.api.post(`/pedidos/${this.id()}/incidencias`, datos).subscribe({
      next: () => {
        this.guardando.set(false);
        this.mensajes.exito('Incidencia registrada.');
        void this.navegacion.navigateBack(this.destino());
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errores.set(camposDeError(error));
        this.errorFormulario.set(mensajeDeFormulario(error));
      },
    });
  }
}
