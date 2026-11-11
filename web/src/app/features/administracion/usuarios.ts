import { DatePipe } from '@angular/common';
import { Component, Injector, OnInit, afterNextRender, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { CatalogosService } from '../../core/catalogos.service';
import {
  ErroresDeCampos,
  ROLES,
  camposDeError,
  codigoDeError,
  mensajeDeError,
  mensajeDeFormulario,
} from '../../core/etiquetas';
import { MensajesService } from '../../core/mensajes.service';
import { Comercio, Paginado, Respuesta, Rol, Usuario, Zona } from '../../core/modelos';
import { BadgeEstado } from '../../shared/badge-estado';
import { Campo } from '../../shared/campo';
import { Confirmacion } from '../../shared/confirmacion';
import { Modal } from '../../shared/modal';
import { Columna, Tabla } from '../../shared/tabla';
import { Tarjeta } from '../../shared/tarjeta';

interface FormularioUsuario {
  nombre: string;
  correo: string;
  password: string;
  rol: Rol;
  comercio_id: number | null;
  zona_id: number | null;
}

/** Errores de la API que son reglas de negocio de esta pantalla: se muestran como aviso ámbar. */
const REGLAS_DE_USUARIOS = [
  'NO_PUEDES_DESACTIVARTE',
  'NO_PUEDES_CAMBIAR_TU_ROL',
  'ULTIMO_ADMINISTRADOR',
];

/**
 * Gestión de usuarios (INT-02): tabla de usuarios y, debajo, el formulario para crear o editar.
 * Ajustes al modelo: se mantiene el campo nombre, la zona aparece si el rol es repartidor y en
 * lugar de "creado / modificado por" se muestran solo las fechas.
 */
@Component({
  selector: 'app-usuarios',
  imports: [DatePipe, FormsModule, Tarjeta, Tabla, BadgeEstado, Campo, Modal, Confirmacion],
  templateUrl: './usuarios.html',
})
export class Usuarios implements OnInit {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly catalogos = inject(CatalogosService);
  private readonly mensajes = inject(MensajesService);
  private readonly injector = inject(Injector);

  protected readonly nombresRol = ROLES;
  protected readonly roles = Object.keys(ROLES) as Rol[];
  protected readonly miId = this.auth.usuario()?.id ?? null;
  protected readonly columnas: Columna[] = ['Correo', 'Rol', 'Estado', 'Acciones'];

  // Lista
  protected readonly datos = signal<Usuario[]>([]);
  protected readonly meta = signal<Paginado<Usuario>['meta'] | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);
  /** Regla de negocio que impidió una acción de la tabla (aviso ámbar debajo de ella). */
  protected readonly aviso = signal<string | null>(null);
  /** Usuario al que se le pide confirmar la desactivación. */
  protected readonly porDesactivar = signal<Usuario | null>(null);
  /** Id del usuario al que se le está cambiando el estado (para deshabilitar sus botones). */
  protected readonly cambiandoEstado = signal<number | null>(null);

  // Opciones de los <select> del formulario
  protected readonly comercios = signal<Comercio[]>([]);
  protected readonly zonas = signal<Zona[]>([]);

  // Formulario (tarjeta debajo de la tabla, como en el mockup)
  protected readonly formularioAbierto = signal(false);
  protected readonly editando = signal<Usuario | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorFormulario = signal<string | null>(null);
  protected readonly errores = signal<ErroresDeCampos>({});
  protected formulario: FormularioUsuario = formularioVacio();

  // Modal "Restablecer contraseña"
  protected readonly restableciendo = signal<Usuario | null>(null);
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
        this.datos.set(respuesta.data);
        this.meta.set(respuesta.meta);
        this.cargando.set(false);
      },
      error: (error: unknown) => {
        this.error.set(mensajeDeError(error));
        this.cargando.set(false);
      },
    });
  }

  /** Comercios y zonas para los <select>, desde los catálogos en memoria. */
  private cargarOpciones(): void {
    this.catalogos.comercios().subscribe({
      next: (comercios) => this.comercios.set(comercios),
      error: (error: unknown) => this.mensajes.error(mensajeDeError(error)),
    });

    this.catalogos.zonas().subscribe({
      next: (zonas) => this.zonas.set(zonas),
      error: (error: unknown) => this.mensajes.error(mensajeDeError(error)),
    });
  }

  // ── Formulario ──────────────────────────────────────────────────────────────

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
    this.errorFormulario.set(null);
    this.errores.set({});
    this.formularioAbierto.set(true);

    // El formulario está debajo de la tabla: se lleva el foco (y la vista) a su primer campo.
    afterNextRender(() => document.getElementById('usuario-nombre')?.focus(), {
      injector: this.injector,
    });
  }

  protected cerrarFormulario(): void {
    this.formularioAbierto.set(false);
    this.editando.set(null);
  }

  protected guardar(): void {
    const usuario = this.editando();
    const peticion = usuario
      ? this.api.put<Respuesta<Usuario>>(`/usuarios/${usuario.id}`, this.formulario)
      : this.api.post<Respuesta<Usuario>>('/usuarios', this.formulario);

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    peticion.subscribe({
      next: (respuesta) => {
        this.guardando.set(false);
        this.cerrarFormulario();
        this.mensajes.exito(
          `Usuario ${respuesta.data.correo} ${usuario ? 'actualizado' : 'creado'} correctamente.`,
        );
        this.cargar(usuario ? (this.meta()?.current_page ?? 1) : 1);
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorFormulario.set(mensajeDeFormulario(error));
        this.errores.set(camposDeError(error));
      },
    });
  }

  // ── Activar / desactivar ────────────────────────────────────────────────────

  /** Desactivar pide confirmación (cierra las sesiones del usuario); activar no. */
  protected alternarEstado(usuario: Usuario): void {
    if (usuario.activo) {
      this.porDesactivar.set(usuario);
    } else {
      this.cambiarEstado(usuario);
    }
  }

  protected cambiarEstado(usuario: Usuario): void {
    this.cambiandoEstado.set(usuario.id);
    this.aviso.set(null);

    this.api
      .put<Respuesta<Usuario>>(`/usuarios/${usuario.id}/estado`, { activo: !usuario.activo })
      .subscribe({
        next: (respuesta) => {
          const actualizado = respuesta.data;

          this.cambiandoEstado.set(null);
          this.porDesactivar.set(null);
          this.datos.update((usuarios) =>
            usuarios.map((u) => (u.id === actualizado.id ? actualizado : u)),
          );
          this.mensajes.exito(
            `Usuario ${actualizado.correo} ${actualizado.activo ? 'activado' : 'desactivado'}.`,
          );
        },
        error: (error: unknown) => {
          this.cambiandoEstado.set(null);
          this.porDesactivar.set(null);
          this.mostrarErrorDeAccion(error);
        },
      });
  }

  // ── Restablecer contraseña ──────────────────────────────────────────────────

  protected abrirPassword(usuario: Usuario): void {
    this.restableciendo.set(usuario);
    this.passwordNueva = '';
    this.errorFormulario.set(null);
    this.errores.set({});
  }

  protected guardarPassword(): void {
    const usuario = this.restableciendo();
    if (!usuario) {
      return;
    }

    this.guardando.set(true);
    this.errorFormulario.set(null);
    this.errores.set({});

    this.api
      .put<Respuesta<Usuario>>(`/usuarios/${usuario.id}/password`, { password: this.passwordNueva })
      .subscribe({
        next: () => {
          this.guardando.set(false);
          this.restableciendo.set(null);
          this.mensajes.exito(`Contraseña de ${usuario.correo} restablecida correctamente.`);
        },
        error: (error: unknown) => {
          this.guardando.set(false);
          this.errorFormulario.set(mensajeDeFormulario(error));
          this.errores.set(camposDeError(error));
        },
      });
  }

  /** Una regla de negocio va como aviso ámbar bajo la tabla (INT-02); cualquier otro error, en un toast. */
  private mostrarErrorDeAccion(error: unknown): void {
    if (REGLAS_DE_USUARIOS.includes(codigoDeError(error) ?? '')) {
      this.aviso.set(mensajeDeError(error));
    } else {
      this.mensajes.error(mensajeDeError(error));
    }
  }
}

function formularioVacio(): FormularioUsuario {
  return {
    nombre: '',
    correo: '',
    password: '',
    rol: 'operador',
    comercio_id: null,
    zona_id: null,
  };
}
