import { Component, input, output } from '@angular/core';

import { Modal } from './modal';

/**
 * Modal de confirmación para acciones que conviene pensar dos veces (desactivar, cancelar…):
 *
 *   @if (porDesactivar(); as usuario) {
 *     <app-confirmacion
 *       titulo="Desactivar usuario"
 *       [mensaje]="'¿Desactivar a ' + usuario.correo + '? Sus sesiones abiertas se cerrarán.'"
 *       textoConfirmar="Desactivar" [peligro]="true" [procesando]="cambiandoEstado() !== null"
 *       (confirmar)="cambiarEstado(usuario)" (cancelar)="porDesactivar.set(null)"
 *     />
 *   }
 *
 * Lo que se ponga dentro aparece debajo del mensaje (por ejemplo, el error de la API).
 */
@Component({
  selector: 'app-confirmacion',
  imports: [Modal],
  template: `
    <app-modal [titulo]="titulo()" (cerrar)="cancelar.emit()">
      <p class="mb-0">{{ mensaje() }}</p>
      <ng-content />
      <div class="d-flex justify-content-end gap-2 mt-4">
        <button
          type="button"
          class="btn btn-outline-secondary"
          [disabled]="procesando()"
          (click)="cancelar.emit()"
        >
          Cancelar
        </button>
        <button
          type="button"
          class="btn"
          [class]="peligro() ? 'btn-danger' : 'btn-primary'"
          [disabled]="procesando()"
          (click)="confirmar.emit()"
        >
          {{ procesando() ? 'Procesando…' : textoConfirmar() }}
        </button>
      </div>
    </app-modal>
  `,
})
export class Confirmacion {
  readonly titulo = input.required<string>();
  readonly mensaje = input.required<string>();
  readonly textoConfirmar = input('Confirmar');
  /** Botón rojo, para acciones que quitan acceso o no se deshacen fácil. */
  readonly peligro = input(false);
  readonly procesando = input(false);

  readonly confirmar = output<void>();
  readonly cancelar = output<void>();
}
