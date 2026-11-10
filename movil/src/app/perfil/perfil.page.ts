import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { IonButton, IonContent, IonHeader, IonItem, IonLabel, IonList, IonTitle, IonToolbar } from '@ionic/angular';

import { AuthService } from '../core/auth.service';

/** Pestaña "Perfil": página base. Por ahora solo muestra quién inició sesión y permite salir. */
@Component({
  selector: 'app-perfil',
  imports: [IonHeader, IonToolbar, IonTitle, IonContent, IonList, IonItem, IonLabel, IonButton],
  template: `
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Perfil</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      <ion-list>
        <ion-item>
          <ion-label>
            <h2>{{ usuario()?.nombre }}</h2>
            <p>{{ usuario()?.correo }}</p>
          </ion-label>
        </ion-item>
      </ion-list>

      <ion-button expand="block" fill="outline" color="danger" class="ion-margin-top" (click)="cerrarSesion()">
        Cerrar sesión
      </ion-button>
    </ion-content>
  `,
})
export class PerfilPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly usuario = this.auth.usuario;

  protected cerrarSesion(): void {
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/login', { replaceUrl: true }));
  }
}
