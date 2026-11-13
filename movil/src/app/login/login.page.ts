import { Component, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonButton, IonContent, IonHeader, IonInput, IonToolbar } from '@ionic/angular';

import { AuthService } from '../core/auth.service';
import { mensajeDeError } from '../core/etiquetas';

/** Inicio de sesión (INT-01, versión móvil). */
@Component({
  selector: 'app-login',
  templateUrl: 'login.page.html',
  styles: `
    .barra-estado {
      --min-height: 0;
    }

    .marca {
      margin: 24px 0 20px;
      text-align: center;
    }

    .logo {
      margin: 0 0 4px;
      font-size: 2rem;
      font-weight: 800;
      color: #14313b;

      span {
        color: var(--lum-naranja);
      }
    }
  `,
  imports: [FormsModule, IonHeader, IonToolbar, IonContent, IonInput, IonButton],
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Llega en la URL (?sesion=vencida) cuando la API dejó de aceptar el token. */
  readonly sesion = input<string>();

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
