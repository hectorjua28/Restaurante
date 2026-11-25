import { Injectable } from '@angular/core';

/**
 * Envoltorio seguro de localStorage (en modo privado o con almacenamiento bloqueado
 * puede lanzar excepciones; en ese caso la app sigue funcionando sin persistir).
 */
@Injectable({ providedIn: 'root' })
export class StorageService {
  private readonly prefix = 'pinguin.';

  get<T>(key: string, fallback: T): T {
    try {
      const raw = localStorage.getItem(this.prefix + key);
      return raw === null ? fallback : (JSON.parse(raw) as T);
    } catch {
      return fallback;
    }
  }

  set(key: string, value: unknown): void {
    try {
      localStorage.setItem(this.prefix + key, JSON.stringify(value));
    } catch {
      // Sin almacenamiento disponible: se ignora.
    }
  }

  remove(key: string): void {
    try {
      localStorage.removeItem(this.prefix + key);
    } catch {
      // Sin almacenamiento disponible: se ignora.
    }
  }
}
