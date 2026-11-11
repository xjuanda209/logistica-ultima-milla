import { Component, computed, input } from '@angular/core';

import { EstadoVacio } from '../../shared/estado-vacio';

/**
 * Página para las opciones del menú cuyo módulo todavía no existe (Repartidores, Asistente IA…).
 * La ruta dice quién la construye: data: { roles: [...], modulo: 'el módulo 3 (Despacho)' }.
 * Cuando el módulo esté listo, se cambia el loadComponent de su ruta en app.routes.ts.
 */
@Component({
  selector: 'app-en-construccion',
  imports: [EstadoVacio],
  template: `
    <div class="card">
      <div class="card-body">
        <app-estado-vacio icono="bi-cone-striped" titulo="En construcción" [texto]="texto()" />
      </div>
    </div>
  `,
})
export class EnConstruccion {
  /** Llega desde data.modulo de la ruta (withComponentInputBinding). */
  readonly modulo = input<string>();

  protected readonly texto = computed(
    () =>
      `Esta sección la está construyendo ${this.modulo() ?? 'otro módulo del equipo'}. Pronto estará disponible.`,
  );
}
