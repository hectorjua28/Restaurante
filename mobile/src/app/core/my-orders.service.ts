import { Injectable, computed, inject, signal } from '@angular/core';
import { Subject, firstValueFrom } from 'rxjs';

import { Poller } from '../shared/polling';

import { errorMessage } from './api-error';
import { ApiService } from './api.service';
import type { Order } from './models';

/**
 * Órdenes activas del mesero. Se consultan cada 5 s mientras hay sesión para poder avisar
 * en cualquier pestaña cuando cocina marca una orden como lista.
 */
@Injectable({ providedIn: 'root' })
export class MyOrdersService {
  private readonly api = inject(ApiService);

  readonly orders = signal<Order[]>([]);
  readonly loaded = signal(false);
  readonly error = signal('');

  readonly readyCount = computed(() => this.orders().filter((o) => o.status === 'lista').length);

  /** Emite cada orden que acaba de pasar a «lista» (no las que ya estaban listas al abrir la app). */
  readonly becameReady$ = new Subject<Order>();

  private knownReady: Set<number> | null = null;
  private readonly poller = new Poller(() => this.refresh(), 5000);

  start(): void {
    this.poller.start();
  }

  stop(): void {
    this.poller.stop();
    this.knownReady = null;
    this.orders.set([]);
    this.loaded.set(false);
  }

  async refresh(): Promise<void> {
    try {
      const orders = await firstValueFrom(this.api.getMyOrders());
      this.detectNewlyReady(orders);
      this.orders.set(orders);
      this.error.set('');
    } catch (e) {
      this.error.set(errorMessage(e));
      throw e;
    } finally {
      this.loaded.set(true);
    }
  }

  async deliver(order: Order): Promise<Order> {
    const updated = await firstValueFrom(this.api.deliverOrder(order.id));
    this.replace(updated);
    return updated;
  }

  async cancel(order: Order, reason: string): Promise<Order> {
    const updated = await firstValueFrom(this.api.cancelOrder(order.id, reason.trim()));
    // Las canceladas ya no son activas: se quitan de la lista.
    this.orders.update((list) => list.filter((o) => o.id !== updated.id));
    return updated;
  }

  private replace(updated: Order): void {
    this.orders.update((list) => list.map((o) => (o.id === updated.id ? updated : o)));
  }

  private detectNewlyReady(orders: Order[]): void {
    const ready = new Set(orders.filter((o) => o.status === 'lista').map((o) => o.id));
    if (this.knownReady !== null) {
      for (const order of orders) {
        if (order.status === 'lista' && !this.knownReady.has(order.id)) {
          this.becameReady$.next(order);
        }
      }
    }
    this.knownReady = ready;
  }
}
