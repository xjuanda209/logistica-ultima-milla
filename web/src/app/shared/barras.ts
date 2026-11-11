import { Component, computed, input } from '@angular/core';

import { EstadoVacio } from './estado-vacio';

export interface Barra {
  etiqueta: string;
  valor: number;
}

/**
 * Gráfico de barras horizontales del panel (INT-04: incidencias por tipo, zona y repartidor).
 * Cada barra se mide contra la mayor; el número va siempre escrito a la derecha.
 * Sin datos muestra "Sin datos".
 *
 *   <app-barras [datos]="[{ etiqueta: 'Centro', valor: 5 }, { etiqueta: 'Cuba', valor: 3 }]" />
 */
@Component({
  selector: 'app-barras',
  imports: [EstadoVacio],
  host: { class: 'd-block' },
  template: `
    @if (datos().length > 0) {
      <ul class="list-unstyled d-grid gap-2 mb-0">
        @for (barra of datos(); track barra.etiqueta) {
          <li class="fila">
            <span>{{ barra.etiqueta }}</span>
            <span class="pista" aria-hidden="true">
              <span class="relleno" [style.width.%]="(barra.valor / maximo()) * 100"></span>
            </span>
            <span class="cifra fw-semibold text-end">{{ barra.valor }}</span>
          </li>
        }
      </ul>
    } @else {
      <app-estado-vacio icono="bi-bar-chart" titulo="Sin datos" [compacto]="true" />
    }
  `,
  styles: `
    @use '../../estilos/variables' as tema;

    .fila {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 2.5rem;
      align-items: center;
      gap: 0.75rem;
    }

    .pista {
      height: 0.625rem;
      overflow: hidden;
      border-radius: 50rem;
      background: tema.$gray-200;
    }

    .relleno {
      display: block;
      height: 100%;
      border-radius: inherit;
      background: tema.$primary;
    }
  `,
})
export class Barras {
  readonly datos = input<Barra[]>([]);

  protected readonly maximo = computed(() =>
    Math.max(1, ...this.datos().map((barra) => barra.valor)),
  );
}
