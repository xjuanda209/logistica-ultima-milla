import { Component, input } from '@angular/core';

/**
 * Muestra debajo de un input el error de validación que devolvió la API para ese campo:
 *
 *   <input class="form-control" [class.is-invalid]="errores()['nombre']" ... />
 *   <app-error-campo [mensajes]="errores()['nombre']" />
 */
@Component({
  selector: 'app-error-campo',
  template: `
    @if (mensajes(); as lista) {
      <div class="invalid-feedback d-block">{{ lista[0] }}</div>
    }
  `,
})
export class ErrorCampo {
  readonly mensajes = input<string[] | undefined>();
}
