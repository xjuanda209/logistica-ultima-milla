import { Component, input } from '@angular/core';

import { ErrorCampo } from './error-campo';

/**
 * Campo de formulario: etiqueta (con * si es obligatorio), el control y, debajo, el error en rojo
 * que devolvió la API para ese campo (o una ayuda si no hay error):
 *
 *   <app-campo etiqueta="Correo electrónico" para="usuario-correo" [obligatorio]="true"
 *              [errores]="errores()['correo']">
 *     <input id="usuario-correo" name="correo" class="form-control" required
 *            [class.is-invalid]="errores()['correo']" [(ngModel)]="formulario.correo" />
 *   </app-campo>
 *
 * La clave de errores() es el nombre del campo en la API (comercio_id, no comercio).
 */
@Component({
  selector: 'app-campo',
  imports: [ErrorCampo],
  host: { class: 'd-block' },
  template: `
    <label class="form-label" [attr.for]="para()">
      {{ etiqueta() }}
      @if (obligatorio()) {
        <span aria-hidden="true">*</span>
      }
      @if (nota(); as texto) {
        <span class="fw-normal ms-1">({{ texto }})</span>
      }
    </label>
    <ng-content />
    <app-error-campo [mensajes]="errores()" />
    @if (ayuda() && !errores()) {
      <div class="form-text">{{ ayuda() }}</div>
    }
  `,
})
export class Campo {
  readonly etiqueta = input.required<string>();
  /** id del control, para que la etiqueta lo enfoque al hacer clic. */
  readonly para = input<string>();
  readonly obligatorio = input(false);
  /** Aclaración junto a la etiqueta, por ejemplo "rol comercio". */
  readonly nota = input<string>();
  readonly errores = input<string[] | undefined>();
  readonly ayuda = input<string>();
}
