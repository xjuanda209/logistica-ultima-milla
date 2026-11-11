import { Component, input } from '@angular/core';

let contador = 0;

/**
 * Tarjeta blanca con título y, a la derecha del título, sus acciones (mockups INT-02, INT-03, INT-04):
 *
 *   <app-tarjeta titulo="Usuarios del sistema">
 *     <button acciones class="btn btn-primary" (click)="abrirFormulario()">+ Nuevo usuario</button>
 *     ...contenido (una <app-tabla>, un formulario, un indicador)...
 *   </app-tarjeta>
 *
 * Sin título queda solo el recuadro (por ejemplo, la barra de filtros del panel).
 */
@Component({
  selector: 'app-tarjeta',
  host: { class: 'd-block' },
  template: `
    <section class="card h-100" [attr.aria-labelledby]="titulo() ? idTitulo : null">
      <div class="card-body">
        @if (titulo()) {
          <div class="encabezado d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="card-title mb-0" [id]="idTitulo">{{ titulo() }}</h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <ng-content select="[acciones]" />
            </div>
          </div>
        }
        <ng-content />
      </div>
    </section>
  `,
  styles: `
    /* Una tarjeta con solo título y acciones (INT-02) no deja espacio vacío debajo. */
    .encabezado:not(:last-child) {
      margin-bottom: 1rem;
    }
  `,
})
export class Tarjeta {
  readonly titulo = input<string>();

  protected readonly idTitulo = `tarjeta-titulo-${++contador}`;
}
