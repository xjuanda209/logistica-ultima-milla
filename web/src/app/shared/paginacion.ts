import { Component, input, output } from '@angular/core';

import { Paginado } from '../core/modelos';

/**
 * Botones Anterior / Siguiente para las listas paginadas de la API.
 *   <app-paginacion [meta]="meta()" (cambiar)="cargar($event)" />
 */
@Component({
  selector: 'app-paginacion',
  template: `
    @if (meta(); as m) {
      <div class="d-flex justify-content-between align-items-center mt-3">
        <span class="text-body-secondary small">
          {{ m.total }} registros · página {{ m.current_page }} de {{ m.last_page }}
        </span>
        @if (m.last_page > 1) {
          <div class="btn-group">
            <button
              type="button"
              class="btn btn-outline-secondary btn-sm"
              [disabled]="m.current_page <= 1"
              (click)="cambiar.emit(m.current_page - 1)"
            >
              Anterior
            </button>
            <button
              type="button"
              class="btn btn-outline-secondary btn-sm"
              [disabled]="m.current_page >= m.last_page"
              (click)="cambiar.emit(m.current_page + 1)"
            >
              Siguiente
            </button>
          </div>
        }
      </div>
    }
  `,
})
export class Paginacion {
  readonly meta = input<Paginado<unknown>['meta'] | null>(null);
  readonly cambiar = output<number>();
}
