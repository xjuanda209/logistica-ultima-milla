import { Injectable, signal } from '@angular/core';

export type TipoMensaje = 'exito' | 'error' | 'aviso';

export interface Mensaje {
  id: number;
  tipo: TipoMensaje;
  texto: string;
}

const DURACION: Record<TipoMensaje, number> = { exito: 4000, error: 8000, aviso: 6000 };
const MAXIMO_VISIBLES = 4;

/**
 * Mensajes del sistema (mockup INT-22), todos en español y sin textos técnicos:
 *
 *   mensajes.exito('Pedido P-000131 creado correctamente.');   toast verde
 *   mensajes.error(mensajeDeError(error));                    toast rojo con el motivo
 *   mensajes.accesoDenegado();                                 aviso "Acceso denegado"
 *
 * La franja "Sin conexión" la enciende y apaga el interceptor (core/auth.interceptor.ts) según
 * respondan o no las peticiones; las páginas no la manejan.
 * Los errores de un formulario NO van en un toast: van dentro del formulario, junto a cada campo.
 */
@Injectable({ providedIn: 'root' })
export class MensajesService {
  private siguienteId = 1;
  private readonly _mensajes = signal<Mensaje[]>([]);
  private readonly _sinConexion = signal(false);

  readonly mensajes = this._mensajes.asReadonly();
  readonly sinConexion = this._sinConexion.asReadonly();

  exito(texto: string): void {
    this.mostrar('exito', texto);
  }

  error(texto: string): void {
    this.mostrar('error', texto);
  }

  accesoDenegado(): void {
    this.mostrar('aviso', 'Acceso denegado: tu rol no permite esta función.');
  }

  cerrar(id: number): void {
    this._mensajes.update((mensajes) => mensajes.filter((mensaje) => mensaje.id !== id));
  }

  marcarSinConexion(): void {
    this._sinConexion.set(true);
  }

  marcarConectado(): void {
    this._sinConexion.set(false);
  }

  private mostrar(tipo: TipoMensaje, texto: string): void {
    // Si el mismo mensaje ya está a la vista, no se repite.
    if (this._mensajes().some((mensaje) => mensaje.texto === texto)) {
      return;
    }

    const id = this.siguienteId++;
    this._mensajes.update((mensajes) => [...mensajes, { id, tipo, texto }].slice(-MAXIMO_VISIBLES));
    setTimeout(() => this.cerrar(id), DURACION[tipo]);
  }
}
