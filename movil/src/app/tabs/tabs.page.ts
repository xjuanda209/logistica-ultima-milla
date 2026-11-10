import { Component } from '@angular/core';
import { IonIcon, IonLabel, IonTabBar, IonTabButton, IonTabs } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { cubeOutline, personOutline } from 'ionicons/icons';

/** Pestañas inferiores de la app. Cada pestaña es una ruta hija de /tabs (ver app.routes.ts). */
@Component({
  selector: 'app-tabs',
  imports: [IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel],
  template: `
    <ion-tabs>
      <ion-tab-bar slot="bottom">
        <ion-tab-button tab="pedidos">
          <ion-icon name="cube-outline" aria-hidden="true"></ion-icon>
          <ion-label>Mis pedidos</ion-label>
        </ion-tab-button>
        <ion-tab-button tab="perfil">
          <ion-icon name="person-outline" aria-hidden="true"></ion-icon>
          <ion-label>Perfil</ion-label>
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>
  `,
})
export class TabsPage {
  constructor() {
    addIcons({ cubeOutline, personOutline });
  }
}
