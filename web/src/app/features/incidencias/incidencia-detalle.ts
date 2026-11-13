import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import {
  ErroresDeCampos,
  PRIORIDADES,
  camposDeError,
  mensajeDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { MensajesService } from '../../core/mensajes.service';
import {
  EstadoIncidencia,
  Incidencia,
  Prioridad,
  Respuesta,
  RespuestaAsistente,
} from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { ErrorCampo } from '../../shared/error-campo';
import { EstadoVacio } from '../../shared/estado-vacio';
import { Tarjeta } from '../../shared/tarjeta';

/**
 * Detalle de la incidencia (INT-13). Administrador y operador la gestionan: prioridad, estado
 * (abierta → en revisión → resuelta, con resolución obligatoria para resolver), nota para el
 * comercio y "Resumir con el asistente". El comercio solo consulta.
 * Ajuste al modelo: hay una sola nota para el comercio, que se edita (no un historial de notas).
 */
@Component({
  selector: 'app-incidencia-detalle',
  imports: [DatePipe, FormsModule, RouterLink, Tarjeta, BadgeEstado, EstadoVacio, ErrorCampo],
  templateUrl: './incidencia-detalle.html',
  styles: `
    /* Textos escritos por las personas: se respetan sus saltos de línea. */
    .texto-libre {
      white-space: pre-line;
    }
  `,
})
export class IncidenciaDetalle implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly mensajes = inject(MensajesService);

  /** Llega desde la URL (/incidencias/:id) gracias a withComponentInputBinding(). */
  readonly id = input.required<string>();

  protected readonly puedeGestionar = this.auth.tieneRol('administrador', 'operador');
  protected readonly prioridades = PRIORIDADES;
  protected readonly listaPrioridades = Object.keys(PRIORIDADES) as Prioridad[];
  protected readonly flujo: EstadoIncidencia[] = ['abierta', 'en_revision', 'resuelta'];

  protected readonly incidencia = signal<Incidencia | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  /** Qué se está guardando, para deshabilitar solo ese botón. */
  protected readonly guardando = signal<'prioridad' | 'estado' | 'nota' | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  /** Regla de negocio que impidió el cambio de estado (por ejemplo, otra persona ya la resolvió). */
  protected readonly avisoEstado = signal<string | null>(null);

  protected prioridad: Prioridad | '' = '';
  protected resolucion = '';
  protected nota = '';

  // Resumen del asistente: solo informativo, no se guarda.
  protected readonly resumen = signal<string | null>(null);
  protected readonly resumiendo = signal(false);
  protected readonly errorResumen = signal<string | null>(null);

  ngOnInit(): void {
    this.cargar();
  }

  protected cargar(): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Respuesta<Incidencia>>(`/incidencias/${this.id()}`).subscribe({
      next: (respuesta) => {
        this.mostrar(respuesta.data);
        this.nota = respuesta.data.nota_comercio ?? '';
        this.resolucion = respuesta.data.resolucion ?? '';
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  protected cambiarPrioridad(): void {
    if (!this.prioridad) {
      return;
    }

    this.guardar('prioridad', 'prioridad', { prioridad: this.prioridad }, 'Prioridad actualizada.');
  }

  protected pasarA(estado: EstadoIncidencia): void {
    const cuerpo = estado === 'resuelta' ? { estado, resolucion: this.resolucion } : { estado };

    this.guardar(
      'estado',
      'estado',
      cuerpo,
      estado === 'resuelta' ? 'Incidencia resuelta.' : 'Incidencia en revisión.',
    );
  }

  protected guardarNota(): void {
    this.guardar('nota', 'nota', { nota_comercio: this.nota }, 'Nota para el comercio guardada.');
  }

  protected resumir(): void {
    this.resumiendo.set(true);
    this.errorResumen.set(null);

    this.api.post<Respuesta<RespuestaAsistente>>(`/incidencias/${this.id()}/resumen`).subscribe({
      next: (respuesta) => {
        this.resumen.set(respuesta.data.respuesta);
        this.resumiendo.set(false);
      },
      error: (error: unknown) => {
        this.errorResumen.set(mensajeDeError(error));
        this.resumiendo.set(false);
      },
    });
  }

  private guardar(
    accion: 'prioridad' | 'estado' | 'nota',
    ruta: string,
    cuerpo: unknown,
    exito: string,
  ): void {
    this.guardando.set(accion);
    this.errores.set({});
    this.avisoEstado.set(null);

    this.api.put<Respuesta<Incidencia>>(`/incidencias/${this.id()}/${ruta}`, cuerpo).subscribe({
      next: (respuesta) => {
        this.guardando.set(null);
        this.mostrar(respuesta.data);
        this.mensajes.exito(exito);
      },
      error: (error: unknown) => {
        this.guardando.set(null);
        this.errores.set(camposDeError(error));
        // Lo que no es de un campo: en el estado va como aviso en su tarjeta; lo demás, en un toast.
        const mensaje = mensajeDeFormulario(error);
        if (mensaje && accion === 'estado') {
          this.avisoEstado.set(mensaje);
        } else if (mensaje) {
          this.mensajes.error(mensaje);
        }
        this.prioridad = this.incidencia()?.prioridad ?? '';
      },
    });
  }

  /** Lo que el usuario está escribiendo (nota, resolución) no se pisa al guardar otra cosa. */
  private mostrar(incidencia: Incidencia): void {
    this.incidencia.set(incidencia);
    this.prioridad = incidencia.prioridad ?? '';
  }
}
