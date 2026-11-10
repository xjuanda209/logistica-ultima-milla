import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { ErroresDeCampos, camposDeError, mensajeDeError } from '../../core/etiquetas';
import { Paginado, Respuesta, Zona } from '../../core/modelos';
import { ErrorCampo } from '../../shared/error-campo';
import { Modal } from '../../shared/modal';
import { Paginacion } from '../../shared/paginacion';

/**
 * CRUD de zonas. Es el ejemplo más corto de "tabla + formulario en modal":
 * úsalo como plantilla para un CRUD nuevo (ver CLAUDE.md).
 */
@Component({
  selector: 'app-zonas',
  imports: [FormsModule, Paginacion, Modal, ErrorCampo],
  templateUrl: './zonas.html',
})
export class Zonas implements OnInit {
  private readonly api = inject(ApiService);

  // Lista
  protected readonly zonas = signal<Zona[]>([]);
  protected readonly meta = signal<Paginado<Zona>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly exito = signal<string | null>(null);

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

    this.api.get<Paginado<Zona>>('/zonas', { page: pagina }).subscribe({
      next: (respuesta) => {
        this.zonas.set(respuesta.data);
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
        this.exito.set(
          `La zona "${respuesta.data.nombre}" se ${zona ? 'actualizó' : 'creó'} correctamente.`,
        );
        this.cargar(zona ? (this.meta()?.current_page ?? 1) : 1);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeError(error));
        this.errores.set(camposDeError(error));
      },
    });
  }
}
