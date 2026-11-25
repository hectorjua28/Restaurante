import { centsToPesosString, formatElapsed, formatMoney, formatTime, parsePesos } from './format';

describe('format', () => {
  it('formatea centavos como MXN', () => {
    expect(formatMoney(0)).toBe('$0.00');
    expect(formatMoney(4550)).toBe('$45.50');
    expect(formatMoney(123456789)).toBe('$1,234,567.89');
  });

  it('nunca muestra NaN, undefined ni null', () => {
    for (const bad of [null, undefined, Number.NaN, Infinity] as unknown as number[]) {
      const text = formatMoney(bad);
      expect(text).toBe('$0.00');
    }
    expect(formatTime(undefined)).toBe('—');
    expect(formatTime('no-es-fecha')).toBe('—');
    expect(formatElapsed(null)).toBe('—');
  });

  it('convierte pesos escritos a centavos sin decimales flotantes', () => {
    expect(parsePesos('120')).toBe(12000);
    expect(parsePesos('120.5')).toBe(12050);
    expect(parsePesos('0.10')).toBe(10);
    expect(parsePesos('$1,250.75')).toBe(125075);
    expect(parsePesos('19.99')).toBe(1999);
    expect(parsePesos('1.999')).toBeNull();
    expect(parsePesos('abc')).toBeNull();
    expect(parsePesos('')).toBeNull();
    expect(parsePesos('-5')).toBeNull();
  });

  it('convierte centavos al formato que espera la API', () => {
    expect(centsToPesosString(12050)).toBe('120.50');
    expect(centsToPesosString(5)).toBe('0.05');
    expect(centsToPesosString(0)).toBe('0.00');
  });

  it('muestra la hora en America/Mexico_City aunque la API mande UTC', () => {
    // 18:30 UTC = 12:30 en Ciudad de México (UTC-6, sin horario de verano).
    expect(formatTime('2026-11-24T18:30:00Z')).toBe('12:30');
  });

  it('calcula el tiempo transcurrido', () => {
    const now = Date.parse('2026-11-24T18:30:00Z');
    expect(formatElapsed('2026-11-24T18:29:40Z', now)).toBe('hace menos de 1 min');
    expect(formatElapsed('2026-11-24T18:17:00Z', now)).toBe('hace 13 min');
    expect(formatElapsed('2026-11-24T17:05:00Z', now)).toBe('hace 1 h 25 min');
  });
});
