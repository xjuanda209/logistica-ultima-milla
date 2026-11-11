import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { ESTADOS_PEDIDO, mensajeDeError } from '../../core/etiquetas';
import { EstadoPedido, Respuesta, ResumenPanel } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';

@Component({
  selector: 'app-panel',
  imports: [RouterLink, BadgeEstado],
  templateUrl: './panel.html',
})
export class Panel implements OnInit {
  private readonly api = inject(ApiService);

  protected readonly resumen = signal<ResumenPanel | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  /** Una tarjeta por estado, en el orden del flujo del pedido. */
  protected readonly tarjetas = computed(() => {
    const resumen = this.resumen();
    if (!resumen) {
      return [];
    }

    return (Object.keys(ESTADOS_PEDIDO) as EstadoPedido[]).map((estado) => ({
      estado,
      ...ESTADOS_PEDIDO[estado],
      cantidad: resumen.por_estado[estado] ?? 0,
    }));
  });

  ngOnInit(): void {
    this.api.get<Respuesta<ResumenPanel>>('/panel/resumen').subscribe({
      next: (respuesta) => {
        this.resumen.set(respuesta.data);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }
}
