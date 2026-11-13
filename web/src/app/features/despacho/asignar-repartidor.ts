import { Component, OnInit, computed, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { mensajeDeError } from '../../core/etiquetas';
import { Pedido, Repartidor, Respuesta } from '../../core/modelos';
import { EstadoVacio } from '../../shared/estado-vacio';
import { Modal } from '../../shared/modal';

/**
 * Ventana para asignar o reasignar un pedido (INT-09). Se usa desde el detalle del pedido:
 *
 *   @if (asignando()) {
 *     <app-asignar-repartidor [pedido]="p" (cerrar)="asignando.set(false)" (asignado)="actualizar($event)" />
 *   }
 *
 * "Propuesta" lista a quienes pueden recibirlo, de menor a mayor carga (la API ya los ordena);
 * "Elegir manualmente", a todos los activos, con los no elegibles en gris y su motivo en rojo.
 * Ajuste al modelo: columna "Zona" (una sola por repartidor).
 */
@Component({
  selector: 'app-asignar-repartidor',
  imports: [FormsModule, Modal, EstadoVacio],
  templateUrl: './asignar-repartidor.html',
})
export class AsignarRepartidor implements OnInit {
  private readonly api = inject(ApiService);

  readonly pedido = input.required<Pedido>();
  readonly cerrar = output<void>();
  /** El pedido ya asignado, tal como lo devuelve la API (con su historial). */
  readonly asignado = output<Pedido>();

  protected readonly pestana = signal<'propuesta' | 'manual'>('propuesta');
  protected readonly repartidores = signal<Repartidor[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly elegido = signal<number | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);

  protected readonly elegibles = computed(() => this.repartidores().filter((r) => r.elegible));
  protected readonly visibles = computed(() =>
    this.pestana() === 'propuesta' ? this.elegibles() : this.repartidores(),
  );
  protected readonly titulo = computed(() => {
    const pedido = this.pedido();
    const accion = pedido.estado === 'asignado' ? 'Reasignar' : 'Asignar';

    return `${accion} repartidor · Pedido ${pedido.numero} (zona ${pedido.zona?.nombre ?? '—'})`;
  });

  ngOnInit(): void {
    this.cargar();
  }

  protected cargar(): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Respuesta<Repartidor[]>>(`/pedidos/${this.pedido().id}/repartidores`).subscribe({
      next: (respuesta) => {
        this.repartidores.set(respuesta.data);
        // La propuesta: el elegible con menos carga queda marcado.
        this.elegido.set(respuesta.data.find((r) => r.elegible)?.id ?? null);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  protected confirmar(): void {
    const repartidorId = this.elegido();
    if (repartidorId === null) {
      return;
    }

    this.guardando.set(true);
    this.errorFormulario.set(null);

    this.api
      .put<Respuesta<Pedido>>(`/pedidos/${this.pedido().id}/repartidor`, {
        repartidor_id: repartidorId,
      })
      .subscribe({
        next: (respuesta) => {
          this.guardando.set(false);
          this.asignado.emit(respuesta.data);
        },
        error: (error: unknown) => {
          this.guardando.set(false);
          this.errorFormulario.set(mensajeDeError(error));
          // La carga o la disponibilidad pudo cambiar mientras la ventana estaba abierta.
          this.cargar();
        },
      });
  }
}
