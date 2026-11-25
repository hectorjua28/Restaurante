import { Injectable, computed, inject, signal } from '@angular/core';
import { Capacitor } from '@capacitor/core';

import { environment } from '../../environments/environment';

import { StorageService } from './storage.service';

/**
 * URL base de la API. Por defecto la del entorno; el mesero puede cambiarla en el login
 * (p. ej. la IP de la computadora en la red local o el dominio del restaurante).
 *
 * En el APK de Android no hay un dominio propio, así que una URL relativa no sirve:
 * la app pide la dirección del servidor la primera vez.
 */
@Injectable({ providedIn: 'root' })
export class ApiConfigService {
  private readonly storage = inject(StorageService);
  private readonly custom = signal<string>(this.storage.get<string>('apiUrl', ''));

  readonly defaultUrl = environment.apiUrl;
  readonly isNative = Capacitor.isNativePlatform();

  /** true si todavía falta configurar el servidor (solo pasa en el APK). */
  readonly needsServer = computed(() => this.isNative && !/^https?:\/\//.test(this.custom() || this.defaultUrl));

  baseUrl(): string {
    return this.custom() || this.defaultUrl;
  }

  isCustom(): boolean {
    return this.custom() !== '';
  }

  /** Valida y guarda la URL. Devuelve un mensaje de error o '' si es válida. */
  setBaseUrl(url: string): string {
    let clean = url.trim().replace(/\/+$/, '');
    if (clean === '' || clean === this.defaultUrl) {
      this.custom.set('');
      this.storage.remove('apiUrl');
      return this.needsServer() ? 'Escribe la dirección del servidor, por ejemplo https://tu-dominio.com/api/v1' : '';
    }
    if (!/^https?:\/\//.test(clean)) {
      return 'La dirección debe empezar con http:// o https://';
    }
    if (!clean.endsWith('/api/v1')) {
      clean += '/api/v1';
    }
    this.custom.set(clean);
    this.storage.set('apiUrl', clean);
    return '';
  }

  isApiRequest(url: string): boolean {
    return url.startsWith(this.baseUrl());
  }
}
