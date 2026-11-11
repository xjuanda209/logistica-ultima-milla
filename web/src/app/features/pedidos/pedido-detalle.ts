import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { ESTADOS_PEDIDO, PRIORIDADES, mensajeDeError } from '../../core/etiquetas';
import { CambioPedido, EventoHistorial, Pedido, Prioridad, Respuesta } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { EstadoVacio } from '../../shared/estado-vacio';
import { Tarjeta } from '../../shared/tarjeta';

const CAMPOS: Record<CambioPedido['campo'], string> = {
  prioridad: 'Prioridad',
  franja_inicio: 'Inicio de la franja',
  franja_fin: 'Fin de la franja',
  observaciones: 'Observaciones',
  lineas: 'Productos',
};

const CANALES: Record<EventoHistorial['canal'], string> = { web: 'Web', movil: 'Móvil' };

const TIPOS_EVIDENCIA = { foto_entrega: 'Foto de entrega', comprobante: 'Comprobante' } as const;

/**
 * Detalle del pedido (INT-08): encabezado, datos, productos, historial (más reciente primero),
 * evidencias e incidencias. Este módulo solo pone el botón "Editar pedido" (en estado «Creado»);
 * asignar, registrar incidencia y cancelar son de Despacho e Incidencias.
 * Ajuste al modelo: se muestra quién creó el pedido y la fecha de última modificación, no quién modificó.
 */
@Component({
  selector: 'app-pedido-detalle',
  imports: [DatePipe, RouterLink, BadgeEstado, Tarjeta, EstadoVacio],
  templateUrl: './pedido-detalle.html',
  styles: `
    /* Línea de tiempo del historial: un punto por evento unido por una línea vertical. */
    .linea-tiempo {
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .linea-tiempo > li {
      position: relative;
      padding: 0 0 1.25rem 1.75rem;
    }

    .linea-tiempo > li:last-child {
      padding-bottom: 0;
    }

    .linea-tiempo > li::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0.3rem;
      width: 0.85rem;
      height: 0.85rem;
      border-radius: 50%;
      background: var(--bs-primary);
      box-shadow:
        0 0 0 2px var(--bs-body-bg),
        0 0 0 3px var(--bs-primary);
    }

    .linea-tiempo > li:not(:last-child)::after {
      content: '';
      position: absolute;
      left: calc(0.425rem - 1px);
      top: 1.4rem;
      bottom: 0;
      width: 2px;
      background: var(--bs-border-color);
    }
  `,
})
export class PedidoDetalle implements OnInit {
  private readonly api = inject(ApiService);

  /** Llega desde la URL (/pedidos/:id) gracias a withComponentInputBinding(). */
  readonly id = input.required<string>();

  protected readonly estados = ESTADOS_PEDIDO;
  protected readonly canales = CANALES;
  protected readonly tiposEvidencia = TIPOS_EVIDENCIA;

  protected readonly pedido = signal<Pedido | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  /** El más reciente primero (la API lo entrega en orden cronológico). */
  protected readonly historial = computed(() => [...(this.pedido()?.historial ?? [])].reverse());

  private readonly puedeGestionar = inject(AuthService).tieneRol(
    'administrador',
    'operador',
    'comercio',
  );
  /** Solo se edita en «Creado»; el backend también lo exige. */
  protected readonly puedeEditar = computed(
    () => this.puedeGestionar && this.pedido()?.estado === 'creado',
  );

  ngOnInit(): void {
    this.cargar();
  }

  protected cargar(): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Respuesta<Pedido>>(`/pedidos/${this.id()}`).subscribe({
      next: (respuesta) => {
        this.pedido.set(respuesta.data);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  protected nombreCampo(cambio: CambioPedido): string {
    return CAMPOS[cambio.campo] ?? cambio.campo;
  }

  protected esFecha(cambio: CambioPedido): boolean {
    return cambio.campo === 'franja_inicio' || cambio.campo === 'franja_fin';
  }

  /** Valor de un campo editado, en palabras ("Media", "Pan × 2") o "(vacío)". */
  protected valorDe(cambio: CambioPedido, valor: string | null): string {
    if (valor === null || valor === '') {
      return '(vacío)';
    }

    return cambio.campo === 'prioridad' ? (PRIORIDADES[valor as Prioridad]?.texto ?? valor) : valor;
  }
}
