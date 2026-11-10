import { Component, input, output } from '@angular/core';

/**
 * Ventana modal con los estilos de Bootstrap (sin su JavaScript).
 * Se muestra mientras exista en la plantilla, así que se controla con un @if:
 *
 *   @if (modalAbierto()) {
 *     <app-modal titulo="Nueva zona" (cerrar)="cerrarModal()"> ...formulario... </app-modal>
 *   }
 */
@Component({
  selector: 'app-modal',
  template: `
    <div
      class="modal d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
      [attr.aria-label]="titulo()"
      (keydown.escape)="cerrar.emit()"
    >
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5">{{ titulo() }}</h2>
            <button
              type="button"
              class="btn-close"
              aria-label="Cerrar"
              (click)="cerrar.emit()"
            ></button>
          </div>
          <div class="modal-body">
            <ng-content />
          </div>
        </div>
      </div>
    </div>
    <div class="modal-backdrop show"></div>
  `,
})
export class Modal {
  readonly titulo = input.required<string>();
  readonly cerrar = output<void>();
}
