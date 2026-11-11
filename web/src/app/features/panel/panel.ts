import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { CatalogosService } from '../../core/catalogos.service';
import { ESTADOS_PEDIDO, mensajeDeError } from '../../core/etiquetas';
import { MensajesService } from '../../core/mensajes.service';
import { Comercio, EstadoPedido, Pedido, Zona } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Barra, Barras } from '../../shared/barras';
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
 * Panel operativo e indicadores (INT-04). Por ahora es la MAQUETA: filtros, tarjetas de indicadores
 * y espacios para los gráficos, todos en "Sin datos".
 *
 * Para conectarlo (módulo 3, Despacho): al "Aplicar" pedir los datos con filtrosAplicados() y
 * llenar los signals de abajo. Las plantillas ya muestran los valores en cuanto dejen de ser null / [].
 *   - conteos:            pedidos por estado (incluye los estados en cero)
 *   - porcentajeExito:    entregados ÷ (entregados + no entregados) × 100, como "75 %"
 *   - tiempoPromedio:     minutos de asignado a entregado, como "42 min"
 *   - incidenciasPorTipo / PorZona / PorRepartidor: barras
 *   - pedidos:            lista con el ⚠ de incidencia abierta
 */
@Component({
  selector: 'app-panel',
  imports: [FormsModule, Tarjeta, Tabla, BadgeEstado, Indicador, Barras],
  templateUrl: './panel.html',
})
export class Panel implements OnInit {
  private readonly catalogos = inject(CatalogosService);
  private readonly mensajes = inject(MensajesService);

  protected readonly estados = Object.keys(ESTADOS_PEDIDO) as EstadoPedido[];
  protected readonly nombresEstado = ESTADOS_PEDIDO;
  protected readonly columnasPedidos: Columna[] = ['Número', 'Comercio', 'Zona', 'Estado', ''];

  // Opciones de los filtros
  protected readonly zonas = signal<Zona[]>([]);
  protected readonly comercios = signal<Comercio[]>([]);

  protected filtros: FiltrosPanel = filtrosIniciales();
  protected readonly filtrosAplicados = signal<FiltrosPanel>(filtrosIniciales());

  // Datos del panel (vacíos hasta que Despacho los conecte)
  protected readonly conteos = signal<Record<EstadoPedido, number> | null>(null);
  protected readonly porcentajeExito = signal<string | null>(null);
  protected readonly tiempoPromedio = signal<string | null>(null);
  protected readonly incidenciasPorTipo = signal<Barra[]>([]);
  protected readonly incidenciasPorZona = signal<Barra[]>([]);
  protected readonly incidenciasPorRepartidor = signal<Barra[]>([]);
  protected readonly pedidos = signal<(Pedido & { incidencia_abierta?: boolean })[]>([]);

  ngOnInit(): void {
    this.catalogos.zonas().subscribe({
      next: (zonas) => this.zonas.set(zonas),
      error: (error: unknown) => this.mensajes.error(mensajeDeError(error)),
    });

    this.catalogos.comercios().subscribe({
      next: (comercios) => this.comercios.set(comercios),
      error: (error: unknown) => this.mensajes.error(mensajeDeError(error)),
    });
  }

  protected aplicar(): void {
    this.filtrosAplicados.set({ ...this.filtros });
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
