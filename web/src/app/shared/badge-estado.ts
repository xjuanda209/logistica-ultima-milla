import { Component, computed, input } from '@angular/core';

import { TipoBadge, etiquetaDe } from '../core/etiquetas';

/**
 * Insignia de un valor fijo, siempre con texto (el color nunca va solo). Úsala SIEMPRE que se
 * muestre un estado o una prioridad:
 *
 *   <app-badge-estado [valor]="pedido.estado" />                       estado del pedido
 *   <app-badge-estado tipo="prioridad" [valor]="pedido.prioridad" />
 *   <app-badge-estado tipo="incidencia" [valor]="incidencia.estado" />
 *   <app-badge-estado tipo="activo" [valor]="usuario.activo" />         Activo / Inactivo
 *
 * Textos y colores salen de core/etiquetas.ts; no se repiten en las páginas.
 * Si el valor viene vacío (por ejemplo, una incidencia sin prioridad) muestra "—".
 */
@Component({
  selector: 'app-badge-estado',
  template: `
    @if (etiqueta(); as e) {
      <span class="badge" [style.background-color]="e.fondo" [style.color]="e.color">{{
        e.texto
      }}</span>
    } @else {
      <span class="text-body-secondary">—</span>
    }
  `,
})
export class BadgeEstado {
  readonly tipo = input<TipoBadge>('pedido');
  readonly valor = input.required<string | boolean | null | undefined>();

  protected readonly etiqueta = computed(() => etiquetaDe(this.tipo(), this.valor()));
}
