import { Component, input } from '@angular/core';

/**
 * Tarjeta de indicador del panel (INT-04): una cifra grande y qué significa.
 * Sin valor (null) muestra "Sin datos", que es lo que pide el mockup cuando no hay base de cálculo
 * (por ejemplo, % de entregas exitosas sin ningún pedido entregado ni no entregado).
 *
 *   <app-indicador etiqueta="Entregas exitosas" [valor]="porcentajeExito()" />   → "75 %"
 */
@Component({
  selector: 'app-indicador',
  host: { class: 'd-block' },
  template: `
    <div class="card h-100 text-center">
      <div class="card-body d-flex flex-column justify-content-center gap-1">
        @if (valor() !== null) {
          <div class="valor cifra">{{ valor() }}</div>
          <div class="text-body-secondary">{{ etiqueta() }}</div>
        } @else {
          <div class="valor valor--vacio">Sin datos</div>
          <div class="text-body-secondary">{{ etiqueta() }}</div>
          <div class="small text-body-secondary">No hay base de cálculo en el periodo.</div>
        }
      </div>
    </div>
  `,
  styles: `
    @use '../../estilos/variables' as tema;

    .valor {
      color: tema.$lateral-fondo;
      font-size: 2.25rem;
      font-weight: 800;
      line-height: 1.15;
    }

    .valor--vacio {
      color: tema.$gray-500;
      font-size: 1.5rem;
    }
  `,
})
export class Indicador {
  readonly etiqueta = input.required<string>();
  /** Texto ya formateado ("75 %", "42 min") o null si no hay datos. */
  readonly valor = input<string | null>(null);
}
