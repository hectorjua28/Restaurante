import { changeFor, newPaymentLine, summarizePayments, toPaymentInputs } from './checkout';
import type { PaymentLine } from './checkout';

const line = (patch: Partial<PaymentLine>): PaymentLine => ({ ...newPaymentLine(), ...patch });

describe('cobro: validación de pagos', () => {
  it('propone un pago en efectivo por el total', () => {
    const l = newPaymentLine('efectivo', 12050);
    expect(l.method).toBe('efectivo');
    expect(l.amount).toBe('120.50');
    expect(summarizePayments([l], 12050).valid).toBe(true);
  });

  it('acepta la cuenta dividida si suma exactamente el total', () => {
    const lines = [
      line({ method: 'efectivo', amount: '50' }),
      line({ method: 'tarjeta', amount: '40.50', tip: '10' }),
      line({ method: 'transferencia', amount: '29.50' }),
    ];
    const s = summarizePayments(lines, 12000);

    expect(s.valid).toBe(true);
    expect(s.paidCents).toBe(12000);
    expect(s.tipCents).toBe(1000);
    expect(s.remainingCents).toBe(0);
  });

  it('indica cuánto falta o cuánto sobra', () => {
    expect(summarizePayments([line({ amount: '100' })], 12000).problem).toBe('Faltan $20.00 por cubrir.');
    expect(summarizePayments([line({ amount: '130' })], 12000).problem).toBe('Los pagos exceden el total por $10.00.');
  });

  it('rechaza montos y propinas inválidos', () => {
    expect(summarizePayments([line({ amount: '' })], 100).problem).toBe('Escribe un monto válido.');
    expect(summarizePayments([line({ amount: 'abc' })], 100).valid).toBe(false);
    expect(summarizePayments([line({ amount: '1', tip: '1.234' })], 100).problem).toBe('La propina no es válida.');
    expect(summarizePayments([line({ amount: '0.5' }), line({ amount: '0' })], 100).problem).toBe(
      'Escribe un monto válido del pago 2.',
    );
  });

  it('calcula el cambio en efectivo incluyendo la propina', () => {
    expect(changeFor(line({ method: 'efectivo', amount: '120', tip: '15', received: '200' }))).toBe(6500);
    expect(changeFor(line({ method: 'efectivo', amount: '120', received: '100' }))).toBeNull();
    expect(changeFor(line({ method: 'tarjeta', amount: '120', received: '200' }))).toBeNull();
    expect(summarizePayments([line({ method: 'efectivo', amount: '120', received: '100' })], 12000).problem).toBe(
      'El efectivo recibido no alcanza.',
    );
  });

  it('convierte los pagos al formato de la API', () => {
    expect(toPaymentInputs([line({ method: 'tarjeta', amount: '40.5', tip: '' })])).toEqual([
      { method: 'tarjeta', amount: '40.50', tip: '0.00' },
    ]);
  });
});
