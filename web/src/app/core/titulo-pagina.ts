import { Injectable, inject, signal } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { RouterStateSnapshot, TitleStrategy } from '@angular/router';

/**
 * Título de cada página. Se escribe una sola vez en app.routes.ts (`title: 'Gestión de usuarios'`)
 * y aparece en la barra superior (layout) y en la pestaña del navegador ("Gestión de usuarios · Última Milla").
 */
@Injectable({ providedIn: 'root' })
export class TituloPagina extends TitleStrategy {
  private readonly title = inject(Title);
  private readonly _titulo = signal('');

  readonly titulo = this._titulo.asReadonly();

  override updateTitle(estado: RouterStateSnapshot): void {
    const titulo = this.buildTitle(estado) ?? '';

    this._titulo.set(titulo);
    this.title.setTitle(titulo ? `${titulo} · Última Milla` : 'Última Milla');
  }
}
