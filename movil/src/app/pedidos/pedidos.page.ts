import { DatePipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import type { RefresherCustomEvent, ViewWillEnter, ViewWillLeave } from '@ionic/angular';
import {
  IonContent,
  IonHeader,
  IonIcon,
  IonRefresher,
  IonRefresherContent,
  IonRouterLinkWithHref,
  IonSpinner,
  IonTitle,
  IonToolbar,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { cubeOutline, refreshOutline, timeOutline } from 'ionicons/icons';

import { ApiService } from '../core/api.service';
import { AuthService } from '../core/auth.service';
import { esSinConexion, mensajeDeError } from '../core/etiquetas';
import type { Paginado, Pedido } from '../core/modelos';
import { InsigniaComponent } from '../shared/insignia.component';
import { SinConexionComponent } from '../shared/sin-conexion.component';

/**
 * "Mis pedidos" (INT-15): los pedidos del repartidor en asignado, recogido o en ruta, por inicio
 * de franja. La API ya devuelve solo los suyos (GET /pedidos?activos=1).
 */
@Component({
  selector: 'app-pedidos',
  templateUrl: 'pedidos.page.html',
  styles: `
    .encabezado {
      margin-bottom: 12px;
    }

    .punto {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: currentColor;
    }

    .actualizado {
      margin-bottom: 12px;
    }

    .numero {
      font-size: 1.125rem;
    }

    .franja {
      justify-content: flex-start;
      margin-top: 4px;
    }

    .icono-vacio {
      font-size: 64px;
      color: #b3bfc4;
    }

    ion-icon {
      vertical-align: -2px;
    }
  `,
  imports: [
    DatePipe,
    RouterLink,
    IonRouterLinkWithHref,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonContent,
    IonRefresher,
    IonRefresherContent,
    IonSpinner,
    IonIcon,
    InsigniaComponent,
    SinConexionComponent,
  ],
})
export class PedidosPage implements ViewWillEnter, ViewWillLeave {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);

  protected readonly pedidos = signal<Pedido[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly disponible = computed(() => this.auth.usuario()?.disponibilidad === 'disponible');

  /** Hora de la última carga correcta y reloj para escribir "Actualizado hace 1 min". */
  private readonly ultimaCarga = signal<number | null>(null);
  private readonly ahora = signal(Date.now());
  private reloj: ReturnType<typeof setInterval> | null = null;

  protected readonly actualizado = computed(() => {
    const ultima = this.ultimaCarga();
    const minutos = ultima === null ? 0 : Math.floor((this.ahora() - ultima) / 60000);

    return minutos < 1 ? 'Actualizado hace un momento' : `Actualizado hace ${minutos} min`;
  });

  constructor() {
    addIcons({ cubeOutline, refreshOutline, timeOutline });
  }

  /** Se ejecuta cada vez que la pantalla vuelve a mostrarse (por ejemplo al regresar del detalle). */
  ionViewWillEnter(): void {
    this.cargar();
    // El operador puede cambiar la disponibilidad desde la web: se vuelve a leer.
    this.auth.refrescar().subscribe({ error: () => undefined });
    this.reloj = setInterval(() => this.ahora.set(Date.now()), 30000);
  }

  ionViewWillLeave(): void {
    if (this.reloj !== null) {
      clearInterval(this.reloj);
      this.reloj = null;
    }
  }

  protected refrescar(evento: RefresherCustomEvent): void {
    this.cargar(() => void evento.target.complete());
  }

  protected cargar(alTerminar: () => void = () => undefined): void {
    this.error.set(null);

    this.api.get<Paginado<Pedido>>('/pedidos', { activos: 1 }).subscribe({
      next: (respuesta) => {
        this.pedidos.set(respuesta.data);
        this.ultimaCarga.set(Date.now());
        this.ahora.set(Date.now());
        this.cargando.set(false);
        alTerminar();
      },
      error: (error: unknown) => {
        // Los pedidos que ya estaban en pantalla se conservan; solo se avisa que no se actualizaron.
        this.error.set(esSinConexion(error) ? 'Sin conexión. No pudimos actualizar tus pedidos.' : mensajeDeError(error));
        this.cargando.set(false);
        alTerminar();
      },
    });
  }
}
