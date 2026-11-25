import { centsToPesosString, formatMoney, parsePesos } from '../shared/format';

import type { PaymentInput, PaymentMethod } from './models';
import { newUuid } from './order-draft.service';

/** Un pago de la cuenta (la cuenta se puede dividir en varios). Montos escritos en pesos. */
export interface PaymentLine {
  uid: string;
  method: PaymentMethod;
  amount: string;
  tip: string;
  /** Solo para efectivo: con cuánto paga el cliente, para calcular el cambio. */
  received: string;
}

export interface PaymentSummary {
  paidCents: number;
  tipCents: number;
  remainingCents: number;
  /** Mensaje del primer problema encontrado; vacío si todo está bien. */
  problem: string;
  valid: boolean;
}

export function newPaymentLine(method: PaymentMethod = 'efectivo', amountCents = 0): PaymentLine {
  return {
    uid: newUuid(),
    method,
    amount: amountCents > 0 ? centsToPesosString(amountCents) : '',
    tip: '',
    received: '',
  };
}

/** Cambio a entregar en un pago en efectivo, o null si no aplica o el recibido no alcanza. */
export function changeFor(line: PaymentLine): number | null {
  if (line.method !== 'efectivo' || line.received.trim() === '') return null;
  const received = parsePesos(line.received);
  const amount = parsePesos(line.amount) ?? 0;
  const tip = parsePesos(line.tip || '0') ?? 0;
  if (received === null) return null;
  const change = received - amount - tip;
  return change >= 0 ? change : null;
}

/**
 * Valida los pagos contra el total que calculó el servidor (solo para guiar al mesero;
 * el servidor vuelve a validar la suma exacta).
 */
export function summarizePayments(lines: PaymentLine[], totalCents: number): PaymentSummary {
  let paid = 0;
  let tips = 0;
  let problem = '';

  lines.forEach((line, i) => {
    const label = lines.length > 1 ? ` del pago ${i + 1}` : '';
    const amount = parsePesos(line.amount);
    const tip = line.tip.trim() === '' ? 0 : parsePesos(line.tip);

    if (amount === null || amount <= 0) {
      problem ||= `Escribe un monto válido${label}.`;
    } else {
      paid += amount;
    }
    if (tip === null) {
      problem ||= `La propina${label} no es válida.`;
    } else {
      tips += tip;
    }
    if (line.method === 'efectivo' && line.received.trim() !== '' && amount !== null && changeFor(line) === null) {
      problem ||= `El efectivo recibido${label} no alcanza.`;
    }
  });

  const remaining = totalCents - paid;
  if (!problem && remaining > 0) problem = `Faltan ${formatMoney(remaining)} por cubrir.`;
  if (!problem && remaining < 0) problem = `Los pagos exceden el total por ${formatMoney(-remaining)}.`;

  return {
    paidCents: paid,
    tipCents: tips,
    remainingCents: remaining,
    problem,
    valid: problem === '' && lines.length > 0,
  };
}

/** Convierte las líneas al formato de la API. */
export function toPaymentInputs(lines: PaymentLine[]): PaymentInput[] {
  return lines.map((l) => ({
    method: l.method,
    amount: centsToPesosString(parsePesos(l.amount) ?? 0),
    tip: centsToPesosString(l.tip.trim() === '' ? 0 : (parsePesos(l.tip) ?? 0)),
  }));
}
