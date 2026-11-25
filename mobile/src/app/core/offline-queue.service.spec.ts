import 'fake-indexeddb/auto';

import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { AuthService } from './auth.service';
import type { NewOrderPayload, User } from './models';
import { OfflineQueueService } from './offline-queue.service';
import { outboxDb } from './outbox-db';

const API = 'http://localhost:8000/api/v1';
const luis: User = { id: 3, name: 'Luis', email: 'l@p.test', role: 'mesero', role_label: 'Mesero' };

function payload(key: string, table = 4): NewOrderPayload {
  return {
    idempotency_key: key,
    table_id: table,
    items: [{ product_id: 1, quantity: 2, modifier_ids: [], note: null }],
  };
}

describe('OfflineQueueService (comandas sin conexión)', () => {
  let queue: OfflineQueueService;
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    localStorage.clear();
    localStorage.setItem('pinguin.session', JSON.stringify({ token: 't', user: luis }));
    outboxDb.reset();
    await outboxDb.clear();

    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    queue = TestBed.inject(OfflineQueueService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    queue.stop();
    httpMock.verify();
  });

  const meta = { tableNumber: 4, itemCount: 2, totalCents: 9000 };

  /** Espera a que flush() haga la siguiente petición (cada paso de IndexedDB es asíncrono). */
  async function nextRequest(url = `${API}/orders`) {
    for (let i = 0; i < 50; i++) {
      const found = httpMock.match(url);
      if (found.length) return found[0];
      await new Promise((r) => setTimeout(r, 5));
    }
    throw new Error('No llegó la petición esperada');
  }

  it('guarda la comanda en el teléfono y cuenta las pendientes', async () => {
    await queue.enqueue(payload('a'), meta);

    expect(queue.pendingCount()).toBe(1);
    expect(queue.items()[0].tableNumber).toBe(4);
    expect((await outboxDb.getAll()).length).toBe(1);
  });

  it('sobrevive a cerrar la app (se recarga desde IndexedDB)', async () => {
    await queue.enqueue(payload('a'), meta);

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()] });
    const reopened = TestBed.inject(OfflineQueueService);
    httpMock = TestBed.inject(HttpTestingController);
    await reopened.load();

    expect(reopened.pendingCount()).toBe(1);
  });

  it('al volver la señal envía con la MISMA idempotency_key y la quita de la bandeja', async () => {
    const sent: number[] = [];
    queue.sent$.subscribe((o) => sent.push(o.id));
    await queue.enqueue(payload('clave-1'), meta);

    const flushing = queue.flush();
    const req = await nextRequest();
    expect(req.request.body.idempotency_key).toBe('clave-1');
    req.flush({ data: { id: 77, table: { id: 4, number: 4 } } });
    await flushing;

    expect(sent).toEqual([77]);
    expect(queue.pendingCount()).toBe(0);
    expect(await outboxDb.getAll()).toEqual([]);
  });

  it('si sigue sin red, se queda pendiente y suma el intento', async () => {
    await queue.enqueue(payload('a'), meta);

    const flushing = queue.flush();
    (await nextRequest()).error(new ProgressEvent('error'), { status: 0 });
    await flushing;

    expect(queue.pendingCount()).toBe(1);
    expect(queue.items()[0].attempts).toBe(1);
    expect(queue.items()[0].status).toBe('pendiente');
  });

  it('si el servidor la rechaza (p. ej. platillo agotado) la marca como rechazada con el motivo', async () => {
    await queue.enqueue(payload('a'), meta);

    const flushing = queue.flush();
    (await nextRequest()).flush(
      { message: '«Zacahuil» está agotado.', code: 'product_sold_out' },
      { status: 422, statusText: 'Unprocessable' },
    );
    await flushing;

    expect(queue.pendingCount()).toBe(0);
    expect(queue.rejectedCount()).toBe(1);
    expect(queue.items()[0].error).toBe('«Zacahuil» está agotado.');

    await queue.discard('a');
    expect(queue.items()).toEqual([]);
  });

  it('envía en orden de llegada y se detiene al primer error de red', async () => {
    await queue.enqueue(payload('primera', 1), meta);
    await new Promise((r) => setTimeout(r, 2));
    await queue.enqueue(payload('segunda', 2), meta);

    const flushing = queue.flush();
    const first = await nextRequest();
    expect(first.request.body.idempotency_key).toBe('primera');
    first.error(new ProgressEvent('error'), { status: 0 });
    await flushing;

    httpMock.expectNone(`${API}/orders`);
    expect(queue.pendingCount()).toBe(2);
  });

  it('solo muestra y envía las comandas del mesero con sesión', async () => {
    await queue.enqueue(payload('de-luis'), meta);
    const auth = TestBed.inject(AuthService);
    auth.clearSession();

    expect(queue.items()).toEqual([]);
    expect(await queue.flush()).toEqual([]);
  });
});
