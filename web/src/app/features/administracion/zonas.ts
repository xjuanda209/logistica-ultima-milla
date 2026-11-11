import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { CatalogosService } from '../../core/catalogos.service';
import {
  ErroresDeCampos,
  camposDeError,
  mensajeDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { MensajesService } from '../../core/mensajes.service';
import { Paginado, Respuesta, Zona } from '../../core/modelos';
import { Campo } from '../../shared/campo';
import { Modal } from '../../shared/modal';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

/**
 * Bloque "Zonas y cobertura" de Parametrización (INT-03). Es el ejemplo más corto de
 * "tarjeta + tabla + formulario en modal": úsalo como plantilla para un CRUD nuevo (ver CLAUDE.md).
 * Ajuste al modelo: cada repartidor tiene una sola zona (se elige en Usuarios); aquí solo se
 * muestran como chips, para consulta.
 */
@Component({
  selector: 'app-zonas',
  imports: [FormsModule, Tarjeta, Tabla, Campo, Modal],
  templateUrl: './zonas.html',
})
export class Zonas implements OnInit {
  private readonly api = inject(ApiService);
  private readonly catalogos = inject(CatalogosService);
  private readonly mensajes = inject(MensajesService);

  protected readonly columnas: Columna[] = [
    'Zona',
    'Repartidores que la cubren',
    { titulo: 'Acciones', clase: 'text-end' },
  ];

  // Lista
  protected readonly datos = signal<Zona[]>([]);
  protected readonly meta = signal<Paginado<Zona>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  // Formulario (modal)
  protected readonly modalAbierto = signal(false);
  protected readonly editando = signal<Zona | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario = { nombre: '', descripcion: '' };

  ngOnInit(): void {
    this.cargar(1);
  }

  protected cargar(pagina: number): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Paginado<Zona>>('/zonas', { page: pagina, con_repartidores: 1 }).subscribe({
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

  /** Abre el modal vacío (crear) o con los datos de la zona (editar). */
  protected abrirFormulario(zona: Zona | null = null): void {
    this.editando.set(zona);
    this.formulario = { nombre: zona?.nombre ?? '', descripcion: zona?.descripcion ?? '' };
    this.errorFormulario.set(null);
    this.errores.set({});
    this.modalAbierto.set(true);
  }

  protected cerrarFormulario(): void {
    this.modalAbierto.set(false);
  }

  protected guardar(): void {
    const zona = this.editando();
    const peticion = zona
      ? this.api.put<Respuesta<Zona>>(`/zonas/${zona.id}`, this.formulario)
      : this.api.post<Respuesta<Zona>>('/zonas', this.formulario);

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    peticion.subscribe({
      next: (respuesta) => {
        this.guardando.set(false);
        this.modalAbierto.set(false);
        this.catalogos.invalidar('zonas'); // los <select> de zonas deben ver el cambio
        this.mensajes.exito(
          `Zona ${respuesta.data.nombre} ${zona ? 'actualizada' : 'creada'} correctamente.`,
        );
        this.cargar(zona ? (this.meta()?.current_page ?? 1) : 1);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeFormulario(error));
        this.errores.set(camposDeError(error));
      },
    });
  }
}
