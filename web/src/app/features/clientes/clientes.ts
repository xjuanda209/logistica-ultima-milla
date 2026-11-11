import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { CatalogosService } from '../../core/catalogos.service';
import {
  ErroresDeCampos,
  camposDeError,
  mensajeDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { MensajesService } from '../../core/mensajes.service';
import { Cliente, Comercio, Paginado, Respuesta, Zona } from '../../core/modelos';
import { Campo } from '../../shared/campo';
import { EstadoVacio } from '../../shared/estado-vacio';
import { Paginacion } from '../../shared/paginacion';
import { Tarjeta } from '../../shared/tarjeta';

const FORMULARIO_VACIO = {
  nombre: '',
  cedula: '',
  telefono: '',
  correo: '',
  direccion: '',
  barrio: '',
  indicaciones: '',
  zona_id: null as number | null,
};

/**
 * Clientes (INT-05): a la izquierda el comercio, el buscador y la lista; a la derecha el
 * formulario "Datos del cliente" (vacío = cliente nuevo; al elegir uno de la lista, se edita).
 * Ajuste al modelo: cada cliente tiene UNA dirección, dentro del mismo formulario.
 *
 * Si se llega desde el formulario de pedido (/clientes?volver=pedido&comercio_id=1), al guardar
 * el cliente nuevo se regresa al pedido con ese cliente ya elegido.
 */
@Component({
  selector: 'app-clientes',
  imports: [FormsModule, Tarjeta, Campo, EstadoVacio, Paginacion],
  templateUrl: './clientes.html',
})
export class Clientes implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly catalogos = inject(CatalogosService);
  private readonly mensajes = inject(MensajesService);
  private readonly router = inject(Router);

  /** Llegan por la URL cuando se viene del formulario de pedido. */
  readonly volver = input<string>();
  readonly comercioInicial = input<string | undefined>(undefined, { alias: 'comercio_id' });

  /** El usuario de un comercio trabaja solo con el suyo: no lo elige. */
  protected readonly comercioFijo = this.auth.tieneRol('comercio');
  protected readonly vieneDePedido = computed(() => this.volver() === 'pedido');

  protected readonly comercios = signal<Comercio[]>([]);
  protected readonly zonas = signal<Zona[]>([]);
  protected readonly comercioId = signal<number | null>(null);
  protected readonly nombreComercio = computed(
    () => this.comercios().find((comercio) => comercio.id === this.comercioId())?.nombre ?? '',
  );

  // Lista
  protected buscar = '';
  protected readonly datos = signal<Cliente[]>([]);
  protected readonly meta = signal<Paginado<Cliente>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  // Formulario
  protected readonly editando = signal<Cliente | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario = { ...FORMULARIO_VACIO };

  private esperaBusqueda: ReturnType<typeof setTimeout> | undefined;

  ngOnInit(): void {
    this.catalogos.zonas().subscribe({ next: (zonas) => this.zonas.set(zonas) });

    this.catalogos.comercios().subscribe({
      next: (comercios) => {
        const pedido = Number(this.comercioInicial());
        const inicial = comercios.find((comercio) => comercio.id === pedido) ?? comercios[0];

        this.comercios.set(comercios);
        this.comercioId.set(inicial?.id ?? null);
        this.cargar(1);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  protected cargar(pagina: number): void {
    const comercioId = this.comercioId();
    if (comercioId === null) {
      this.cargando.set(false);
      return;
    }

    this.cargando.set(true);
    this.error.set(null);

    const filtros = { page: pagina, comercio_id: comercioId };
    const buscar = this.buscar.trim();

    this.api
      .get<Paginado<Cliente>>('/clientes', buscar ? { ...filtros, buscar } : filtros)
      .subscribe({
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

  protected cambiarComercio(id: number): void {
    this.comercioId.set(id);
    this.nuevo();
    this.cargar(1);
  }

  /** Busca mientras se escribe, sin lanzar una petición por cada tecla. */
  protected alBuscar(): void {
    clearTimeout(this.esperaBusqueda);
    this.esperaBusqueda = setTimeout(() => this.cargar(1), 300);
  }

  /** Deja el formulario vacío para registrar un cliente nuevo. */
  protected nuevo(): void {
    this.editando.set(null);
    this.formulario = { ...FORMULARIO_VACIO };
    this.errorFormulario.set(null);
    this.errores.set({});
  }

  /** Pasa al formulario los datos del cliente elegido en la lista. */
  protected elegir(cliente: Cliente): void {
    this.editando.set(cliente);
    this.formulario = {
      nombre: cliente.nombre,
      cedula: cliente.cedula,
      telefono: cliente.telefono,
      correo: cliente.correo ?? '',
      direccion: cliente.direccion,
      barrio: cliente.barrio,
      indicaciones: cliente.indicaciones ?? '',
      zona_id: cliente.zona_id,
    };
    this.errorFormulario.set(null);
    this.errores.set({});
  }

  protected guardar(): void {
    const cliente = this.editando();
    const cuerpo = { ...this.formulario, comercio_id: this.comercioId() };
    const peticion = cliente
      ? this.api.put<Respuesta<Cliente>>(`/clientes/${cliente.id}`, cuerpo)
      : this.api.post<Respuesta<Cliente>>('/clientes', cuerpo);

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    peticion.subscribe({
      next: (respuesta) => {
        const guardado = respuesta.data;

        this.guardando.set(false);
        this.mensajes.exito(
          `Cliente ${guardado.nombre} ${cliente ? 'actualizado' : 'registrado'} correctamente.`,
        );

        if (this.vieneDePedido()) {
          this.volverAlPedido(guardado.id);
          return;
        }

        this.elegir(guardado);
        this.cargar(cliente ? (this.meta()?.current_page ?? 1) : 1);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeFormulario(error));
        this.errores.set(camposDeError(error));
      },
    });
  }

  /** Regresa al formulario de pedido; si se guardó un cliente, llega ya elegido. */
  protected volverAlPedido(clienteId?: number): void {
    const comercioId = this.comercioId();

    void this.router.navigate(['/pedidos/nuevo'], {
      queryParams: {
        ...(comercioId ? { comercio_id: comercioId } : {}),
        ...(clienteId ? { cliente_id: clienteId } : {}),
      },
    });
  }
}
