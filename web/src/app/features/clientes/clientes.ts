import { Component, OnInit, inject, signal } from '@angular/core';

import { ApiService } from '../../core/api.service';
import { mensajeDeError } from '../../core/etiquetas';
import { Cliente, Paginado } from '../../core/modelos';
import { Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

@Component({
  selector: 'app-clientes',
  imports: [Tarjeta, Tabla],
  templateUrl: './clientes.html',
})
export class Clientes implements OnInit {
  private readonly api = inject(ApiService);

  protected readonly clientes = signal<Cliente[]>([]);
  protected readonly meta = signal<Paginado<Cliente>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  ngOnInit(): void {
    this.cargar(1);
  }

  protected cargar(pagina: number): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Paginado<Cliente>>('/clientes', { page: pagina }).subscribe({
      next: (respuesta) => {
        this.clientes.set(respuesta.data);
        this.meta.set(respuesta.meta);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }
}
