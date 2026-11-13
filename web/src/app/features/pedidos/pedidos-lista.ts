import { DatePipe } from '@angular/common';
import {
  Component,
  OnInit,
  computed,
  effect,
  inject,
  input,
  signal,
  untracked,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { CatalogosService } from '../../core/catalogos.service';
import {
  ESTADOS_PEDIDO,
  ErroresDeCampos,
  camposDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { finDelDia, inicioDelDia } from '../../core/fechas';
import { EstadoPedido, Paginado, Pedido, Zona } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Campo } from '../../shared/campo';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

/**
 * Listado de pedidos (INT-06), del más reciente al más antiguo. Los filtros viajan en la URL
 * (/pedidos?estado=en_ruta&numero=P-0001&desde=2026-11-01&hasta=2026-11-09&zona_id=2), así el
 * panel puede enlazar a una lista ya filtrada y "atrás" conserva la búsqueda.
 * No hay opción de eliminar pedidos.
 */
@Component({
  selector: 'app-pedidos-lista',
  imports: [DatePipe, FormsModule, RouterLink, Tarjeta, Tabla, Campo, BadgeEstado],
  templateUrl: './pedidos-lista.html',
})
export class PedidosLista implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly catalogos = inject(CatalogosService);
  private readonly router = inject(Router);

  // Filtros que llegan por la URL.
  readonly estado = input<EstadoPedido | undefined>();
  readonly numero = input<string>();
  /** Fechas del dispositivo, como las da <input type="date">: 2026-11-01. */
  readonly desde = input<string>();
  readonly hasta = input<string>();
  readonly zona = input<string | undefined>(undefined, { alias: 'zona_id' });

  /** El repartidor solo consulta: no crea pedidos. */
  protected readonly puedeCrear = this.auth.tieneRol('administrador', 'operador', 'comercio');
  /** Administrador y operador ven los pedidos de todos los comercios: necesitan la columna. */
  protected readonly veComercio = this.auth.tieneRol('administrador', 'operador');
  protected readonly comercioPropio = this.auth.tieneRol('comercio')
    ? (this.auth.usuario()?.comercio?.nombre ?? null)
    : null;

  protected readonly estados = ESTADOS_PEDIDO;
  protected readonly listaEstados = Object.keys(ESTADOS_PEDIDO) as EstadoPedido[];
  protected readonly zonas = signal<Zona[]>([]);
  protected readonly columnas: Columna[] = [
    'Número',
    'Cliente',
    ...(this.veComercio ? ['Comercio'] : []),
    'Fecha de creación ▼',
    'Estado',
    { titulo: 'Incidencia', clase: 'text-center' },
    { titulo: 'Acciones', clase: 'text-end' },
  ];

  /** Lo que hay escrito en la barra de filtros (se aplica con "Filtrar"). */
  protected filtros = { numero: '', desde: '', hasta: '', estado: '', zona_id: '' };

  protected readonly pedidos = signal<Pedido[]>([]);
  protected readonly meta = signal<Paginado<Pedido>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});

  protected readonly hayFiltros = computed(
    () => !!(this.estado() || this.numero() || this.desde() || this.hasta() || this.zona()),
  );
  /** Se buscó un número y no apareció: no existe o no es de los que el usuario puede consultar. */
  protected readonly noEncontrado = computed(
    () => !!this.numero() && !this.cargando() && !this.error() && this.pedidos().length === 0,
  );

  constructor() {
    // Cada vez que cambian los filtros de la URL se vuelve a pedir la primera página.
    effect(() => {
      this.filtros = {
        numero: this.numero() ?? '',
        desde: this.desde() ?? '',
        hasta: this.hasta() ?? '',
        estado: this.estado() ?? '',
        zona_id: this.zona() ?? '',
      };
      untracked(() => this.cargar(1));
    });
  }

  ngOnInit(): void {
    this.catalogos.zonas().subscribe({ next: (zonas) => this.zonas.set(zonas) });
  }

  protected cargar(pagina: number): void {
    const parametros: Record<string, string | number> = { page: pagina };
    const desde = inicioDelDia(this.desde());
    const hasta = finDelDia(this.hasta());

    if (this.estado()) parametros['estado'] = this.estado() as string;
    if (this.numero()) parametros['numero'] = this.numero() as string;
    if (this.zona()) parametros['zona_id'] = this.zona() as string;
    if (desde) parametros['desde'] = desde;
    if (hasta) parametros['hasta'] = hasta;

    this.cargando.set(true);
    this.error.set(null);
    this.errores.set({});

    this.api.get<Paginado<Pedido>>('/pedidos', parametros).subscribe({
      next: (respuesta) => {
        this.pedidos.set(respuesta.data);
        this.meta.set(respuesta.meta);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        // Un filtro mal escrito se avisa junto a su campo; lo demás, en la tabla con "Reintentar".
        this.errores.set(camposDeError(error));
        this.error.set(mensajeDeFormulario(error) ?? 'Revisa los filtros marcados en rojo.');
        this.pedidos.set([]);
        this.meta.set(null);
        this.cargando.set(false);
      },
    });
  }

  /** Lleva los filtros a la URL; el effect del constructor hace la búsqueda. */
  protected filtrar(): void {
    const { numero, desde, hasta, estado, zona_id } = this.filtros;

    void this.router.navigate(['/pedidos'], {
      queryParams: {
        ...(numero.trim() ? { numero: numero.trim() } : {}),
        ...(desde ? { desde } : {}),
        ...(hasta ? { hasta } : {}),
        ...(estado ? { estado } : {}),
        ...(zona_id ? { zona_id } : {}),
      },
    });
  }

  protected limpiar(): void {
    void this.router.navigate(['/pedidos']);
  }
}
