import { Pipe } from '@angular/core';
import type { PipeTransform } from '@angular/core';

import { formatElapsed, formatMoney, formatTime } from './format';

@Pipe({ name: 'money' })
export class MoneyPipe implements PipeTransform {
  transform(cents: number | null | undefined): string {
    return formatMoney(cents);
  }
}

@Pipe({ name: 'localTime' })
export class LocalTimePipe implements PipeTransform {
  transform(iso: string | null | undefined): string {
    return formatTime(iso);
  }
}

/** Impuro a propósito: se reevalúa con cada detección de cambios (el reloj avanza). */
@Pipe({ name: 'elapsed', pure: false })
export class ElapsedPipe implements PipeTransform {
  transform(iso: string | null | undefined, now?: number): string {
    return formatElapsed(iso, now);
  }
}
