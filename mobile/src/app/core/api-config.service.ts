import { Injectable, inject, signal } from '@angular/core';

import { environment } from '../../environments/environment';

import { StorageService } from './storage.service';

/** URL base de la API. Por defecto la del entorno; el mesero puede cambiarla en el login (p. ej. IP de la red local). */
@Injectable({ providedIn: 'root' })
export class ApiConfigService {
  private readonly storage = inject(StorageService);
  private readonly custom = signal<string>(this.storage.get<string>('apiUrl', ''));

  readonly defaultUrl = environment.apiUrl;

  baseUrl(): string {
    return this.custom() || this.defaultUrl;
  }

  isCustom(): boolean {
    return this.custom() !== '';
  }

  setBaseUrl(url: string): void {
    const clean = url.trim().replace(/\/+$/, '');
    if (clean === '' || clean === this.defaultUrl) {
      this.custom.set('');
      this.storage.remove('apiUrl');
      return;
    }
    this.custom.set(clean);
    this.storage.set('apiUrl', clean);
  }

  isApiRequest(url: string): boolean {
    return url.startsWith(this.baseUrl());
  }
}
