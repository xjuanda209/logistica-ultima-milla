import { Component, input } from '@angular/core';

/**
 * Logotipo de texto: "Última" + "Milla" en naranja (docs/interfaces/README.md).
 *   <app-logo />                 sobre fondo claro: "Última" oscuro (login)
 *   <app-logo [claro]="true" />  sobre fondo oscuro: "Última" blanco (barra lateral)
 */
@Component({
  selector: 'app-logo',
  template: `<span class="logo" [class.logo--claro]="claro()"
    >Última<span class="logo__milla">Milla</span></span
  >`,
  styles: `
    @use '../../estilos/variables' as tema;

    .logo {
      color: tema.$lateral-fondo;
      font-weight: 800;
      letter-spacing: -0.02em;
      white-space: nowrap;
    }

    .logo--claro {
      color: tema.$white;
    }

    .logo__milla {
      color: tema.$naranja;
    }
  `,
})
export class Logo {
  readonly claro = input(false);
}
