import { Injectable, computed, signal } from '@angular/core';

/**
 * Estado de conexión: combina el aviso del sistema (navigator.onLine) con el resultado
 * real de la última petición al servidor.
 */
@Injectable({ providedIn: 'root' })
export class ConnectivityService {
  private readonly browserOnline = signal(typeof navigator === 'undefined' ? true : navigator.onLine);
  private readonly serverReachable = signal(true);

  readonly online = computed(() => this.browserOnline() && this.serverReachable());

  constructor() {
    if (typeof window !== 'undefined') {
      window.addEventListener('online', () => {
        this.browserOnline.set(true);
        this.serverReachable.set(true);
      });
      window.addEventListener('offline', () => this.browserOnline.set(false));
    }
  }

  reportSuccess(): void {
    this.serverReachable.set(true);
  }

  reportNetworkFailure(): void {
    this.serverReachable.set(false);
  }
}
