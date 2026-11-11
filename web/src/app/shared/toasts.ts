import { Component, inject } from '@angular/core';

import { MensajesService, TipoMensaje } from '../core/mensajes.service';

/**
 * Toasts de INT-22 arriba a la derecha, sobre cualquier pantalla. Va una sola vez, en app.html.
 * Para mostrar uno, usa MensajesService (exito, error, accesoDenegado).
 */
@Component({
  selector: 'app-toasts',
  template: `
    <div class="toasts" aria-live="polite">
      @for (mensaje of servicio.mensajes(); track mensaje.id) {
        <div
          class="alert d-flex align-items-start gap-2 shadow mb-0"
          [class]="estilos[mensaje.tipo].clase"
          [attr.role]="mensaje.tipo === 'exito' ? 'status' : 'alert'"
        >
          <i
            class="bi flex-shrink-0 mt-1"
            [class]="estilos[mensaje.tipo].icono"
            aria-hidden="true"
          ></i>
          <span class="flex-grow-1">{{ mensaje.texto }}</span>
          <button
            type="button"
            class="btn-close btn-sm mt-1"
            aria-label="Cerrar mensaje"
            (click)="servicio.cerrar(mensaje.id)"
          ></button>
        </div>
      }
    </div>
  `,
  styles: `
    .toasts {
      position: fixed;
      top: 5rem; /* debajo de la barra superior */
      right: 1rem;
      z-index: 1090;
      display: grid;
      gap: 0.75rem;
      width: min(26rem, calc(100vw - 2rem));
    }
  `,
})
export class Toasts {
  protected readonly servicio = inject(MensajesService);

  protected readonly estilos: Record<TipoMensaje, { clase: string; icono: string }> = {
    exito: { clase: 'alert-success', icono: 'bi-check-lg' },
    error: { clase: 'alert-danger', icono: 'bi-x-lg' },
    aviso: { clase: 'alert-warning', icono: 'bi-dash-circle-fill text-danger' },
  };
}
