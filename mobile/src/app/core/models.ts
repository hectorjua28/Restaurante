/** Tipos que reflejan las respuestas de la API (/api/v1). El dinero siempre viene en centavos. */

export type Role = 'mesero' | 'cocina' | 'admin';
export type TableStatus = 'libre' | 'ocupada' | 'por_cobrar';
export type OrderStatus = 'pendiente' | 'en_preparacion' | 'lista' | 'entregada' | 'cobrada' | 'cancelada';
export type Station = 'cocina' | 'barra';
export type PaymentMethod = 'efectivo' | 'tarjeta' | 'transferencia';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  role_label: string;
}

export interface LoginResponse {
  token: string;
  user: User;
}

export interface DiningTable {
  id: number;
  number: number;
  capacity: number;
  status: TableStatus;
  status_label: string;
  active_orders_count: number;
  active_total_cents: number;
}

export interface TableCounts {
  total: number;
  libre: number;
  ocupada: number;
  por_cobrar: number;
}

export interface Category {
  id: number;
  name: string;
  position: number;
}

export interface Modifier {
  id: number;
  name: string;
  price_delta_cents: number;
}

export interface Product {
  id: number;
  category_id: number;
  name: string;
  description: string;
  price_cents: number;
  station: Station;
  available: boolean;
  modifiers: Modifier[];
}

export interface OrderItem {
  id: number;
  product_id: number;
  name: string;
  station: Station;
  quantity: number;
  unit_price_cents: number;
  subtotal_cents: number;
  modifiers: Modifier[];
  note: string;
}

export interface OrderHistoryEntry {
  from_status: OrderStatus | null;
  to_status: OrderStatus;
  to_status_label: string;
  user_name: string;
  created_at: string;
}

export interface Order {
  id: number;
  status: OrderStatus;
  status_label: string;
  status_color: string;
  table: { id: number; number: number };
  waiter: { id: number; name: string };
  total_cents: number;
  cancel_reason: string;
  idempotency_key: string;
  items: OrderItem[];
  history?: OrderHistoryEntry[];
  created_at: string;
  updated_at: string;
}

export interface NewOrderItem {
  product_id: number;
  quantity: number;
  modifier_ids: number[];
  note: string | null;
}

export interface NewOrderPayload {
  idempotency_key: string;
  table_id: number;
  items: NewOrderItem[];
}

export interface Bill {
  table: { id: number; number: number; status: TableStatus };
  orders: Order[];
  total_cents: number;
  pending_count: number;
  can_charge: boolean;
  blocking_reason: string;
}

export interface PaymentInput {
  method: PaymentMethod;
  /** Pesos como texto, p. ej. "120.50". */
  amount: string;
  tip: string;
}

export interface Payment {
  id: number;
  table_id: number;
  method: PaymentMethod;
  method_label: string;
  amount_cents: number;
  tip_cents: number;
  created_at: string;
}

export interface DataResponse<T> {
  data: T;
}

export interface TablesResponse {
  data: DiningTable[];
  meta: { counts: TableCounts };
}
