import { Component, computed, input, output } from '@angular/core';

import { Paginado } from '../core/modelos';

/**
 * Pie de las listas paginadas de la API: "1–20 de 57 registros" y botones Anterior / Siguiente.
 *   <app-paginacion [meta]="meta()" (cambiar)="cargar($event)" />
 * Dentro de <app-tabla> no hace falta: la tabla ya la incluye.
 */
@Component({
  selector: 'app-paginacion',
  template: `
    @if (meta(); as m) {
      @if (m.total > 0) {
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <span class="text-body-secondary small">{{ rango() }}</span>
          @if (m.last_page > 1) {
            <nav class="d-flex align-items-center gap-2" aria-label="Paginación">
              <span class="text-body-secondary small d-none d-sm-inline">
                Página {{ m.current_page }} de {{ m.last_page }}
              </span>
              <div class="btn-group">
                <button
                  type="button"
                  class="btn btn-outline-secondary btn-sm"
                  [disabled]="deshabilitada() || m.current_page <= 1"
                  (click)="cambiar.emit(m.current_page - 1)"
                >
                  <i class="bi bi-chevron-left" aria-hidden="true"></i> Anterior
                </button>
                <button
                  type="button"
                  class="btn btn-outline-secondary btn-sm"
                  [disabled]="deshabilitada() || m.current_page >= m.last_page"
                  (click)="cambiar.emit(m.current_page + 1)"
                >
                  Siguiente <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
              </div>
            </nav>
          }
        </div>
      }
    }
  `,
})
export class Paginacion {
  readonly meta = input<Paginado<unknown>['meta'] | null>(null);
  /** Mientras carga otra página, los botones no responden. */
  readonly deshabilitada = input(false);
  readonly cambiar = output<number>();

  /** "21–40 de 57 registros" */
  protected readonly rango = computed(() => {
    const m = this.meta();
    if (!m || m.total === 0) {
      return '';
    }

    const desde = (m.current_page - 1) * m.per_page + 1;
    const hasta = Math.min(m.current_page * m.per_page, m.total);

    return `${desde}–${hasta} de ${m.total} ${m.total === 1 ? 'registro' : 'registros'}`;
  });
}
