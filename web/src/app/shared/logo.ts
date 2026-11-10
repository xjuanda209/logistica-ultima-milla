import { Component, input } from '@angular/core';

/**
 * Isotipo de Última Milla: una ruta punteada que termina en el punto de entrega.
 *   <app-logo />            32 px
 *   <app-logo [tamano]="44" />
 */
@Component({
  selector: 'app-logo',
  template: `
    <svg
      [attr.width]="tamano()"
      [attr.height]="tamano()"
      viewBox="0 0 32 32"
      fill="none"
      aria-hidden="true"
      class="d-block"
    >
      <rect width="32" height="32" rx="8" fill="var(--bs-acento)" />
      <circle cx="10" cy="23" r="2" fill="#fff" />
      <path
        d="M10 23c0-7 12-3.5 12-11"
        stroke="#fff"
        stroke-width="2"
        stroke-linecap="round"
        stroke-dasharray="0.1 4"
      />
      <circle cx="22" cy="10" r="4" fill="#fff" />
      <circle cx="22" cy="10" r="1.6" fill="var(--bs-acento)" />
    </svg>
  `,
})
export class Logo {
  readonly tamano = input(32);
}
