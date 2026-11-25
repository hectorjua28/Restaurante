import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import type { Order, OrderStatus } from './models';
import { MyOrdersService } from './my-orders.service';

const API = 'http://localhost:8000/api/v1';

function order(id: number, status: OrderStatus): Order {
  return {
    id,
    status,
    status_label: status,
    status_color: '',
    table: { id: 1, number: 4 },
    waiter: { id: 1, name: 'Luis' },
    total_cents: 5000,
    cancel_reason: '',
    idempotency_key: `k-${id}`,
    items: [],
    created_at: '2026-11-24T18:00:00Z',
    updated_at: '2026-11-24T18:00:00Z',
  };
}

describe('MyOrdersService', () => {
  let service: MyOrdersService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(MyOrdersService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  async function respond(orders: Order[]): Promise<void> {
    const promise = service.refresh();
    httpMock.expectOne(`${API}/orders`).flush({ data: orders });
    await promise;
  }

  it('cuenta las órdenes listas', async () => {
    await respond([order(1, 'lista'), order(2, 'pendiente'), order(3, 'lista')]);
    expect(service.readyCount()).toBe(2);
    expect(service.loaded()).toBe(true);
  });

  it('avisa solo cuando una orden PASA a lista (no las que ya estaban)', async () => {
    const ready: number[] = [];
    service.becameReady$.subscribe((o) => ready.push(o.id));

    await respond([order(1, 'lista'), order(2, 'en_preparacion')]);
    expect(ready).toEqual([]);

    await respond([order(1, 'lista'), order(2, 'lista')]);
    expect(ready).toEqual([2]);

    await respond([order(1, 'lista'), order(2, 'lista')]);
    expect(ready).toEqual([2]);
  });

  it('marca una orden como entregada', async () => {
    await respond([order(1, 'lista')]);

    const promise = service.deliver(order(1, 'lista'));
    const req = httpMock.expectOne(`${API}/orders/1/deliver`);
    expect(req.request.method).toBe('POST');
    req.flush({ data: order(1, 'entregada') });
    await promise;

    expect(service.orders()[0].status).toBe('entregada');
    expect(service.readyCount()).toBe(0);
  });

  it('cancela con motivo y la quita de las activas', async () => {
    await respond([order(5, 'pendiente'), order(6, 'pendiente')]);

    const promise = service.cancel(order(5, 'pendiente'), '  El cliente se fue ');
    const req = httpMock.expectOne(`${API}/orders/5/cancel`);
    expect(req.request.body).toEqual({ reason: 'El cliente se fue' });
    req.flush({ data: order(5, 'cancelada') });
    await promise;

    expect(service.orders().map((o) => o.id)).toEqual([6]);
  });

  it('guarda un mensaje claro si falla la consulta', async () => {
    const promise = service.refresh();
    httpMock.expectOne(`${API}/orders`).error(new ProgressEvent('error'), { status: 0 });
    await expect(promise).rejects.toBeTruthy();

    expect(service.error()).toContain('Sin conexión');
  });
});
