import { HttpErrorResponse, provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';

import { errorMessage, isNetworkError } from './api-error';
import { ApiService } from './api.service';

const API = 'http://localhost:8000/api/v1';

describe('ApiService', () => {
  let api: ApiService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    api = TestBed.inject(ApiService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('lista mesas con sus contadores', async () => {
    const promise = firstValueFrom(api.getTables('ocupada'));
    const req = httpMock.expectOne(`${API}/tables?status=ocupada`);
    req.flush({ data: [{ id: 1, number: 1 }], meta: { counts: { total: 10, libre: 9, ocupada: 1, por_cobrar: 0 } } });

    const res = await promise;
    expect(res.meta.counts.ocupada).toBe(1);
  });

  it('envía la comanda con su clave de idempotencia y desenvuelve data', async () => {
    const payload = {
      idempotency_key: '6f1c2a5e-1111-4222-8333-444455556666',
      table_id: 3,
      items: [{ product_id: 1, quantity: 2, modifier_ids: [], note: null }],
    };
    const promise = firstValueFrom(api.createOrder(payload));
    const req = httpMock.expectOne(`${API}/orders`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual(payload);
    req.flush({ data: { id: 99, status: 'pendiente' } });

    expect((await promise).id).toBe(99);
  });

  it('pide mis órdenes filtradas por varios estados', () => {
    api.getMyOrders(['lista', 'entregada']).subscribe();
    httpMock.expectOne(`${API}/orders?status=lista,entregada`).flush({ data: [] });
  });

  it('cobra una mesa con pagos divididos', () => {
    api
      .chargeTable(5, [
        { method: 'efectivo', amount: '50.00', tip: '0.00' },
        { method: 'tarjeta', amount: '70.00', tip: '10.00' },
      ])
      .subscribe();
    const req = httpMock.expectOne(`${API}/tables/5/payments`);
    expect(req.request.body.payments.length).toBe(2);
    req.flush({ data: [] });
  });
});

describe('errorMessage', () => {
  const http = (status: number, body: unknown) => new HttpErrorResponse({ status, error: body });

  it('usa el primer error de validación', () => {
    expect(errorMessage(http(422, { message: 'x', errors: { reason: ['El campo motivo es obligatorio.'] } }))).toBe(
      'El campo motivo es obligatorio.',
    );
  });

  it('usa el mensaje de reglas de negocio', () => {
    expect(errorMessage(http(422, { message: '«Zacahuil» está agotado.', code: 'product_sold_out' }))).toBe(
      '«Zacahuil» está agotado.',
    );
  });

  it('da mensajes claros para red y códigos sin cuerpo', () => {
    expect(errorMessage(http(0, null))).toContain('Sin conexión');
    expect(isNetworkError(http(0, null))).toBe(true);
    expect(errorMessage(http(403, null))).toBe('No tienes permiso para realizar esta acción.');
    expect(errorMessage(http(500, '<html>'))).toBe('Ocurrió un error en el servidor (código 500).');
    expect(errorMessage(new Error('Algo'))).toBe('Algo');
    expect(errorMessage(undefined)).toBe('Ocurrió un error inesperado.');
  });
});
