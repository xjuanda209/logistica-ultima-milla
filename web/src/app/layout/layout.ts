import { Component, ElementRef, computed, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthService } from '../core/auth.service';
import { ROLES } from '../core/etiquetas';
import { MENU } from '../core/menu';
import { Logo } from '../shared/logo';

const CLAVE_PLEGADO = 'lum_menu_plegado';
const ESCRITORIO = '(min-width: 992px)';

/**
 * Marco de todas las páginas internas:
 *  - barra lateral fija con las opciones permitidas para el rol (se pliega a solo íconos;
 *    en pantallas pequeñas se abre encima del contenido);
 *  - barra superior con el buscador de pedidos por número y el menú del usuario.
 */
@Component({
  selector: 'app-layout',
  imports: [FormsModule, RouterOutlet, RouterLink, RouterLinkActive, Logo],
  templateUrl: './layout.html',
  styleUrl: './layout.scss',
  host: {
    '(document:click)': 'menuUsuario.set(false)',
    '(document:keydown.escape)': 'cerrarMenus()',
    '(document:keydown)': 'atajoBuscar($event)',
  },
})
export class Layout {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly buscador = viewChild<ElementRef<HTMLInputElement>>('buscador');

  protected readonly usuario = this.auth.usuario;

  /** Escritorio: barra lateral reducida a íconos. Se recuerda entre visitas. */
  protected readonly plegado = signal(localStorage.getItem(CLAVE_PLEGADO) === '1');
  /** Pantallas pequeñas: barra lateral abierta encima del contenido. */
  protected readonly menuMovil = signal(false);
  protected readonly menuUsuario = signal(false);
  protected busqueda = '';

  protected readonly opciones = computed(() => {
    const rol = this.usuario()?.rol;
    return rol ? MENU.filter((opcion) => opcion.roles.includes(rol)) : [];
  });

  protected readonly nombreRol = computed(() => {
    const rol = this.usuario()?.rol;
    return rol ? ROLES[rol] : '';
  });

  /** "Ana María Gómez" → "AM" */
  protected readonly iniciales = computed(() =>
    (this.usuario()?.nombre ?? '')
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((parte) => parte[0].toUpperCase())
      .join(''),
  );

  protected alternarMenu(): void {
    if (window.matchMedia(ESCRITORIO).matches) {
      this.plegado.update((plegado) => !plegado);
      localStorage.setItem(CLAVE_PLEGADO, this.plegado() ? '1' : '0');
    } else {
      this.menuMovil.update((abierto) => !abierto);
    }
  }

  protected alternarMenuUsuario(evento: Event): void {
    evento.stopPropagation(); // que el clic no llegue al document y lo cierre de inmediato
    this.menuUsuario.update((abierto) => !abierto);
  }

  protected cerrarMenus(): void {
    this.menuUsuario.set(false);
    this.menuMovil.set(false);
  }

  /** Lleva a la lista de pedidos filtrada por número: /pedidos?numero=PED-0012 */
  protected buscarPedido(): void {
    const numero = this.busqueda.trim();
    void this.router.navigate(['/pedidos'], { queryParams: numero ? { numero } : {} });
  }

  /** La tecla "/" lleva el cursor al buscador (si no se está escribiendo en otro campo). */
  protected atajoBuscar(evento: KeyboardEvent): void {
    const destino = evento.target as HTMLElement | null;
    const escribiendo =
      destino?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(destino?.tagName ?? '');

    if (evento.key === '/' && !escribiendo) {
      evento.preventDefault();
      this.buscador()?.nativeElement.focus();
    }
  }

  protected cerrarSesion(): void {
    this.auth.logout().subscribe(() => void this.router.navigate(['/login']));
  }
}
