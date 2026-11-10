import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { ErroresDeCampos, camposDeError, mensajeDeError } from '../../core/etiquetas';
import { Configuracion as DatosConfiguracion, Respuesta } from '../../core/modelos';
import { ErrorCampo } from '../../shared/error-campo';

@Component({
  selector: 'app-configuracion',
  imports: [FormsModule, ErrorCampo],
  templateUrl: './configuracion.html',
})
export class Configuracion implements OnInit {
  private readonly api = inject(ApiService);

  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly exito = signal<string | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario: { max_pedidos_activos: number | null } = { max_pedidos_activos: null };

  ngOnInit(): void {
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
    this.exito.set(null);
    this.errorFormulario.set(null);
    this.errores.set({});

    this.api.put<Respuesta<DatosConfiguracion>>('/configuracion', this.formulario).subscribe({
      next: (respuesta) => {
        this.formulario = { ...respuesta.data };
        this.guardando.set(false);
        this.exito.set('La configuración se guardó correctamente.');
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeError(error));
        this.errores.set(camposDeError(error));
      },
    });
  }
}
