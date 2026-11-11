import { DOCUMENT } from '@angular/common';
import {
  Component,
  DestroyRef,
  ElementRef,
  afterNextRender,
  inject,
  input,
  output,
} from '@angular/core';

let contador = 0;
/** Modales abiertos a la vez (una confirmación encima de un formulario, por ejemplo). */
let abiertos = 0;

/**
 * Ventana modal con los estilos de Bootstrap (sin su JavaScript).
 * Se muestra mientras exista en la plantilla, así que se controla con un @if:
 *
 *   @if (modalAbierto()) {
 *     <app-modal titulo="Nueva zona" (cerrar)="cerrarFormulario()"> ...formulario... </app-modal>
 *   }
 *
 * Al abrirse pone el foco en el primer campo y bloquea el desplazamiento de la página;
 * Escape o la X la cierran. Para pedir un "¿Seguro?" usa <app-confirmacion>.
 */
@Component({
  selector: 'app-modal',
  template: `
    <div
      class="modal d-block"
      tabindex="-1"
      role="dialog"
      aria-modal="true"
      [attr.aria-labelledby]="idTitulo"
      (keydown.escape)="cerrar.emit()"
    >
      <div
        class="modal-dialog modal-dialog-centered modal-dialog-scrollable"
        [class.modal-lg]="ancho()"
      >
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5" [id]="idTitulo">{{ titulo() }}</h2>
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
  /** Modal más ancho, para formularios de varias columnas. */
  readonly ancho = input(false);
  readonly cerrar = output<void>();

  protected readonly idTitulo = `modal-titulo-${++contador}`;

  constructor() {
    const documento = inject(DOCUMENT);
    const elemento = inject<ElementRef<HTMLElement>>(ElementRef).nativeElement;

    abiertos++;
    documento.body.classList.add('modal-open');
    inject(DestroyRef).onDestroy(() => {
      abiertos--;
      if (abiertos === 0) {
        documento.body.classList.remove('modal-open');
      }
    });

    afterNextRender(() => {
      const primerCampo = elemento.querySelector<HTMLElement>(
        '.modal-body input:not([type=hidden]), .modal-body select, .modal-body textarea',
      );
      (primerCampo ?? elemento.querySelector<HTMLElement>('.modal'))?.focus();
    });
  }
}
