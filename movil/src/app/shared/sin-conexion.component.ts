import { Component, input, output } from '@angular/core';
import { IonButton } from '@ionic/angular';

/**
 * Aviso de que algo no cargó, con el botón "Reintentar" (INT-22). Úsalo cuando falla la carga de
 * una pantalla; el resultado de una acción va en un toast (MensajesService).
 *
 *   @if (error(); as mensaje) {
 *     <app-sin-conexion [mensaje]="mensaje" (reintentar)="cargar()" />
 *   }
 */
@Component({
  selector: 'app-sin-conexion',
  imports: [IonButton],
  template: `
    <div class="aviso aviso-error" role="alert">
      <p>{{ mensaje() }}</p>
      <div class="ion-text-end">
        <ion-button size="small" fill="solid" (click)="reintentar.emit()">Reintentar</ion-button>
      </div>
    </div>
  `,
})
export class SinConexionComponent {
  readonly mensaje = input.required<string>();
  readonly reintentar = output<void>();
}
