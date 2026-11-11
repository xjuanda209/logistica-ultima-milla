import { Component, computed, input, output } from '@angular/core';

import { Paginado } from '../core/modelos';
import { EstadoVacio } from './estado-vacio';
import { Paginacion } from './paginacion';

/** Encabezado de columna: solo el texto, o el texto y una clase (por ejemplo "text-end"). */
export type Columna = string | { titulo: string; clase?: string };

/**
 * Tabla de los mockups: encabezados en MAYÚSCULAS, filas de carga, estado vacío, error con
 * "Reintentar" y paginación. Las filas (<tr>) se escriben en la página:
 *
 *   <app-tabla
 *     [columnas]="['Correo', 'Rol', 'Estado', 'Acciones']"
 *     [cargando]="cargando()" [error]="error()" [vacia]="datos().length === 0"
 *     textoVacio="Todavía no hay usuarios." [meta]="meta()"
 *     (cambiarPagina)="cargar($event)" (reintentar)="cargar(1)"
 *   >
 *     @for (usuario of datos(); track usuario.id) {
 *       <tr><td>{{ usuario.correo }}</td> ... </tr>
 *     }
 *   </app-tabla>
 *
 * Lo que se ponga con el atributo `vacio` aparece debajo del mensaje de tabla vacía
 * (por ejemplo, el botón para crear el primer registro).
 */
@Component({
  selector: 'app-tabla',
  imports: [EstadoVacio, Paginacion],
  host: { class: 'd-block' },
  template: `
    <div class="table-responsive">
      <table
        class="table align-middle mb-0"
        [class.table-hover]="hayFilas()"
        [attr.aria-busy]="cargando()"
      >
        @if (titulo(); as texto) {
          <caption class="visually-hidden">
            {{
              texto
            }}
          </caption>
        }
        <thead>
          <tr>
            @for (columna of encabezados(); track $index) {
              <th scope="col" [class]="columna.clase ?? ''">{{ columna.titulo }}</th>
            }
          </tr>
        </thead>

        @if (cargando()) {
          <tbody class="placeholder-glow" aria-hidden="true">
            @for (fila of filasDeCarga; track fila) {
              <tr>
                @for (columna of encabezados(); track $index) {
                  <td><span class="placeholder rounded col-8"></span></td>
                }
              </tr>
            }
          </tbody>
        } @else if (error(); as mensaje) {
          <tbody>
            <tr>
              <td class="border-0" [attr.colspan]="encabezados().length">
                <app-estado-vacio
                  tono="error"
                  icono="bi-exclamation-octagon"
                  titulo="No se pudo cargar la información"
                  [texto]="mensaje"
                >
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    (click)="reintentar.emit()"
                  >
                    Reintentar
                  </button>
                </app-estado-vacio>
              </td>
            </tr>
          </tbody>
        } @else if (vacia()) {
          <tbody>
            <tr>
              <td class="border-0" [attr.colspan]="encabezados().length">
                <app-estado-vacio
                  [icono]="iconoVacio()"
                  [titulo]="textoVacio()"
                  [texto]="detalleVacio()"
                >
                  <ng-content select="[vacio]" />
                </app-estado-vacio>
              </td>
            </tr>
          </tbody>
        }

        <tbody [hidden]="!hayFilas()">
          <ng-content />
        </tbody>
      </table>
    </div>

    @if (meta() && !error()) {
      <app-paginacion
        class="d-block mt-3"
        [meta]="meta()"
        [deshabilitada]="cargando()"
        (cambiar)="cambiarPagina.emit($event)"
      />
    }
  `,
})
export class Tabla {
  readonly columnas = input.required<Columna[]>();
  readonly cargando = input(false);
  readonly error = input<string | null>(null);
  readonly vacia = input(false);
  readonly textoVacio = input('No hay registros para mostrar.');
  readonly detalleVacio = input<string | null>(null);
  readonly iconoVacio = input('bi-inbox');
  /** Nombre de la tabla para lectores de pantalla (no se ve). */
  readonly titulo = input<string>();
  readonly meta = input<Paginado<unknown>['meta'] | null>(null);

  readonly cambiarPagina = output<number>();
  readonly reintentar = output<void>();

  protected readonly filasDeCarga = [1, 2, 3];

  protected readonly encabezados = computed(() =>
    this.columnas().map((columna) => (typeof columna === 'string' ? { titulo: columna } : columna)),
  );

  protected readonly hayFilas = computed(() => !this.cargando() && !this.error() && !this.vacia());
}
