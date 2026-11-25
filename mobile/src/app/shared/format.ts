/** Formato de dinero y fechas para es-MX. Nunca devuelve "NaN", "undefined" ni "null". */

export const TIME_ZONE = 'America/Mexico_City';

const currency = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

export function formatMoney(cents: number | null | undefined): string {
  const value = typeof cents === 'number' && Number.isFinite(cents) ? cents : 0;
  // Se divide solo para mostrar; los cálculos se hacen siempre en centavos enteros.
  return currency.format(value / 100);
}

/** "120.5" → 12050. Devuelve null si no es un monto válido (máx. 2 decimales). */
export function parsePesos(text: string | null | undefined): number | null {
  const clean = (text ?? '').trim().replace(/[$,\s]/g, '');
  if (!/^\d{1,7}(\.\d{1,2})?$/.test(clean)) {
    return null;
  }
  const [int, dec = ''] = clean.split('.');
  return Number(int) * 100 + Number(dec.padEnd(2, '0'));
}

/** 12050 → "120.50" (formato que espera la API). */
export function centsToPesosString(cents: number): string {
  const sign = cents < 0 ? '-' : '';
  const abs = Math.abs(Math.trunc(cents));
  return `${sign}${Math.floor(abs / 100)}.${String(abs % 100).padStart(2, '0')}`;
}

function toDate(iso: string | null | undefined): Date | null {
  if (!iso) return null;
  const date = new Date(iso);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatTime(iso: string | null | undefined): string {
  const date = toDate(iso);
  if (!date) return '—';
  return new Intl.DateTimeFormat('es-MX', {
    timeZone: TIME_ZONE,
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
}

export function formatDateTime(iso: string | null | undefined): string {
  const date = toDate(iso);
  if (!date) return '—';
  return new Intl.DateTimeFormat('es-MX', {
    timeZone: TIME_ZONE,
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
}

/** Tiempo transcurrido legible: "hace 3 min", "hace 1 h 05 min". */
export function formatElapsed(iso: string | null | undefined, now: number = Date.now()): string {
  const date = toDate(iso);
  if (!date) return '—';
  const minutes = Math.max(0, Math.floor((now - date.getTime()) / 60000));
  if (minutes < 1) return 'hace menos de 1 min';
  if (minutes < 60) return `hace ${minutes} min`;
  const h = Math.floor(minutes / 60);
  return `hace ${h} h ${String(minutes % 60).padStart(2, '0')} min`;
}
