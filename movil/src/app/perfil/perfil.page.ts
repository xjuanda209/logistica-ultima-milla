import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import type { ToggleCustomEvent, ViewWillEnter } from '@ionic/angular';
import { IonButton, IonContent, IonHeader, IonTitle, IonToggle, IonToolbar } from '@ionic/angular';

import { ApiService } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { ROLES, mensajeDeError } from '../core/etiquetas';
import { MensajesService } from '../core/mensajes.service';
import type { Disponibilidad, Repartidor, Respuesta } from '../core/modelos';

/** Pestaña "Perfil" (INT-21): correo, rol, zona, interruptor de disponibilidad y cerrar sesión. */
@Component({
  selector: 'app-perfil',
  imports: [IonHeader, IonToolbar, IonTitle, IonContent, IonToggle, IonButton],
  template: `
    <ion-header>
      <ion-toolbar color="primary">
        <ion-title>Mi perfil</ion-title>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      @if (usuario(); as u) {
        <div class="tarjeta persona">
          <span class="avatar" aria-hidden="true">{{ iniciales() }}</span>
          <div>
            <p class="correo">{{ u.correo }}</p>
            <p class="texto-suave">Rol: {{ roles[u.rol] }} · Zona: {{ u.zona?.nombre ?? 'sin zona' }}</p>
          </div>
        </div>

        <div class="tarjeta fila">
          <div>
            <h2>Disponibilidad</h2>
            @if (disponible()) {
              <p class="estado disponible"><span class="punto" aria-hidden="true"></span>Disponible</p>
            } @else {
              <p class="estado texto-suave"><span class="punto" aria-hidden="true"></span>No disponible</p>
            }
          </div>
          <ion-toggle
            color="success"
            aria-label="Disponible para recibir pedidos"
            [checked]="disponible()"
            [disabled]="guardando()"
            (ionChange)="cambiarDisponibilidad($event)"
          ></ion-toggle>
        </div>

        <div class="aviso aviso-info">
          Si te marcas «No disponible» conservas los pedidos que ya tienes, pero no recibirás asignaciones nuevas.
        </div>
      }

      <ion-button expand="block" fill="outline" class="boton-grande" (click)="cerrarSesion()">Cerrar sesión</ion-button>
    </ion-content>
  `,
  styles: `
    .persona {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .avatar {
      display: flex;
      flex-shrink: 0;
      align-items: center;
      justify-content: center;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--ion-color-primary);
      color: #fff;
      font-weight: 700;
      font-size: 1.25rem;
    }

    .correo {
      font-weight: 700;
      overflow-wrap: anywhere;
    }

    .estado {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
    }

    .disponible {
      color: var(--ion-color-success);
    }

    .punto {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: currentColor;
    }

    ion-toggle {
      min-height: 48px;
    }
  `,
})
export class PerfilPage implements ViewWillEnter {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly mensajes = inject(MensajesService);
  private readonly router = inject(Router);

  protected readonly roles = ROLES;
  protected readonly usuario = this.auth.usuario;
  protected readonly guardando = signal(false);
  protected readonly disponible = computed(() => this.usuario()?.disponibilidad === 'disponible');

  protected readonly iniciales = computed(() =>
    (this.usuario()?.nombre ?? '')
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((palabra) => palabra[0].toUpperCase())
      .join(''),
  );

  /** El operador puede cambiar la disponibilidad o la zona desde la web: se vuelven a leer. */
  ionViewWillEnter(): void {
    this.auth.refrescar().subscribe({ error: () => undefined });
  }

  protected cambiarDisponibilidad(evento: ToggleCustomEvent): void {
    const usuario = this.usuario();
    const disponibilidad: Disponibilidad = evento.detail.checked ? 'disponible' : 'no_disponible';

    if (!usuario || disponibilidad === usuario.disponibilidad) {
      return;
    }

    this.guardando.set(true);

    this.api.put<Respuesta<Repartidor>>(`/repartidores/${usuario.id}/disponibilidad`, { disponibilidad }).subscribe({
      next: (respuesta) => {
        this.auth.actualizarUsuario({ disponibilidad: respuesta.data.disponibilidad });
        this.guardando.set(false);
        this.mensajes.exito('Disponibilidad actualizada.');
      },
      error: (error: unknown) => {
        // El interruptor vuelve a su posición: el valor guardado no cambió.
        evento.target.checked = this.disponible();
        this.guardando.set(false);
        this.mensajes.error(mensajeDeError(error));
      },
    });
  }

  protected cerrarSesion(): void {
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/login', { replaceUrl: true }));
  }
}
