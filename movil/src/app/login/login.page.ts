import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  IonButton,
  IonContent,
  IonHeader,
  IonInput,
  IonItem,
  IonList,
  IonText,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';

import { AuthService } from '../core/auth.service';
import { mensajeDeError } from '../core/etiquetas';

@Component({
  selector: 'app-login',
  templateUrl: 'login.page.html',
  imports: [FormsModule, IonHeader, IonToolbar, IonTitle, IonContent, IonList, IonItem, IonInput, IonButton, IonText],
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected correo = '';
  protected password = '';
  protected readonly enviando = signal(false);
  protected readonly error = signal<string | null>(null);

  protected entrar(): void {
    this.enviando.set(true);
    this.error.set(null);

    this.auth.login(this.correo.trim(), this.password).subscribe({
      next: ({ usuario }) => {
        this.enviando.set(false);

        // La app móvil es solo para repartidores; los demás roles usan la web.
        if (usuario.rol !== 'repartidor') {
          this.auth.logout().subscribe();
          this.error.set('Esta aplicación es solo para repartidores. Usa la versión web.');
          return;
        }

        this.password = '';
        void this.router.navigateByUrl('/tabs/pedidos', { replaceUrl: true });
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.enviando.set(false);
      },
    });
  }
}
