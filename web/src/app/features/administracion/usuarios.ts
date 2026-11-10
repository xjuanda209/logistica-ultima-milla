import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { ErroresDeCampos, ROLES, camposDeError, mensajeDeError } from '../../core/etiquetas';
import { Comercio, Paginado, Respuesta, Rol, Usuario, Zona } from '../../core/modelos';
import { ErrorCampo } from '../../shared/error-campo';
import { Modal } from '../../shared/modal';
import { Paginacion } from '../../shared/paginacion';

interface FormularioUsuario {
  nombre: string;
  correo: string;
  password: string;
  rol: Rol;
  comercio_id: number | null;
  zona_id: number | null;
}

@Component({
  selector: 'app-usuarios',
  imports: [FormsModule, Paginacion, Modal, ErrorCampo],
  templateUrl: './usuarios.html',
})
export class Usuarios implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);

  protected readonly nombresRol = ROLES;
  protected readonly roles = Object.keys(ROLES) as Rol[];
  protected readonly miId = this.auth.usuario()?.id ?? null;

  // Lista
  protected readonly usuarios = signal<Usuario[]>([]);
  protected readonly meta = signal<Paginado<Usuario>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly exito = signal<string | null>(null);
  /** Id del usuario al que se le está cambiando el estado (para deshabilitar su botón). */
  protected readonly cambiandoEstado = signal<number | null>(null);

  // Opciones de los <select> del formulario
  protected readonly comercios = signal<Comercio[]>([]);
  protected readonly zonas = signal<Zona[]>([]);

  // Modales: 'formulario' (crear / editar) o 'password' (restablecer contraseña)
  protected readonly modal = signal<'formulario' | 'password' | null>(null);
  protected readonly editando = signal<Usuario | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario: FormularioUsuario = formularioVacio();
  protected passwordNueva = '';

  ngOnInit(): void {
    this.cargar(1);
    this.cargarOpciones();
  }

  protected cargar(pagina: number): void {
    this.cargando.set(true);
    this.error.set(null);

    this.api.get<Paginado<Usuario>>('/usuarios', { page: pagina }).subscribe({
      next: (respuesta) => {
        this.usuarios.set(respuesta.data);
        this.meta.set(respuesta.meta);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  /** Comercios y zonas para los <select>. por_pagina=100 trae el catálogo completo. */
  private cargarOpciones(): void {
    this.api.get<Paginado<Comercio>>('/comercios', { por_pagina: 100 }).subscribe({
      next: (respuesta) => this.comercios.set(respuesta.data),
      error: (error: unknown) => this.error.set(mensajeDeError(error)),
    });

    this.api.get<Paginado<Zona>>('/zonas', { por_pagina: 100 }).subscribe({
      next: (respuesta) => this.zonas.set(respuesta.data),
      error: (error: unknown) => this.error.set(mensajeDeError(error)),
    });
  }

  protected abrirFormulario(usuario: Usuario | null = null): void {
    this.editando.set(usuario);
    this.formulario = usuario
      ? {
          nombre: usuario.nombre,
          correo: usuario.correo,
          password: '',
          rol: usuario.rol,
          comercio_id: usuario.comercio_id,
          zona_id: usuario.zona_id,
        }
      : formularioVacio();
    this.abrirModal('formulario');
  }

  protected abrirPassword(usuario: Usuario): void {
    this.editando.set(usuario);
    this.passwordNueva = '';
    this.abrirModal('password');
  }

  private abrirModal(modal: 'formulario' | 'password'): void {
    this.errorFormulario.set(null);
    this.errores.set({});
    this.modal.set(modal);
  }

  protected cerrarModal(): void {
    this.modal.set(null);
  }

  protected guardar(): void {
    const usuario = this.editando();
    const peticion = usuario
      ? this.api.put<Respuesta<Usuario>>(`/usuarios/${usuario.id}`, this.formulario)
      : this.api.post<Respuesta<Usuario>>('/usuarios', this.formulario);

    this.enviar(peticion, (guardado) => {
      this.exito.set(
        `El usuario "${guardado.nombre}" se ${usuario ? 'actualizó' : 'creó'} correctamente.`,
      );
      this.cargar(usuario ? (this.meta()?.current_page ?? 1) : 1);
    });
  }

  protected guardarPassword(): void {
    const usuario = this.editando();
    if (!usuario) {
      return;
    }

    const peticion = this.api.put<Respuesta<Usuario>>(`/usuarios/${usuario.id}/password`, {
      password: this.passwordNueva,
    });

    this.enviar(peticion, (guardado) =>
      this.exito.set(`La contraseña de "${guardado.nombre}" se restableció correctamente.`),
    );
  }

  /** Envía un formulario del modal: si sale bien lo cierra; si no, muestra los errores por campo. */
  private enviar(
    peticion: Observable<Respuesta<Usuario>>,
    alGuardar: (usuario: Usuario) => void,
  ): void {
    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    peticion.subscribe({
      next: (respuesta) => {
        this.guardando.set(false);
        this.modal.set(null);
        alGuardar(respuesta.data);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeError(error));
        this.errores.set(camposDeError(error));
      },
    });
  }

  protected cambiarEstado(usuario: Usuario): void {
    this.cambiandoEstado.set(usuario.id);
    this.error.set(null);
    this.exito.set(null);

    this.api
      .put<Respuesta<Usuario>>(`/usuarios/${usuario.id}/estado`, { activo: !usuario.activo })
      .subscribe({
        next: (respuesta) => {
          const actualizado = respuesta.data;

          this.cambiandoEstado.set(null);
          this.usuarios.update((usuarios) =>
            usuarios.map((u) => (u.id === actualizado.id ? actualizado : u)),
          );
          this.exito.set(
            `El usuario "${actualizado.nombre}" quedó ${actualizado.activo ? 'activo' : 'inactivo'}.`,
          );
        },
        error: (error: unknown) => {
          this.cambiandoEstado.set(null);
          this.error.set(mensajeDeError(error));
        },
      });
  }
}

function formularioVacio(): FormularioUsuario {
  return { nombre: '', correo: '', password: '', rol: 'operador', comercio_id: null, zona_id: null };
}
