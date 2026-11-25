import { TestBed } from '@angular/core/testing';

import type { Modifier, Product } from './models';
import { MAX_QUANTITY, OrderDraftService } from './order-draft.service';

const queso: Modifier = { id: 10, name: 'Con queso', price_delta_cents: 1000 };
const sinSalsa: Modifier = { id: 11, name: 'Sin salsa', price_delta_cents: 0 };

const bocoles: Product = {
  id: 1,
  category_id: 1,
  name: 'Bocoles',
  description: '',
  price_cents: 4500,
  station: 'cocina',
  available: true,
  modifiers: [queso, sinSalsa],
};

const cafe: Product = { ...bocoles, id: 2, name: 'Café de olla', price_cents: 3000, station: 'barra', modifiers: [] };

describe('OrderDraftService', () => {
  let draft: OrderDraftService;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({});
    draft = TestBed.inject(OrderDraftService);
  });

  it('empieza vacía y sin mesa', () => {
    expect(draft.isEmpty()).toBe(true);
    expect(draft.tableId()).toBeNull();
    expect(draft.totalCents()).toBe(0);
  });

  it('calcula el total parcial con modificadores y cantidades', () => {
    draft.selectTable(4, 4);
    draft.addProduct(bocoles, [queso], '', 2);
    draft.addProduct(cafe, [], '', 1);

    expect(draft.itemCount()).toBe(3);
    expect(draft.totalCents()).toBe(2 * 5500 + 3000);
  });

  it('junta renglones iguales y separa los que tienen otra nota o modificadores', () => {
    draft.addProduct(bocoles, [queso, sinSalsa], 'bien dorados', 1);
    draft.addProduct(bocoles, [sinSalsa, queso], '  bien dorados ', 2);
    draft.addProduct(bocoles, [queso], 'bien dorados', 1);
    draft.addProduct(bocoles, [queso, sinSalsa], '', 1);

    expect(draft.lines().length).toBe(3);
    expect(draft.lines()[0].quantity).toBe(3);
  });

  it('ajusta cantidades, quita en cero y respeta el máximo', () => {
    draft.addProduct(cafe, [], '', 1);
    const uid = draft.lines()[0].uid;

    draft.setQuantity(uid, 999);
    expect(draft.lines()[0].quantity).toBe(MAX_QUANTITY);

    draft.setQuantity(uid, 0);
    expect(draft.isEmpty()).toBe(true);
  });

  it('genera una clave de idempotencia nueva cada vez que cambia la comanda', () => {
    draft.selectTable(1, 1);
    draft.addProduct(cafe, [], '', 1);
    const first = draft.draft().idempotencyKey;

    // Sin cambios, la clave se mantiene (reintentos seguros).
    expect(draft.toPayload().idempotency_key).toBe(first);

    draft.addProduct(cafe, [], '', 1);
    expect(draft.draft().idempotencyKey).not.toBe(first);
    expect(draft.draft().idempotencyKey).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('arma el payload que espera la API', () => {
    draft.selectTable(7, 3);
    draft.addProduct(bocoles, [queso], '  sin cebolla ', 2);
    draft.addProduct(cafe, [], '', 1);

    const payload = draft.toPayload();
    expect(payload.table_id).toBe(7);
    expect(payload.items).toEqual([
      { product_id: 1, quantity: 2, modifier_ids: [10], note: 'sin cebolla' },
      { product_id: 2, quantity: 1, modifier_ids: [], note: null },
    ]);
  });

  it('no arma el payload sin mesa o sin platillos', () => {
    expect(() => draft.toPayload()).toThrow('Selecciona una mesa');
    draft.selectTable(1, 1);
    expect(() => draft.toPayload()).toThrow('Agrega al menos un platillo');
  });

  it('se conserva en el teléfono y se limpia al enviar', () => {
    draft.selectTable(2, 2);
    draft.addProduct(cafe, [], '', 2);

    TestBed.resetTestingModule();
    const reloaded = TestBed.inject(OrderDraftService);
    expect(reloaded.itemCount()).toBe(2);
    expect(reloaded.tableNumber()).toBe(2);

    reloaded.clear();
    expect(reloaded.isEmpty()).toBe(true);
    expect(reloaded.tableId()).toBeNull();
  });
});
