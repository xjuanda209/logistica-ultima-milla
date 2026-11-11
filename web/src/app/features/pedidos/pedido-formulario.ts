import { Component, Injectable, OnInit, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { CatalogosService } from '../../core/catalogos.service';
import {
  ErroresDeCampos,
  PRIORIDADES,
  camposDeError,
  mensajeDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { MensajesService } from '../../core/mensajes.service';
import {
  Cliente,
  Comercio,
  DatosPedido,
  Paginado,
  Pedido,
  Prioridad,
  Respuesta,
} from '../../core/modelos';
import { Campo } from '../../shared/campo';
import { ErrorCampo } from '../../shared/error-campo';
import { EstadoVacio } from '../../shared/estado-vacio';
import { Tarjeta } from '../../shared/tarjeta';

interface FormularioPedido {
  comercio_id: number | null;
  cliente_id: number | null;
  prioridad: Prioridad;
  /** Valor de <input type="datetime-local">: "2026-11-12T10:00", en la hora del dispositivo. */
  franja_inicio: string;
  franja_fin: string;
  observaciones: string;
  lineas: { producto: string; cantidad: number | null }[];
}

/**
 * Guarda lo que se llevaba escrito del pedido nuevo mientras se va a "Registrar cliente nuevo"
 * (pantalla de Clientes) y se regresa. Vive solo en memoria.
 */
@Injectable({ providedIn: 'root' })
export class BorradorPedido {
  datos: FormularioPedido | null = null;
}

/**
 * Formulario de pedido (INT-07): crear (/pedidos/nuevo) y editar (/pedidos/:id/editar).
 * Ajuste al modelo: no hay selector de dirección; al elegir el cliente se muestra su dirección
 * como vista previa y el backend la copia al pedido al guardarlo.
 * Al editar solo cambian prioridad, franja, observaciones y productos, y solo en estado «Creado».
 */
@Component({
  selector: 'app-pedido-formulario',
  imports: [FormsModule, RouterLink, Tarjeta, Campo, ErrorCampo, EstadoVacio],
  templateUrl: './pedido-formulario.html',
})
export class PedidoFormulario implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly catalogos = inject(CatalogosService);
  private readonly mensajes = inject(MensajesService);
  private readonly router = inject(Router);
  private readonly borrador = inject(BorradorPedido);

  /** /pedidos/:id/editar. Sin id, es un pedido nuevo. */
  readonly id = input<string>();
  /** Llegan por la URL al regresar de "Registrar cliente nuevo". */
  readonly comercioInicial = input<string | undefined>(undefined, { alias: 'comercio_id' });
  readonly clienteInicial = input<string | undefined>(undefined, { alias: 'cliente_id' });

  protected readonly esEdicion = computed(() => this.id() !== undefined);
  /** El usuario de un comercio trabaja solo con el suyo: no lo elige. */
  protected readonly comercioFijo = this.auth.tieneRol('comercio');
  protected readonly prioridades = PRIORIDADES;
  protected readonly listaPrioridades = Object.keys(PRIORIDADES) as Prioridad[];

  protected readonly comercios = signal<Comercio[]>([]);
  protected readonly clientes = signal<Cliente[]>([]);
  protected readonly cargandoClientes = signal(false);
  /** Pedido que se está editando. */
  protected readonly pedido = signal<Pedido | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario: FormularioPedido = formularioVacio();

  ngOnInit(): void {
    if (this.esEdicion()) {
      this.cargarPedido();
    } else {
      this.prepararNuevo();
    }
  }

  /** Cliente elegido en el <select>, para mostrar su dirección. */
  protected clienteElegido(): Cliente | null {
    return this.clientes().find((cliente) => cliente.id === this.formulario.cliente_id) ?? null;
  }

  protected nombreComercio(): string {
    return (
      this.pedido()?.comercio?.nombre ??
      this.comercios().find((comercio) => comercio.id === this.formulario.comercio_id)?.nombre ??
      ''
    );
  }

  protected cambiarComercio(id: number | null): void {
    this.formulario.comercio_id = id;
    this.formulario.cliente_id = null;
    this.cargarClientes();
  }

  protected agregarLinea(): void {
    this.formulario.lineas.push({ producto: '', cantidad: 1 });
  }

  protected quitarLinea(indice: number): void {
    this.formulario.lineas.splice(indice, 1);
    // Los errores están numerados por posición: al quitar una línea ya no corresponden.
    this.errores.set({});
  }

  /** Va a Clientes a registrar uno nuevo; al guardarlo se regresa aquí con lo ya escrito. */
  protected registrarCliente(): void {
    this.borrador.datos = this.formulario;

    void this.router.navigate(['/clientes'], {
      queryParams: { volver: 'pedido', comercio_id: this.formulario.comercio_id },
    });
  }

  protected guardar(): void {
    const pedido = this.pedido();
    const cuerpo: DatosPedido = {
      prioridad: this.formulario.prioridad,
      franja_inicio: aFechaIso(this.formulario.franja_inicio),
      franja_fin: aFechaIso(this.formulario.franja_fin),
      observaciones: this.formulario.observaciones,
      lineas: this.formulario.lineas,
    };
    const peticion = pedido
      ? this.api.put<Respuesta<Pedido>>(`/pedidos/${pedido.id}`, cuerpo)
      : this.api.post<Respuesta<Pedido>>('/pedidos', {
          ...cuerpo,
          comercio_id: this.formulario.comercio_id,
          cliente_id: this.formulario.cliente_id,
        });

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    peticion.subscribe({
      next: (respuesta) => {
        const guardado = respuesta.data;

        this.guardando.set(false);
        this.mensajes.exito(
          `Pedido ${guardado.numero} ${pedido ? 'actualizado' : 'creado'} correctamente.`,
        );
        void this.router.navigate(['/pedidos', guardado.id]);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeFormulario(error));
        this.errores.set(camposDeError(error));
      },
    });
  }

  private prepararNuevo(): void {
    const borrador = this.borrador.datos;
    this.borrador.datos = null;

    if (borrador) {
      this.formulario = borrador;
    }

    this.catalogos.comercios().subscribe({
      next: (comercios) => {
        // Solo se crean pedidos para comercios activos (el backend también lo exige).
        const activos = comercios.filter((comercio) => comercio.activo);
        const pedido = Number(this.comercioInicial());

        this.comercios.set(this.comercioFijo ? comercios : activos);
        this.formulario.comercio_id =
          comercios.find((comercio) => comercio.id === pedido)?.id ??
          this.formulario.comercio_id ??
          (this.comercioFijo || activos.length === 1 ? (comercios[0]?.id ?? null) : null);

        const cliente = Number(this.clienteInicial());
        if (cliente) {
          this.formulario.cliente_id = cliente;
        }

        this.cargando.set(false);
        this.cargarClientes();
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  private cargarClientes(): void {
    const comercioId = this.formulario.comercio_id;
    this.clientes.set([]);

    if (comercioId === null) {
      return;
    }

    this.cargandoClientes.set(true);

    this.api
      .get<Paginado<Cliente>>('/clientes', { comercio_id: comercioId, por_pagina: 100 })
      .subscribe({
        next: (respuesta) => {
          this.clientes.set(respuesta.data);
          this.cargandoClientes.set(false);
        },
        error: (error: unknown) => {
          this.errorFormulario.set(mensajeDeError(error));
          this.cargandoClientes.set(false);
        },
      });
  }

  private cargarPedido(): void {
    this.api.get<Respuesta<Pedido>>(`/pedidos/${this.id()}`).subscribe({
      next: (respuesta) => {
        const pedido = respuesta.data;

        this.pedido.set(pedido);
        this.formulario = {
          comercio_id: pedido.comercio_id,
          cliente_id: pedido.cliente_id,
          prioridad: pedido.prioridad,
          franja_inicio: aFechaLocal(pedido.franja_inicio),
          franja_fin: aFechaLocal(pedido.franja_fin),
          observaciones: pedido.observaciones ?? '',
          lineas: (pedido.lineas ?? []).map(({ producto, cantidad }) => ({ producto, cantidad })),
        };
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }
}

function formularioVacio(): FormularioPedido {
  return {
    comercio_id: null,
    cliente_id: null,
    prioridad: 'media',
    franja_inicio: '',
    franja_fin: '',
    observaciones: '',
    lineas: [{ producto: '', cantidad: 1 }],
  };
}

/** "2026-11-12T10:00" (hora del dispositivo) → ISO en UTC, como lo guarda la API. */
function aFechaIso(local: string): string | null {
  const fecha = new Date(local);

  return local && !Number.isNaN(fecha.getTime()) ? fecha.toISOString() : null;
}

/** ISO en UTC → "2026-11-12T10:00" en la hora del dispositivo, para <input type="datetime-local">. */
function aFechaLocal(iso: string): string {
  const fecha = new Date(iso);
  const dos = (numero: number) => String(numero).padStart(2, '0');

  return (
    `${fecha.getFullYear()}-${dos(fecha.getMonth() + 1)}-${dos(fecha.getDate())}` +
    `T${dos(fecha.getHours())}:${dos(fecha.getMinutes())}`
  );
}
