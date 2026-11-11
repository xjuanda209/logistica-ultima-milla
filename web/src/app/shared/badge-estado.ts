import { Component, computed, input } from '@angular/core';

import { ESTADOS_PEDIDO } from '../core/etiquetas';
import { EstadoPedido } from '../core/modelos';
import { Insignia } from './insignia';

/**
 * Estado de un pedido, con su color. Úsalo SIEMPRE que se muestre el estado de un pedido:
 *
 *   <app-badge-estado [estado]="pedido.estado" />
 *
 * Los textos y colores salen de ESTADOS_PEDIDO (core/etiquetas.ts); no se repiten en las páginas.
 */
@Component({
  selector: 'app-badge-estado',
  imports: [Insignia],
  template: `<app-insignia [etiqueta]="etiqueta()" />`,
})
export class BadgeEstado {
  readonly estado = input.required<EstadoPedido>();

  protected readonly etiqueta = computed(() => ESTADOS_PEDIDO[this.estado()]);
}
