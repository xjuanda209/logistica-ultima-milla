import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { ErroresDeCampos, camposDeError, mensajeDeError } from '../../core/etiquetas';
import { Paginado, Respuesta, TipoIncidencia } from '../../core/modelos';
import { ErrorCampo } from '../../shared/error-campo';
import { Modal } from '../../shared/modal';
import { Paginacion } from '../../shared/paginacion';

@Component({
  selector: 'app-tipos-incidencia',
  imports: [FormsModule, Paginacion, Modal, ErrorCampo],
  templateUrl: './tipos-incidencia.html',
})
export class TiposIncidencia implements OnInit {
  private readonly api = inject(ApiService);

  // Lista
  protected readonly tipos = signal<TipoIncidencia[]>([]);
  protected readonly meta = signal<Paginado<TipoIncidencia>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly exito = signal<string | null>(null);
  /** Id del tipo al que se le está cambiando el estado (para deshabilitar su botón). */
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
        this.tipos.set(respuesta.data);
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
        this.exito.set(
          `El tipo "${respuesta.data.nombre}" se ${tipo ? 'actualizó' : 'creó'} correctamente.`,
        );
        this.cargar(tipo ? (this.meta()?.current_page ?? 1) : 1);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeError(error));
        this.errores.set(camposDeError(error));
      },
    });
  }

  protected cambiarEstado(tipo: TipoIncidencia): void {
    this.cambiandoEstado.set(tipo.id);
    this.error.set(null);
    this.exito.set(null);

    this.api
      .put<Respuesta<TipoIncidencia>>(`/tipos-incidencia/${tipo.id}/estado`, {
        activo: !tipo.activo,
      })
      .subscribe({
        next: (respuesta) => {
          const actualizado = respuesta.data;

          this.cambiandoEstado.set(null);
          this.tipos.update((tipos) => tipos.map((t) => (t.id === actualizado.id ? actualizado : t)));
          this.exito.set(
            `El tipo "${actualizado.nombre}" quedó ${actualizado.activo ? 'activo' : 'inactivo'}.`,
          );
        },
        error: (error: unknown) => {
          this.cambiandoEstado.set(null);
          this.error.set(mensajeDeError(error));
        },
      });
  }
}
