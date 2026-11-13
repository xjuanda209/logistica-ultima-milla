import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { CatalogosService } from '../../core/catalogos.service';
import { ErroresDeCampos, camposDeError, mensajeDeFormulario } from '../../core/etiquetas';
import { Incidencia, Pedido, Respuesta, TipoIncidencia } from '../../core/modelos';
import { Campo } from '../../shared/campo';
import { Modal } from '../../shared/modal';

/**
 * Ventana para registrar una incidencia desde el detalle del pedido (INT-19, web): tipo (solo
 * los activos), descripción y foto opcional (JPG o PNG, máximo 5 MB; lo valida el backend).
 *
 *   @if (registrandoIncidencia()) {
 *     <app-registrar-incidencia [pedido]="p" (cerrar)="registrandoIncidencia.set(false)"
 *                               (registrada)="alRegistrarIncidencia($event)" />
 *   }
 */
@Component({
  selector: 'app-registrar-incidencia',
  imports: [FormsModule, Modal, Campo],
  template: `
    <app-modal [titulo]="'Registrar incidencia · ' + pedido().numero" (cerrar)="cerrar.emit()">
      <form class="d-grid gap-3" (ngSubmit)="registrar()">
        <app-campo
          etiqueta="Tipo de incidencia"
          para="incidencia-tipo"
          [obligatorio]="true"
          [errores]="errores()['tipo_incidencia_id']"
        >
          <select
            id="incidencia-tipo"
            name="tipo_incidencia_id"
            class="form-select"
            [class.is-invalid]="errores()['tipo_incidencia_id']"
            [(ngModel)]="tipoId"
          >
            <option value="">Selecciona un tipo</option>
            @for (tipo of tipos(); track tipo.id) {
              <option [value]="tipo.id">{{ tipo.nombre }}</option>
            }
          </select>
        </app-campo>

        <app-campo
          etiqueta="Descripción"
          para="incidencia-descripcion"
          [obligatorio]="true"
          [errores]="errores()['descripcion']"
        >
          <textarea
            id="incidencia-descripcion"
            name="descripcion"
            class="form-control"
            rows="3"
            maxlength="1000"
            [class.is-invalid]="errores()['descripcion']"
            [(ngModel)]="descripcion"
          ></textarea>
        </app-campo>

        <app-campo
          etiqueta="Foto"
          nota="opcional"
          para="incidencia-foto"
          ayuda="JPG o PNG de máximo 5 MB."
          [errores]="errores()['foto']"
        >
          <input
            id="incidencia-foto"
            name="foto"
            type="file"
            class="form-control"
            accept="image/jpeg,image/png"
            [class.is-invalid]="errores()['foto']"
            (change)="elegirFoto($event)"
          />
        </app-campo>

        @if (errorFormulario(); as mensaje) {
          <div class="alert alert-danger mb-0" role="alert">{{ mensaje }}</div>
        }

        <div class="d-flex justify-content-end gap-2 mt-2">
          <button type="button" class="btn btn-outline-secondary" (click)="cerrar.emit()">
            Cancelar
          </button>
          <button type="submit" class="btn btn-primary" [disabled]="guardando()">
            {{ guardando() ? 'Registrando…' : 'Registrar' }}
          </button>
        </div>
      </form>
    </app-modal>
  `,
})
export class RegistrarIncidencia implements OnInit {
  private readonly api = inject(ApiService);
  private readonly catalogos = inject(CatalogosService);

  readonly pedido = input.required<Pedido>();
  readonly cerrar = output<void>();
  /** La incidencia recién creada, tal como la devuelve la API. */
  readonly registrada = output<Incidencia>();

  protected readonly tipos = signal<TipoIncidencia[]>([]);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});

  protected tipoId = '';
  protected descripcion = '';
  private foto: File | null = null;

  ngOnInit(): void {
    this.catalogos.tiposIncidencia().subscribe({
      next: (tipos) => this.tipos.set(tipos),
      error: (error: unknown) => this.errorFormulario.set(mensajeDeFormulario(error)),
    });
  }

  protected elegirFoto(evento: Event): void {
    this.foto = (evento.target as HTMLInputElement).files?.[0] ?? null;
  }

  protected registrar(): void {
    // Lleva un archivo: se envía como formulario (multipart), no como JSON.
    const cuerpo = new FormData();
    cuerpo.append('tipo_incidencia_id', this.tipoId);
    cuerpo.append('descripcion', this.descripcion);
    if (this.foto) {
      cuerpo.append('foto', this.foto);
    }

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    this.api
      .post<Respuesta<Incidencia>>(`/pedidos/${this.pedido().id}/incidencias`, cuerpo)
      .subscribe({
        next: (respuesta) => {
          this.guardando.set(false);
          this.registrada.emit(respuesta.data);
        },
        error: (error: unknown) => {
          this.guardando.set(false);
          this.errorFormulario.set(mensajeDeFormulario(error));
          this.errores.set(camposDeError(error));
        },
      });
  }
}
