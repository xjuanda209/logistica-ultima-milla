import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthService } from '../core/auth.service';
import { ROLES } from '../core/etiquetas';
import { MENU } from '../core/menu';

/**
 * Marco de todas las páginas internas: barra superior + menú lateral.
 * El menú muestra solo las opciones permitidas para el rol del usuario.
 */
@Component({
  selector: 'app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './layout.html',
})
export class Layout {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly usuario = this.auth.usuario;
  protected readonly menuAbierto = signal(false);

  protected readonly opciones = computed(() => {
    const rol = this.usuario()?.rol;
    return rol ? MENU.filter((opcion) => opcion.roles.includes(rol)) : [];
  });

  protected readonly nombreRol = computed(() => {
    const rol = this.usuario()?.rol;
    return rol ? ROLES[rol] : '';
  });

  protected alternarMenu(): void {
    this.menuAbierto.update((abierto) => !abierto);
  }

  protected cerrarSesion(): void {
    this.auth.logout().subscribe(() => void this.router.navigate(['/login']));
  }
}
