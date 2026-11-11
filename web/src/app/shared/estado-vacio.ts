import { Component, input } from '@angular/core';

/**
 * Mensaje centrado para cuando no hay nada que mostrar (o falló la carga).
 * Dice qué pasa y, si aplica, ofrece la acción para salir de ahí:
 *
 *   <app-estado-vacio icono="bi-geo-alt" titulo="Todavía no hay zonas"
 *                     texto="Las zonas agrupan los barrios de reparto.">
 *     <button class="btn btn-primary" (click)="abrirFormulario()">+ Nueva zona</button>
 *   </app-estado-vacio>
 *
 * Dentro de <app-tabla> no hace falta: la tabla ya lo muestra sola.
 */
@Component({
  selector: 'app-estado-vacio',
  template: `
    <div class="text-center px-3" [class]="compacto() ? 'py-3' : 'py-5'">
      <div
        class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 icono"
        [class]="
          tono() === 'error'
            ? 'bg-danger-subtle text-danger'
            : 'bg-body-secondary text-body-secondary'
        "
      >
        <i class="bi fs-4" [class]="icono()" aria-hidden="true"></i>
      </div>
      <p class="fw-semibold text-body-emphasis mb-1">{{ titulo() }}</p>
      @if (texto(); as detalle) {
        <p class="text-body-secondary mx-auto mb-0 detalle">{{ detalle }}</p>
      }
      <div class="accion mt-3"><ng-content /></div>
    </div>
  `,
  styles: `
    .icono {
      width: 48px;
      height: 48px;
    }

    .detalle {
      max-width: 46ch;
    }

    .accion:empty {
      display: none;
    }
  `,
})
export class EstadoVacio {
  readonly icono = input('bi-inbox');
  readonly titulo = input.required<string>();
  readonly texto = input<string | null>(null);
  readonly tono = input<'neutro' | 'error'>('neutro');
  /** Menos espacio arriba y abajo (para tarjetas pequeñas). */
  readonly compacto = input(false);
}
