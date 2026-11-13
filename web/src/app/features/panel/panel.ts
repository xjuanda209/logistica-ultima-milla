import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';

import { ApiService } from '../../core/api.service';
import { CatalogosService } from '../../core/catalogos.service';
import {
  ESTADOS_PEDIDO,
  ErroresDeCampos,
  camposDeError,
  mensajeDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { finDelDia, inicioDelDia } from '../../core/fechas';
import { MensajesService } from '../../core/mensajes.service';
import {
  Comercio,
  EstadoPedido,
  IndicadoresPanel,
  Paginado,
  Pedido,
  Respuesta,
  Zona,
} from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Barras } from '../../shared/barras';
import { Campo } from '../../shared/campo';
import { Indicador } from '../../shared/indicador';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

export interface FiltrosPanel {
  desde: string;
  hasta: string;
  zona_id: number | null;
  comercio_id: number | null;
  estado: EstadoPedido | null;
}

/**
 * Panel operativo e indicadores (INT-04), para administrador y operador. Al entrar y con cada
 * "Aplicar" pide, con los mismos filtros:
 *   - GET /panel/indicadores: pedidos por estado, % de entregas exitosas, tiempo promedio de
 *     entrega e incidencias por tipo, zona y repartidor (los cálculos los hace el backend);
 *   - GET /pedidos: la lista, con el ⚠ de incidencia abierta.
 * Un indicador sin base de cálculo llega en null y se muestra como "Sin datos".
 */
@Component({
  selector: 'app-panel',
  imports: [FormsModule, RouterLink, Tarjeta, Tabla, Campo, BadgeEstado, Indicador, Barras],
  templateUrl: './panel.html',
})
export class Panel implements OnInit {
  private readonly api = inject(ApiService);
  private readonly catalogos = inject(CatalogosService);
  private readonly mensajes = inject(MensajesService);

  protected readonly estados = Object.keys(ESTADOS_PEDIDO) as EstadoPedido[];
  protected readonly nombresEstado = ESTADOS_PEDIDO;
  protected readonly columnasPedidos: Columna[] = ['Número', 'Comercio', 'Zona', 'Estado', ''];

  // Opciones de los filtros
  protected readonly zonas = signal<Zona[]>([]);
  protected readonly comercios = signal<Comercio[]>([]);

  /** Lo que hay escrito en la barra de filtros (se aplica con "Aplicar"). */
  protected filtros: FiltrosPanel = filtrosIniciales();
  protected readonly filtrosAplicados = signal<FiltrosPanel>(filtrosIniciales());
  protected readonly errores = signal<ErroresDeCampos>({});

  // Indicadores
  protected readonly indicadores = signal<IndicadoresPanel | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  protected readonly porcentajeExito = computed(() => {
    const porcentaje = this.indicadores()?.porcentaje_exito ?? null;

    return porcentaje === null
      ? null
      : `${porcentaje.toLocaleString('es-CO', { maximumFractionDigits: 1 })} %`;
  });
  protected readonly tiempoPromedio = computed(() => {
    const minutos = this.indicadores()?.tiempo_promedio_minutos ?? null;

    return minutos === null ? null : `${minutos} min`;
  });

  // Lista de pedidos
  protected readonly pedidos = signal<Pedido[]>([]);
  protected readonly meta = signal<Paginado<Pedido>['meta'] | null>(null);
  protected readonly cargandoPedidos = signal(true);
  protected readonly errorPedidos = signal<string | null>(null);

  // Si se aplica otro filtro antes de que llegue la respuesta, la anterior se descarta.
  private peticionIndicadores?: Subscription;
  private peticionPedidos?: Subscription;

  ngOnInit(): void {
    this.catalogos.zonas().subscribe({
      next: (zonas) => this.zonas.set(zonas),
      error: (error: unknown) => this.mensajes.error(mensajeDeError(error)),
    });

    this.catalogos.comercios().subscribe({
      next: (comercios) => this.comercios.set(comercios),
      error: (error: unknown) => this.mensajes.error(mensajeDeError(error)),
    });

    this.aplicar();
  }

  protected aplicar(): void {
    this.filtrosAplicados.set({ ...this.filtros });
    this.cargarIndicadores();
    this.cargarPedidos(1);
  }

  protected cargarIndicadores(): void {
    this.cargando.set(true);
    this.error.set(null);
    this.errores.set({});

    this.peticionIndicadores?.unsubscribe();
    this.peticionIndicadores = this.api
      .get<Respuesta<IndicadoresPanel>>('/panel/indicadores', this.parametros())
      .subscribe({
        next: (respuesta) => {
          this.indicadores.set(respuesta.data);
          this.cargando.set(false);
        },
        error: (error: unknown) => {
          // Un filtro mal escrito se avisa junto a su campo; lo demás, con "Reintentar".
          this.errores.set(camposDeError(error));
          this.error.set(mensajeDeFormulario(error) ?? 'Revisa los filtros marcados en rojo.');
          this.indicadores.set(null);
          this.cargando.set(false);
        },
      });
  }

  protected cargarPedidos(pagina: number): void {
    this.cargandoPedidos.set(true);
    this.errorPedidos.set(null);

    this.peticionPedidos?.unsubscribe();
    this.peticionPedidos = this.api
      .get<Paginado<Pedido>>('/pedidos', { ...this.parametros(), page: pagina })
      .subscribe({
        next: (respuesta) => {
          this.pedidos.set(respuesta.data);
          this.meta.set(respuesta.meta);
          this.cargandoPedidos.set(false);
        },
        error: (error: unknown) => {
          this.errorPedidos.set(
            mensajeDeFormulario(error) ?? 'Revisa los filtros marcados en rojo.',
          );
          this.pedidos.set([]);
          this.meta.set(null);
          this.cargandoPedidos.set(false);
        },
      });
  }

  /** Los filtros aplicados, como los espera la API (las fechas, como instantes en UTC). */
  private parametros(): Record<string, string | number> {
    const filtros = this.filtrosAplicados();
    const parametros: Record<string, string | number> = {};
    const desde = inicioDelDia(filtros.desde);
    const hasta = finDelDia(filtros.hasta);

    if (desde) parametros['desde'] = desde;
    if (hasta) parametros['hasta'] = hasta;
    if (filtros.zona_id) parametros['zona_id'] = filtros.zona_id;
    if (filtros.comercio_id) parametros['comercio_id'] = filtros.comercio_id;
    if (filtros.estado) parametros['estado'] = filtros.estado;

    return parametros;
  }
}

/** Del primer día del mes hasta hoy, en la fecha local del dispositivo. */
function filtrosIniciales(): FiltrosPanel {
  const hoy = new Date();
  const primeroDelMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

  return {
    desde: fechaLocal(primeroDelMes),
    hasta: fechaLocal(hoy),
    zona_id: null,
    comercio_id: null,
    estado: null,
  };
}

/** "2026-11-09" (lo que espera un <input type="date">). */
function fechaLocal(fecha: Date): string {
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');

  return `${fecha.getFullYear()}-${mes}-${dia}`;
}
