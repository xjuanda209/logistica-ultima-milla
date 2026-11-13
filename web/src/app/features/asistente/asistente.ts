import { Component, ElementRef, effect, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { mensajeDeError } from '../../core/etiquetas';
import { MensajeAsistente, Respuesta, RespuestaAsistente } from '../../core/modelos';

/** Mensajes anteriores que se envían con cada pregunta (la API acepta hasta 20). */
const MAXIMO_HISTORIAL = 10;

/**
 * Asistente de IA (INT-14): chat de consulta sobre pedidos e incidencias, para administrador y
 * operador. Solo informa: no crea, modifica ni elimina datos. La conversación vive en esta
 * pantalla (no se guarda); "Nueva conversación" la borra. Si el asistente falla o tarda más de
 * 30 segundos se muestra "El asistente no está disponible" con "Reintentar".
 */
@Component({
  selector: 'app-asistente',
  imports: [FormsModule],
  templateUrl: './asistente.html',
  styles: `
    .conversacion {
      height: min(55vh, 32rem);
      overflow-y: auto;
    }

    .burbuja {
      max-width: 85%;
      padding: 0.6rem 1rem;
      border-radius: 1rem;
      white-space: pre-line;
      overflow-wrap: anywhere;
    }

    .burbuja-usuario {
      align-self: flex-end;
      border-bottom-right-radius: 0.25rem;
    }

    .burbuja-asistente {
      align-self: flex-start;
      border-bottom-left-radius: 0.25rem;
    }
  `,
})
export class Asistente {
  private readonly api = inject(ApiService);
  private readonly conversacion = viewChild<ElementRef<HTMLElement>>('conversacion');

  protected readonly ejemplos = [
    '¿Cuántos pedidos están en ruta?',
    'Incidencias abiertas de hoy',
    '¿Cuántos pedidos quedaron no entregados hoy?',
  ];

  protected readonly mensajes = signal<MensajeAsistente[]>([]);
  protected readonly enviando = signal(false);
  /** "El asistente no está disponible": la última pregunta quedó sin respuesta. */
  protected readonly error = signal<string | null>(null);
  protected pregunta = '';

  constructor() {
    // Cada mensaje nuevo (o el aviso de error) deja a la vista el final de la conversación.
    effect(() => {
      this.mensajes();
      this.enviando();
      this.error();
      const caja = this.conversacion()?.nativeElement;
      setTimeout(() => caja?.scrollTo({ top: caja.scrollHeight }));
    });
  }

  protected enviar(): void {
    const texto = this.pregunta.trim();

    if (!texto || this.enviando()) {
      return;
    }

    this.pregunta = '';
    this.mensajes.update((mensajes) => [...mensajes, { rol: 'usuario', texto }]);
    this.consultar();
  }

  protected usarEjemplo(ejemplo: string): void {
    this.pregunta = ejemplo;
    this.enviar();
  }

  /** Vuelve a enviar la última pregunta, que quedó sin respuesta. */
  protected reintentar(): void {
    if (!this.enviando()) {
      this.consultar();
    }
  }

  protected nuevaConversacion(): void {
    this.mensajes.set([]);
    this.error.set(null);
    this.pregunta = '';
  }

  /** Envía la última pregunta de la lista junto con las preguntas anteriores ya respondidas. */
  private consultar(): void {
    const mensajes = this.mensajes();
    const pregunta = mensajes[mensajes.length - 1];
    const anteriores = mensajes.slice(0, -1);
    // Una pregunta que se quedó sin respuesta (falló) no se envía como contexto.
    const historial = anteriores
      .filter((mensaje, i) => mensaje.rol === 'asistente' || anteriores[i + 1]?.rol === 'asistente')
      .slice(-MAXIMO_HISTORIAL);

    this.enviando.set(true);
    this.error.set(null);

    this.api
      .post<Respuesta<RespuestaAsistente>>('/asistente/consultas', {
        pregunta: pregunta.texto,
        historial,
      })
      .subscribe({
        next: (respuesta) => {
          this.mensajes.update((lista) => [
            ...lista,
            { rol: 'asistente', texto: respuesta.data.respuesta },
          ]);
          this.enviando.set(false);
        },
        error: (error: unknown) => {
          this.error.set(mensajeDeError(error));
          this.enviando.set(false);
        },
      });
  }
}
