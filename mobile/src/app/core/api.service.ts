import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import type { Observable } from 'rxjs';
import { map } from 'rxjs';

import { ApiConfigService } from './api-config.service';
import type {
  Bill,
  Category,
  DataResponse,
  DiningTable,
  NewOrderPayload,
  Order,
  OrderStatus,
  Payment,
  PaymentInput,
  Product,
  TableStatus,
  TablesResponse,
} from './models';

/** Acceso tipado a la API REST. Toda la lógica de negocio vive en el servidor. */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly config = inject(ApiConfigService);

  private url(path: string): string {
    return `${this.config.baseUrl()}${path}`;
  }

  getTables(status?: TableStatus): Observable<TablesResponse> {
    const params = status ? new HttpParams().set('status', status) : undefined;
    return this.http.get<TablesResponse>(this.url('/tables'), { params });
  }

  getTable(id: number): Observable<DiningTable> {
    return this.http.get<DataResponse<DiningTable>>(this.url(`/tables/${id}`)).pipe(map((r) => r.data));
  }

  getCategories(): Observable<Category[]> {
    return this.http.get<DataResponse<Category[]>>(this.url('/categories')).pipe(map((r) => r.data));
  }

  getProducts(): Observable<Product[]> {
    return this.http.get<DataResponse<Product[]>>(this.url('/products')).pipe(map((r) => r.data));
  }

  createOrder(payload: NewOrderPayload): Observable<Order> {
    return this.http.post<DataResponse<Order>>(this.url('/orders'), payload).pipe(map((r) => r.data));
  }

  getMyOrders(statuses: OrderStatus[] = []): Observable<Order[]> {
    const params = statuses.length ? new HttpParams().set('status', statuses.join(',')) : undefined;
    return this.http.get<DataResponse<Order[]>>(this.url('/orders'), { params }).pipe(map((r) => r.data));
  }

  getOrder(id: number): Observable<Order> {
    return this.http.get<DataResponse<Order>>(this.url(`/orders/${id}`)).pipe(map((r) => r.data));
  }

  deliverOrder(id: number): Observable<Order> {
    return this.http.post<DataResponse<Order>>(this.url(`/orders/${id}/deliver`), {}).pipe(map((r) => r.data));
  }

  cancelOrder(id: number, reason: string): Observable<Order> {
    return this.http.post<DataResponse<Order>>(this.url(`/orders/${id}/cancel`), { reason }).pipe(map((r) => r.data));
  }

  getBill(tableId: number): Observable<Bill> {
    return this.http.get<DataResponse<Bill>>(this.url(`/tables/${tableId}/bill`)).pipe(map((r) => r.data));
  }

  chargeTable(tableId: number, payments: PaymentInput[]): Observable<Payment[]> {
    return this.http
      .post<DataResponse<Payment[]>>(this.url(`/tables/${tableId}/payments`), { payments })
      .pipe(map((r) => r.data));
  }
}
