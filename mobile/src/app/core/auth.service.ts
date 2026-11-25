import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { ApiConfigService } from './api-config.service';
import type { LoginResponse, User } from './models';
import { StorageService } from './storage.service';

interface Session {
  token: string;
  user: User;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly storage = inject(StorageService);
  private readonly config = inject(ApiConfigService);

  private readonly session = signal<Session | null>(this.storage.get<Session | null>('session', null));

  readonly user = computed(() => this.session()?.user ?? null);
  readonly token = computed(() => this.session()?.token ?? null);
  readonly isLoggedIn = computed(() => this.session() !== null);

  async login(email: string, password: string): Promise<User> {
    const response = await firstValueFrom(
      this.http.post<LoginResponse>(`${this.config.baseUrl()}/auth/login`, {
        email: email.trim(),
        password,
        device_name: 'App meseros',
      }),
    );

    if (response.user.role !== 'mesero') {
      // La app es solo para meseros: revocamos el token recién creado.
      this.http
        .post(`${this.config.baseUrl()}/auth/logout`, {}, { headers: { Authorization: `Bearer ${response.token}` } })
        .subscribe({ error: () => undefined });
      throw new Error('Esta app es para meseros. Cocina y administración usan el sitio web.');
    }

    this.setSession({ token: response.token, user: response.user });
    return response.user;
  }

  /** Cierra sesión en el servidor (si hay red) y localmente. */
  async logout(): Promise<void> {
    if (this.token()) {
      try {
        await firstValueFrom(this.http.post(`${this.config.baseUrl()}/auth/logout`, {}));
      } catch {
        // Si falla (sin red o token ya inválido) igual cerramos la sesión local.
      }
    }
    this.clearSession();
    await this.router.navigateByUrl('/login', { replaceUrl: true });
  }

  /** Borra la sesión local sin llamar al servidor (se usa al recibir un 401). */
  clearSession(): void {
    this.session.set(null);
    this.storage.remove('session');
  }

  private setSession(session: Session): void {
    this.session.set(session);
    this.storage.set('session', session);
  }
}
