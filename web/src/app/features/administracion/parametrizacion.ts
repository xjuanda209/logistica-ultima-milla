import { Component } from '@angular/core';

import { Configuracion } from './configuracion';
import { TiposIncidencia } from './tipos-incidencia';
import { Zonas } from './zonas';

/**
 * Parametrización del sistema (INT-03): zonas, tipos de incidencia y reglas de asignación
 * en una sola página. Cada bloque es su propio componente con su CRUD.
 */
@Component({
  selector: 'app-parametrizacion',
  imports: [Zonas, TiposIncidencia, Configuracion],
  template: `
    <div class="d-grid gap-4">
      <app-zonas />
      <app-tipos-incidencia />
      <app-configuracion />
    </div>
  `,
})
export class Parametrizacion {}
