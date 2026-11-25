import { Injectable, computed, inject, signal } from '@angular/core';

import type { Modifier, NewOrderPayload, Product } from './models';
import { StorageService } from './storage.service';

export interface DraftLine {
  uid: string;
  productId: number;
  name: string;
  basePriceCents: number;
  modifiers: Modifier[];
  note: string;
  quantity: number;
}

export interface Draft {
  tableId: number | null;
  tableNumber: number | null;
  /** Se regenera cada vez que cambia la comanda; si se reintenta un envío sin cambios, el servidor no la duplica. */
  idempotencyKey: string;
  lines: DraftLine[];
}

export const MAX_QUANTITY = 50;

export function newUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  // Respaldo RFC 4122 v4 para webviews antiguos.
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
  });
}

export function lineUnitCents(line: Pick<DraftLine, 'basePriceCents' | 'modifiers'>): number {
  return line.basePriceCents + line.modifiers.reduce((sum, m) => sum + m.price_delta_cents, 0);
}

function emptyDraft(): Draft {
  return { tableId: null, tableNumber: null, idempotencyKey: newUuid(), lines: [] };
}

/**
 * Comanda en curso (carrito). Se guarda en el teléfono para no perderla si se cierra la app.
 * El total es solo informativo: el servidor recalcula todo al recibir la orden.
 */
@Injectable({ providedIn: 'root' })
export class OrderDraftService {
  private readonly storage = inject(StorageService);
  private readonly state = signal<Draft>(this.storage.get<Draft>('draft', emptyDraft()));

  readonly draft = this.state.asReadonly();
  readonly lines = computed(() => this.state().lines);
  readonly tableId = computed(() => this.state().tableId);
  readonly tableNumber = computed(() => this.state().tableNumber);
  readonly itemCount = computed(() => this.state().lines.reduce((n, l) => n + l.quantity, 0));
  readonly totalCents = computed(() => this.state().lines.reduce((sum, l) => sum + lineUnitCents(l) * l.quantity, 0));
  readonly isEmpty = computed(() => this.state().lines.length === 0);

  selectTable(tableId: number, tableNumber: number): void {
    this.update((d) => ({ ...d, tableId, tableNumber }));
  }

  addProduct(product: Product, modifiers: Modifier[], note: string, quantity: number): void {
    const cleanNote = note.trim();
    const modIds = modifiers.map((m) => m.id).sort((a, b) => a - b);
    const qty = this.clamp(quantity);

    this.update((d) => {
      const same = d.lines.find(
        (l) =>
          l.productId === product.id &&
          l.note === cleanNote &&
          l.modifiers
            .map((m) => m.id)
            .sort((a, b) => a - b)
            .join(',') === modIds.join(','),
      );
      const lines = same
        ? d.lines.map((l) => (l === same ? { ...l, quantity: this.clamp(l.quantity + qty) } : l))
        : [
            ...d.lines,
            {
              uid: newUuid(),
              productId: product.id,
              name: product.name,
              basePriceCents: product.price_cents,
              modifiers: [...modifiers],
              note: cleanNote,
              quantity: qty,
            },
          ];
      return { ...d, lines };
    });
  }

  setQuantity(uid: string, quantity: number): void {
    if (quantity < 1) {
      this.remove(uid);
      return;
    }
    this.update((d) => ({
      ...d,
      lines: d.lines.map((l) => (l.uid === uid ? { ...l, quantity: this.clamp(quantity) } : l)),
    }));
  }

  remove(uid: string): void {
    this.update((d) => ({ ...d, lines: d.lines.filter((l) => l.uid !== uid) }));
  }

  /** Vacía la comanda y deja la mesa sin seleccionar. */
  clear(): void {
    this.state.set(emptyDraft());
    this.persist();
  }

  toPayload(): NewOrderPayload {
    const d = this.state();
    if (d.tableId === null) {
      throw new Error('Selecciona una mesa antes de enviar la comanda.');
    }
    if (d.lines.length === 0) {
      throw new Error('Agrega al menos un platillo.');
    }
    return {
      idempotency_key: d.idempotencyKey,
      table_id: d.tableId,
      items: d.lines.map((l) => ({
        product_id: l.productId,
        quantity: l.quantity,
        modifier_ids: l.modifiers.map((m) => m.id),
        note: l.note === '' ? null : l.note,
      })),
    };
  }

  private update(fn: (d: Draft) => Draft): void {
    // Cualquier cambio genera una clave nueva: es otra comanda distinta.
    this.state.update((d) => ({ ...fn(d), idempotencyKey: newUuid() }));
    this.persist();
  }

  private persist(): void {
    this.storage.set('draft', this.state());
  }

  private clamp(qty: number): number {
    return Math.min(MAX_QUANTITY, Math.max(1, Math.floor(qty)));
  }
}
