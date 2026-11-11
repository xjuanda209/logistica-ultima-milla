import { Component, inject, signal } from '@angular/core';

import { ApiService } from '../core/api.service';
import { MENSAJE_SIN_CONEXION } from '../core/etiquetas';
import { MensajesService } from '../core/mensajes.service';

/**
 * Franja roja "Sin conexión" de INT-22, arriba del contenido (va una sola vez, en el layout).
 * Aparece cuando una petición no llega al servidor o el navegador pierde la red.
 * "Reintentar" comprueba que la API responda y, si responde, recarga la página para volver
 * a pedir los datos que no llegaron.
 */
@Component({
  selector: 'app-sin-conexion',
  host: { '(window:offline)': 'mensajes.marcarSinConexion()' },
  template: `
    @if (mensajes.sinConexion()) {
      <div
        class="alert alert-danger d-flex flex-wrap align-items-center gap-2 gap-sm-3"
        role="alert"
      >
        <i class="bi bi-wifi-off fs-5" aria-hidden="true"></i>
        <span class="flex-grow-1"><strong>Sin conexión.</strong> {{ texto }}</span>
        <button
          type="button"
          class="btn btn-outline-secondary btn-sm"
          [disabled]="reintentando()"
          (click)="reintentar()"
        >
          {{ reintentando() ? 'Reintentando…' : 'Reintentar' }}
        </button>
      </div>
    }
  `,
})
export class SinConexion {
  private readonly api = inject(ApiService);
  protected readonly mensajes = inject(MensajesService);

  protected readonly texto = MENSAJE_SIN_CONEXION;
  protected readonly reintentando = signal(false);

  protected reintentar(): void {
    this.reintentando.set(true);

    // El interceptor apaga la franja si responde y la deja encendida si no.
    this.api.get('/me').subscribe({
      next: () => window.location.reload(),
      error: () => this.reintentando.set(false),
    });
  }
}
