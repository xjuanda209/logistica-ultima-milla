import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import {
  ErroresDeCampos,
  camposDeError,
  mensajeDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { Configuracion as DatosConfiguracion, Respuesta } from '../../core/modelos';
import { Campo } from '../../shared/campo';
import { EstadoVacio } from '../../shared/estado-vacio';
import { Tarjeta } from '../../shared/tarjeta';

/**
 * Bloque "Reglas de asignación" de Parametrización (INT-03). Ajuste al modelo: no hay casillas de
 * reglas (zona y disponibilidad siempre aplican); solo se edita el máximo de pedidos activos.
 */
@Component({
  selector: 'app-configuracion',
  imports: [FormsModule, Tarjeta, Campo, EstadoVacio],
  templateUrl: './configuracion.html',
})
export class Configuracion implements OnInit {
  private readonly api = inject(ApiService);

  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  /** "✔ Cambios guardados." junto al botón, como en el mockup. */
  protected readonly guardado = signal(false);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario: { max_pedidos_activos: number | null } = { max_pedidos_activos: null };

  ngOnInit(): void {
    this.cargar();
  }

  protected cargar(): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Respuesta<DatosConfiguracion>>('/configuracion').subscribe({
      next: (respuesta) => {
        this.formulario = { ...respuesta.data };
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  protected guardar(): void {
    this.guardando.set(true);
    this.guardado.set(false);
    this.errorFormulario.set(null);
    this.errores.set({});

    this.api.put<Respuesta<DatosConfiguracion>>('/configuracion', this.formulario).subscribe({
      next: (respuesta) => {
        this.formulario = { ...respuesta.data };
        this.guardando.set(false);
        this.guardado.set(true);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeFormulario(error));
        this.errores.set(camposDeError(error));
      },
    });
  }
}
