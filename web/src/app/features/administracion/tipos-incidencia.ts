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
import { Paginado, Respuesta, TipoIncidencia } from '../../core/modelos';
import { Campo } from '../../shared/campo';
import { Modal } from '../../shared/modal';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

/**
 * Bloque "Tipos de incidencia" de Parametrización (INT-03): crear, editar y activar/desactivar
 * con un interruptor. Un tipo inactivo deja de ofrecerse al reportar incidencias.
 */
@Component({
  selector: 'app-tipos-incidencia',
  imports: [FormsModule, Tarjeta, Tabla, Campo, Modal],
  templateUrl: './tipos-incidencia.html',
})
export class TiposIncidencia implements OnInit {
  private readonly api = inject(ApiService);
  private readonly catalogos = inject(CatalogosService);
  private readonly mensajes = inject(MensajesService);

  protected readonly columnas: Columna[] = [
    'Tipo',
    'Activo',
    { titulo: 'Acciones', clase: 'text-end' },
  ];

  // Lista
  protected readonly datos = signal<TipoIncidencia[]>([]);
  protected readonly meta = signal<Paginado<TipoIncidencia>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  /** Id del tipo al que se le está cambiando el estado (para deshabilitar su interruptor). */
  protected readonly cambiandoEstado = signal<number | null>(null);

  // Formulario (modal)
  protected readonly modalAbierto = signal(false);
  protected readonly editando = signal<TipoIncidencia | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario = { nombre: '' };

  ngOnInit(): void {
    this.cargar(1);
  }

  protected cargar(pagina: number): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Paginado<TipoIncidencia>>('/tipos-incidencia', { page: pagina }).subscribe({
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

  protected abrirFormulario(tipo: TipoIncidencia | null = null): void {
    this.editando.set(tipo);
    this.formulario = { nombre: tipo?.nombre ?? '' };
    this.errorFormulario.set(null);
    this.errores.set({});
    this.modalAbierto.set(true);
  }

  protected cerrarFormulario(): void {
    this.modalAbierto.set(false);
  }

  protected guardar(): void {
    const tipo = this.editando();
    const peticion = tipo
      ? this.api.put<Respuesta<TipoIncidencia>>(`/tipos-incidencia/${tipo.id}`, this.formulario)
      : this.api.post<Respuesta<TipoIncidencia>>('/tipos-incidencia', this.formulario);

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    peticion.subscribe({
      next: (respuesta) => {
        this.guardando.set(false);
        this.modalAbierto.set(false);
        this.catalogos.invalidar('tipos-incidencia');
        this.mensajes.exito(
          `Tipo ${respuesta.data.nombre} ${tipo ? 'actualizado' : 'creado'} correctamente.`,
        );
        this.cargar(tipo ? (this.meta()?.current_page ?? 1) : 1);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeFormulario(error));
        this.errores.set(camposDeError(error));
      },
    });
  }

  protected cambiarEstado(tipo: TipoIncidencia, interruptor: HTMLInputElement): void {
    this.cambiandoEstado.set(tipo.id);

    this.api
      .put<Respuesta<TipoIncidencia>>(`/tipos-incidencia/${tipo.id}/estado`, {
        activo: !tipo.activo,
      })
      .subscribe({
        next: (respuesta) => {
          const actualizado = respuesta.data;

          this.cambiandoEstado.set(null);
          this.catalogos.invalidar('tipos-incidencia');
          this.datos.update((tipos) =>
            tipos.map((t) => (t.id === actualizado.id ? actualizado : t)),
          );
          this.mensajes.exito(
            `Tipo ${actualizado.nombre} ${actualizado.activo ? 'activado' : 'desactivado'}.`,
          );
        },
        error: (error: unknown) => {
          this.cambiandoEstado.set(null);
          interruptor.checked = tipo.activo; // vuelve a su posición: el tipo no cambió
          this.mensajes.error(mensajeDeError(error));
        },
      });
  }
}
