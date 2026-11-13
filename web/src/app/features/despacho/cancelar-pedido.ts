import { Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { ErroresDeCampos, camposDeError, mensajeDeFormulario } from '../../core/etiquetas';
import { Pedido, Respuesta } from '../../core/modelos';
import { Campo } from '../../shared/campo';
import { Modal } from '../../shared/modal';

/**
 * Ventana para cancelar un pedido (INT-10), con motivo obligatorio. Se usa desde el detalle:
 *
 *   @if (cancelando()) {
 *     <app-cancelar-pedido [pedido]="p" (cerrar)="cancelando.set(false)" (cancelado)="actualizar($event)" />
 *   }
 */
@Component({
  selector: 'app-cancelar-pedido',
  imports: [FormsModule, Modal, Campo],
  template: `
    <app-modal [titulo]="'Cancelar pedido ' + pedido().numero" (cerrar)="cerrar.emit()">
      <form (ngSubmit)="confirmar()">
        <div class="alert alert-warning" role="alert">
          Solo se pueden cancelar pedidos en estado «Creado» o «Asignado». La cancelación no se
          puede deshacer.
        </div>

        <app-campo
          etiqueta="Motivo de la cancelación"
          para="cancelacion-motivo"
          [obligatorio]="true"
          [errores]="errores()['motivo']"
        >
          <textarea
            id="cancelacion-motivo"
            name="motivo"
            class="form-control"
            rows="3"
            maxlength="500"
            [class.is-invalid]="errores()['motivo']"
            [(ngModel)]="motivo"
          ></textarea>
        </app-campo>

        @if (errorFormulario(); as mensaje) {
          <div class="alert alert-danger mt-3 mb-0" role="alert">{{ mensaje }}</div>
        }

        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-outline-secondary" (click)="cerrar.emit()">
            Volver
          </button>
          <button type="submit" class="btn btn-danger" [disabled]="guardando()">
            {{ guardando() ? 'Cancelando…' : 'Confirmar cancelación' }}
          </button>
        </div>
      </form>
    </app-modal>
  `,
})
export class CancelarPedido {
  private readonly api = inject(ApiService);

  readonly pedido = input.required<Pedido>();
  readonly cerrar = output<void>();
  /** El pedido ya cancelado, tal como lo devuelve la API (con su historial). */
  readonly cancelado = output<Pedido>();

  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected motivo = '';

  protected confirmar(): void {
    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    this.api
      .put<Respuesta<Pedido>>(`/pedidos/${this.pedido().id}/cancelacion`, { motivo: this.motivo })
      .subscribe({
        next: (respuesta) => {
          this.guardando.set(false);
          this.cancelado.emit(respuesta.data);
        },
        error: (error: unknown) => {
          this.guardando.set(false);
          this.errorFormulario.set(mensajeDeFormulario(error));
          this.errores.set(camposDeError(error));
        },
      });
  }
}
