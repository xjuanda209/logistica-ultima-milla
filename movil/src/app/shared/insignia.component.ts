import { Component, computed, input } from '@angular/core';

import type { TipoInsignia } from '../core/etiquetas';
import { INSIGNIAS } from '../core/etiquetas';

/**
 * Insignia (badge) con los mismos colores que la web. Siempre lleva texto, nunca solo color.
 *
 *   <app-insignia [valor]="pedido.estado" />                                   estado del pedido
 *   <app-insignia tipo="prioridad" prefijo="Prioridad" [valor]="pedido.prioridad" />   "Prioridad media"
 *   <app-insignia tipo="incidencia" [valor]="incidencia.estado" />
 */
@Component({
  selector: 'app-insignia',
  template: `
    @if (etiqueta(); as e) {
      <span class="insignia" [style.background-color]="e.fondo" [style.color]="e.color">{{ texto() }}</span>
    }
  `,
  styles: `
    .insignia {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 50rem;
      font-weight: 600;
      font-size: 0.875rem;
      white-space: nowrap;
    }
  `,
})
export class InsigniaComponent {
  readonly tipo = input<TipoInsignia>('pedido');
  readonly valor = input<string | null | undefined>();
  readonly prefijo = input('');

  protected readonly etiqueta = computed(() => {
    const valor = this.valor();

    return valor ? ((INSIGNIAS[this.tipo()] as Record<string, { texto: string; fondo: string; color: string }>)[valor] ?? null) : null;
  });

  protected readonly texto = computed(() => {
    const texto = this.etiqueta()?.texto ?? '';

    return this.prefijo() ? `${this.prefijo()} ${texto.toLowerCase()}` : texto;
  });
}
