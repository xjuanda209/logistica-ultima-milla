import { Injectable, inject } from '@angular/core';
import { ToastController } from '@ionic/angular';

/**
 * Mensajes del sistema (mockup INT-22), en español y sin textos técnicos:
 *
 *   mensajes.exito('Estado actualizado.');        toast verde
 *   mensajes.error(mensajeDeError(error));        toast rojo con el motivo
 *
 * Los errores de un formulario NO van en un toast: van dentro del formulario. Cuando una lista no
 * carga por falta de conexión se usa <app-sin-conexion> (con Reintentar), no un toast.
 */
@Injectable({ providedIn: 'root' })
export class MensajesService {
  private readonly toasts = inject(ToastController);

  exito(texto: string): void {
    void this.mostrar(`✔ ${texto}`, 'toast-exito', 3000);
  }

  error(texto: string): void {
    void this.mostrar(texto, 'toast-error', 6000);
  }

  private async mostrar(message: string, cssClass: string, duration: number): Promise<void> {
    const toast = await this.toasts.create({
      message,
      cssClass,
      duration,
      position: 'bottom',
      positionAnchor: document.querySelector('ion-tab-bar') ?? undefined,
      buttons: [{ text: 'Cerrar', role: 'cancel' }],
    });

    await toast.present();
  }
}
