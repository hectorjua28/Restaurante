import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Subject, firstValueFrom } from 'rxjs';

import { errorMessage, isNetworkError } from './api-error';
import { ApiService } from './api.service';
import { AuthService } from './auth.service';
import type { NewOrderPayload, Order } from './models';
import { outboxDb } from './outbox-db';

export type QueuedStatus = 'pendiente' | 'enviando' | 'rechazada';

export interface QueuedOrder {
  payload: NewOrderPayload;
  userId: number;
  tableNumber: number;
  itemCount: number;
  totalCents: number;
  /** ISO en UTC. */
  queuedAt: string;
  attempts: number;
  status: QueuedStatus;
  /** Motivo si el servidor la rechazó (p. ej. un platillo se agotó). */
  error: string;
}

/**
 * Bandeja de salida de comandas. Si no hay señal, la comanda se guarda en el teléfono (IndexedDB)
 * y se envía sola al volver la conexión, con la MISMA idempotency_key: el servidor nunca la duplica.
 */
@Injectable({ providedIn: 'root' })
export class OfflineQueueService {
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);

  private readonly all = signal<QueuedOrder[]>([]);
  private flushing = false;
  private timer: ReturnType<typeof setInterval> | null = null;

  /** Comandas del mesero actual que siguen en el teléfono. */
  readonly items = computed(() => {
    const userId = this.auth.user()?.id;
    return this.all()
      .filter((q) => q.userId === userId)
      .sort((a, b) => a.queuedAt.localeCompare(b.queuedAt));
  });
  readonly pendingCount = computed(() => this.items().filter((q) => q.status !== 'rechazada').length);
  readonly rejectedCount = computed(() => this.items().filter((q) => q.status === 'rechazada').length);

  /** Emite cada comanda guardada que por fin llegó al servidor. */
  readonly sent$ = new Subject<Order>();

  /** Carga la bandeja y empieza a reintentar: al volver la red y cada 15 s mientras haya pendientes. */
  async start(): Promise<void> {
    await this.load();
    if (typeof window !== 'undefined') {
      window.addEventListener('online', this.onOnline);
    }
    this.timer ??= setInterval(() => {
      if (this.pendingCount() > 0) void this.flush();
    }, 15000);
    void this.flush();
  }

  stop(): void {
    if (typeof window !== 'undefined') {
      window.removeEventListener('online', this.onOnline);
    }
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  async load(): Promise<void> {
    try {
      const stored = await outboxDb.getAll<QueuedOrder>();
      // Si la app se cerró a mitad de un envío, vuelve a quedar pendiente.
      this.all.set(stored.map((q) => (q.status === 'enviando' ? { ...q, status: 'pendiente' } : q)));
    } catch {
      this.all.set([]);
    }
  }

  async enqueue(
    payload: NewOrderPayload,
    meta: { tableNumber: number; itemCount: number; totalCents: number },
  ): Promise<void> {
    const user = this.auth.user();
    if (!user) throw new Error('Inicia sesión para guardar la comanda.');

    const record: QueuedOrder = {
      payload,
      userId: user.id,
      ...meta,
      queuedAt: new Date().toISOString(),
      attempts: 0,
      status: 'pendiente',
      error: '',
    };
    await outboxDb.put(payload.idempotency_key, record);
    this.upsert(record);
  }

  /**
   * Envía las pendientes en orden de llegada. Se detiene al primer error de red.
   * Devuelve las órdenes que se registraron en el servidor.
   */
  async flush(): Promise<Order[]> {
    if (this.flushing || !this.auth.isLoggedIn()) return [];
    this.flushing = true;
    const sent: Order[] = [];

    try {
      for (const item of this.items().filter((q) => q.status === 'pendiente')) {
        await this.save({ ...item, status: 'enviando', attempts: item.attempts + 1 });
        try {
          const order = await firstValueFrom(this.api.createOrder(item.payload));
          await outboxDb.delete(item.payload.idempotency_key);
          this.all.update((list) => list.filter((q) => q.payload.idempotency_key !== item.payload.idempotency_key));
          sent.push(order);
          this.sent$.next(order);
        } catch (e) {
          if (isNetworkError(e) || (e instanceof HttpErrorResponse && (e.status === 401 || e.status >= 500))) {
            // Sin red, sesión vencida o servidor caído: se reintenta después.
            await this.save({ ...item, status: 'pendiente', attempts: item.attempts + 1, error: errorMessage(e) });
            break;
          }
          // El servidor la rechazó (422, 403…): reintentar no sirve, el mesero debe decidir.
          await this.save({ ...item, status: 'rechazada', attempts: item.attempts + 1, error: errorMessage(e) });
        }
      }
    } finally {
      this.flushing = false;
    }
    return sent;
  }

  async discard(key: string): Promise<void> {
    await outboxDb.delete(key);
    this.all.update((list) => list.filter((q) => q.payload.idempotency_key !== key));
  }

  async retry(key: string): Promise<Order[]> {
    const item = this.all().find((q) => q.payload.idempotency_key === key);
    if (item) await this.save({ ...item, status: 'pendiente', error: '' });
    return this.flush();
  }

  private readonly onOnline = (): void => {
    void this.flush();
  };

  private async save(record: QueuedOrder): Promise<void> {
    await outboxDb.put(record.payload.idempotency_key, record);
    this.upsert(record);
  }

  private upsert(record: QueuedOrder): void {
    this.all.update((list) => [
      ...list.filter((q) => q.payload.idempotency_key !== record.payload.idempotency_key),
      record,
    ]);
  }
}
