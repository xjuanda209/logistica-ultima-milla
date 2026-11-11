import { Component, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { AuthService } from '../../core/auth.service';
import {
  ErroresDeCampos,
  camposDeError,
  codigoDeError,
  mensajeDeError,
} from '../../core/etiquetas';
import { Campo } from '../../shared/campo';
import { Logo } from '../../shared/logo';

/**
 * Inicio de sesión (INT-01). Si el correo no existe, la contraseña no coincide o la cuenta está
 * desactivada, la API responde siempre "Correo o contraseña incorrectos." (un solo mensaje).
 */
@Component({
  selector: 'app-login',
  imports: [FormsModule, Campo, Logo],
  templateUrl: './login.html',
  styles: `
    @use '../../../estilos/variables' as tema;

    .acceso {
      display: grid;
      min-height: 100vh;
      padding: 1rem;
      place-items: center;
      background: linear-gradient(160deg, tema.$lateral-fondo 0%, tema.$verde-petroleo 100%);
    }

    .acceso__tarjeta {
      width: 100%;
      max-width: 27rem;
      border: 0;
      border-radius: tema.$border-radius-xl;
      box-shadow: tema.$box-shadow-lg;
    }

    .acceso__logo {
      font-size: 1.875rem;
    }

    .acceso__mostrar {
      position: absolute;
      top: 50%;
      right: 0.25rem;
      transform: translateY(-50%);
    }
  `,
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  /** Llega por la URL cuando el interceptor cerró la sesión por un 401: /login?sesion=vencida */
  readonly sesion = input<string>();

  protected correo = '';
  protected password = '';
  protected readonly mostrarPassword = signal(false);
  protected readonly enviando = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});

  protected readonly sesionVencida = computed(
    () => this.sesion() === 'vencida' && this.error() === null,
  );

  protected entrar(): void {
    this.enviando.set(true);
    this.error.set(null);
    this.errores.set({});

    this.auth.login(this.correo.trim(), this.password).subscribe({
      next: () => void this.router.navigateByUrl(this.auth.rutaInicial()),
      error: (error: unknown) => {
        // Campos vacíos o mal escritos: el error va debajo de cada campo.
        if (codigoDeError(error) === 'VALIDACION') {
          this.errores.set(camposDeError(error));
        } else {
          this.error.set(mensajeDeError(error));
        }
        this.enviando.set(false);
      },
    });
  }
}
