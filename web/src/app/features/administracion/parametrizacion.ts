import { Component } from '@angular/core';

import { Configuracion } from './configuracion';
import { TiposIncidencia } from './tipos-incidencia';
import { Zonas } from './zonas';

/**
 * Parametrización del sistema (INT-03): zonas, tipos de incidencia y reglas de asignación en una
 * sola página, por bloques. Cada bloque es su propio componente con su CRUD.
 * Ajustes al modelo: no hay bloque "Estados del pedido" (los nombres de los estados son fijos,
 * ver core/etiquetas.ts) ni casillas de reglas de asignación.
 */
@Component({
  selector: 'app-parametrizacion',
  imports: [Zonas, TiposIncidencia, Configuracion],
  template: `
    <div class="row g-4 align-items-start">
      <div class="col-xl-6 d-grid gap-4">
        <app-zonas />
        <app-tipos-incidencia />
      </div>
      <div class="col-xl-6">
        <app-configuracion />
      </div>
    </div>
  `,
})
export class Parametrizacion {}
