import { Component, OnInit, inject, signal } from '@angular/core';

import { ApiService } from '../../core/api.service';
import { mensajeDeError } from '../../core/etiquetas';
import { MensajesService } from '../../core/mensajes.service';
import { Disponibilidad, Paginado, Repartidor, Respuesta } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

/**
 * Repartidores y disponibilidad (INT-11): el interruptor cambia la disponibilidad al momento.
 * Ajuste al modelo: columna "Zona" (una sola por repartidor).
 */
@Component({
  selector: 'app-repartidores',
  imports: [Tarjeta, Tabla, BadgeEstado],
  templateUrl: './repartidores.html',
})
export class Repartidores implements OnInit {
  private readonly api = inject(ApiService);
  private readonly mensajes = inject(MensajesService);

  protected readonly columnas: Columna[] = [
    'Repartidor',
    'Zona',
    'Disponibilidad',
    'Pedidos activos',
    'Cuenta',
  ];

  protected readonly datos = signal<Repartidor[]>([]);
  protected readonly meta = signal<Paginado<Repartidor>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  /** Repartidor cuya disponibilidad se está guardando (su interruptor se bloquea). */
  protected readonly guardandoId = signal<number | null>(null);

  ngOnInit(): void {
    this.cargar(1);
  }

  protected cargar(pagina: number): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Paginado<Repartidor>>('/repartidores', { page: pagina }).subscribe({
      next: (respuesta) => {
        this.datos.set(respuesta.data);
        this.meta.set(respuesta.meta);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  protected cambiarDisponibilidad(repartidor: Repartidor, evento: Event): void {
    const interruptor = evento.target as HTMLInputElement;
    const disponibilidad: Disponibilidad = interruptor.checked ? 'disponible' : 'no_disponible';

    this.guardandoId.set(repartidor.id);

    this.api
      .put<Respuesta<Repartidor>>(`/repartidores/${repartidor.id}/disponibilidad`, {
        disponibilidad,
      })
      .subscribe({
        next: (respuesta) => {
          this.guardandoId.set(null);
          this.datos.update((datos) =>
            datos.map((dato) => (dato.id === repartidor.id ? respuesta.data : dato)),
          );
          this.mensajes.exito(`Disponibilidad de ${repartidor.nombre} actualizada.`);
        },
        error: (error: unknown) => {
          this.guardandoId.set(null);
          // El interruptor vuelve a como estaba.
          interruptor.checked = repartidor.disponibilidad === 'disponible';
          this.mensajes.error(mensajeDeError(error));
        },
      });
  }
}
