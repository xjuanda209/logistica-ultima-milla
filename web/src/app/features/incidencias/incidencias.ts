import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { CatalogosService } from '../../core/catalogos.service';
import {
  ESTADOS_INCIDENCIA,
  ErroresDeCampos,
  PRIORIDADES,
  camposDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import {
  EstadoIncidencia,
  Incidencia,
  Paginado,
  Prioridad,
  TipoIncidencia,
} from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Campo } from '../../shared/campo';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

/**
 * Lista de incidencias (INT-12), de la más reciente a la más antigua, con filtros por estado,
 * tipo, prioridad y número de pedido. Administrador y operador ven todas; el comercio, solo las
 * de sus pedidos (lo decide el backend). "Abrir" lleva al detalle (INT-13).
 * Las incidencias se registran desde el detalle del pedido (INT-19), no desde aquí.
 */
@Component({
  selector: 'app-incidencias',
  imports: [DatePipe, FormsModule, RouterLink, Tarjeta, Tabla, Campo, BadgeEstado],
  templateUrl: './incidencias.html',
})
export class Incidencias implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly catalogos = inject(CatalogosService);

  protected readonly comercioPropio = this.auth.tieneRol('comercio')
    ? (this.auth.usuario()?.comercio?.nombre ?? null)
    : null;

  protected readonly estados = ESTADOS_INCIDENCIA;
  protected readonly listaEstados = Object.keys(ESTADOS_INCIDENCIA) as EstadoIncidencia[];
  protected readonly prioridades = PRIORIDADES;
  protected readonly listaPrioridades = Object.keys(PRIORIDADES) as Prioridad[];
  protected readonly tipos = signal<TipoIncidencia[]>([]);
  protected readonly columnas: Columna[] = [
    'Incidencia',
    'Pedido',
    'Tipo',
    'Prioridad',
    'Estado',
    'Registrada por · fecha',
    { titulo: 'Acciones', clase: 'text-end' },
  ];

  /** Lo que hay escrito en la barra de filtros (se aplica con "Filtrar"). */
  protected filtros = { estado: '', tipo_incidencia_id: '', prioridad: '', numero: '' };
  protected readonly hayFiltros = signal(false);

  protected readonly incidencias = signal<Incidencia[]>([]);
  protected readonly meta = signal<Paginado<Incidencia>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});

  ngOnInit(): void {
    this.catalogos.tiposIncidencia().subscribe({ next: (tipos) => this.tipos.set(tipos) });
    this.cargar(1);
  }

  protected cargar(pagina: number): void {
    const parametros: Record<string, string | number> = { page: pagina };
    const { estado, tipo_incidencia_id, prioridad, numero } = this.filtros;

    if (estado) parametros['estado'] = estado;
    if (tipo_incidencia_id) parametros['tipo_incidencia_id'] = tipo_incidencia_id;
    if (prioridad) parametros['prioridad'] = prioridad;
    if (numero.trim()) parametros['numero'] = numero.trim();

    this.hayFiltros.set(Object.keys(parametros).length > 1);
    this.cargando.set(true);
    this.error.set(null);
    this.errores.set({});

    this.api.get<Paginado<Incidencia>>('/incidencias', parametros).subscribe({
      next: (respuesta) => {
        this.incidencias.set(respuesta.data);
        this.meta.set(respuesta.meta);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        // Un filtro mal escrito se avisa junto a su campo; lo demás, en la tabla con "Reintentar".
        this.errores.set(camposDeError(error));
        this.error.set(mensajeDeFormulario(error) ?? 'Revisa los filtros marcados en rojo.');
        this.incidencias.set([]);
        this.meta.set(null);
        this.cargando.set(false);
      },
    });
  }

  protected limpiar(): void {
    this.filtros = { estado: '', tipo_incidencia_id: '', prioridad: '', numero: '' };
    this.cargar(1);
  }
}
