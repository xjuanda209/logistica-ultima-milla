import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthService } from '../core/auth.service';
import { ROLES } from '../core/etiquetas';
import { MENU } from '../core/menu';
import { TituloPagina } from '../core/titulo-pagina';
import { Logo } from '../shared/logo';

/**
 * Marco de todas las páginas internas (mockups INT-02, INT-03, INT-04):
 *  - barra lateral oscura con el logo y las opciones permitidas para el rol
 *    (en pantallas pequeñas se abre encima del contenido con el botón ☰);
 *  - barra superior con el título de la página a la izquierda y, a la derecha,
 *    el correo, el rol y el botón "Cerrar sesión".
 */
@Component({
  selector: 'app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, Logo],
  templateUrl: './layout.html',
  styleUrl: './layout.scss',
  host: { '(document:keydown.escape)': 'menuMovil.set(false)' },
})
export class Layout {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly usuario = this.auth.usuario;
  protected readonly titulo = inject(TituloPagina).titulo;

  /** Pantallas pequeñas: barra lateral abierta encima del contenido. */
  protected readonly menuMovil = signal(false);

  protected readonly opciones = computed(() => {
    const rol = this.usuario()?.rol;
    return rol ? MENU.filter((opcion) => opcion.roles.includes(rol)) : [];
  });

  protected readonly nombreRol = computed(() => {
    const rol = this.usuario()?.rol;
    return rol ? ROLES[rol] : '';
  });

  protected cerrarSesion(): void {
    this.auth.logout().subscribe(() => void this.router.navigate(['/login']));
  }
}
