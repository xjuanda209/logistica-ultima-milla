import { Component, input } from '@angular/core';

import { Etiqueta } from '../core/etiquetas';

/**
 * Insignia con punto de color para cualquier valor fijo definido en core/etiquetas.ts:
 *
 *   <app-insignia [etiqueta]="prioridades[pedido.prioridad]" />
 *   <app-insignia [etiqueta]="usuario.activo ? activo.activo : activo.inactivo" />
 *
 * Para el estado de un pedido usa <app-badge-estado>, que ya sabe buscar la etiqueta.
 * El color nunca va solo: siempre lo acompaña el texto.
 */
@Component({
  selector: 'app-insignia',
  template: `
    <span class="insignia" [style.--color]="etiqueta().color">
      <span class="insignia__punto" aria-hidden="true"></span>{{ etiqueta().texto }}
    </span>
  `,
  styles: `
    .insignia {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.125rem 0.625rem 0.125rem 0.5rem;
      border-radius: 50rem;
      background: color-mix(in srgb, var(--color) 12%, white);
      color: color-mix(in srgb, var(--color) 62%, black);
      font-size: 0.75rem;
      font-weight: 500;
      line-height: 1.25rem;
      white-space: nowrap;
    }

    .insignia__punto {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--color);
    }
  `,
})
export class Insignia {
  readonly etiqueta = input.required<Etiqueta>();
}
