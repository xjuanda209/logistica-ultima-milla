import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

/**
 * Marco del módulo Administración: pestañas + la página elegida.
 * Las páginas son rutas hijas de /administracion (ver app.routes.ts).
 */
@Component({
  selector: 'app-administracion',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  template: `
    <h1 class="h3 mb-3">Administración</h1>

    <ul class="nav nav-tabs mb-4">
      @for (pagina of paginas; track pagina.ruta) {
        <li class="nav-item">
          <a class="nav-link" [routerLink]="pagina.ruta" routerLinkActive="active">
            {{ pagina.titulo }}
          </a>
        </li>
      }
    </ul>

    <router-outlet />
  `,
})
export class Administracion {
  protected readonly paginas = [
    { titulo: 'Usuarios', ruta: 'usuarios' },
    { titulo: 'Zonas', ruta: 'zonas' },
    { titulo: 'Tipos de incidencia', ruta: 'tipos-incidencia' },
    { titulo: 'Configuración', ruta: 'configuracion' },
  ];
}
