import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  AlertController,
  IonButton,
  IonContent,
  IonFooter,
  IonIcon,
  IonInput,
  IonLabel,
  IonRefresher,
  IonRefresherContent,
  IonSegment,
  IonSegmentButton,
  IonSpinner,
  ToastController,
} from '@ionic/angular';
import type { RefresherCustomEvent } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { addOutline, arrowBackOutline, cashOutline, trashOutline, walletOutline } from 'ionicons/icons';
import { firstValueFrom } from 'rxjs';

import { errorMessage } from '../../core/api-error';
import { ApiService } from '../../core/api.service';
import { changeFor, newPaymentLine, summarizePayments, toPaymentInputs } from '../../core/checkout';
import type { PaymentLine } from '../../core/checkout';
import type { Bill, DiningTable, PaymentMethod } from '../../core/models';
import { MyOrdersService } from '../../core/my-orders.service';
import { AppHeaderComponent } from '../../shared/app-header.component';
import { formatMoney, parsePesos } from '../../shared/format';
import { LocalTimePipe, MoneyPipe } from '../../shared/pipes';

const METHOD_LABELS: Record<PaymentMethod, string> = {
  efectivo: 'Efectivo',
  tarjeta: 'Tarjeta',
  transferencia: 'Transferencia',
};

@Component({
  selector: 'app-checkout',
  templateUrl: './checkout.page.html',
  styleUrls: ['./checkout.page.scss'],
  imports: [
    FormsModule,
    AppHeaderComponent,
    IonContent,
    IonFooter,
    IonButton,
    IonIcon,
    IonInput,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonSpinner,
    IonRefresher,
    IonRefresherContent,
    MoneyPipe,
    LocalTimePipe,
  ],
})
export class CheckoutPage {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);
  private readonly alerts = inject(AlertController);
  private readonly toasts = inject(ToastController);
  private readonly myOrders = inject(MyOrdersService);

  /** ?mesa=ID para abrir directamente la cuenta de una mesa. */
  readonly mesa = input<string | undefined>();

  readonly tables = signal<DiningTable[]>([]);
  readonly tablesLoaded = signal(false);
  readonly tablesError = signal('');

  readonly selectedId = signal<number | null>(null);
  readonly bill = signal<Bill | null>(null);
  readonly billError = signal('');
  readonly loadingBill = signal(false);
  readonly charging = signal(false);

  readonly lines = signal<PaymentLine[]>([]);
  readonly methods = Object.entries(METHOD_LABELS) as [PaymentMethod, string][];
  readonly change = changeFor;

  readonly summary = computed(() => summarizePayments(this.lines(), this.bill()?.total_cents ?? 0));

  constructor() {
    addIcons({ walletOutline, arrowBackOutline, addOutline, trashOutline, cashOutline });

    effect(() => {
      const id = Number(this.mesa());
      if (Number.isInteger(id) && id > 0 && id !== this.selectedId()) {
        void this.select(id);
      }
    });
  }

  ionViewWillEnter(): void {
    void this.loadTables();
    if (this.selectedId() !== null) {
      void this.loadBill();
    }
  }

  async loadTables(): Promise<void> {
    try {
      const res = await firstValueFrom(this.api.getTables());
      this.tables.set(res.data.filter((t) => t.status !== 'libre'));
      this.tablesError.set('');
    } catch (e) {
      this.tablesError.set(errorMessage(e));
    } finally {
      this.tablesLoaded.set(true);
    }
  }

  async refresh(event: RefresherCustomEvent): Promise<void> {
    await (this.selectedId() === null ? this.loadTables() : this.loadBill());
    await event.target.complete();
  }

  async select(tableId: number): Promise<void> {
    this.selectedId.set(tableId);
    this.bill.set(null);
    this.lines.set([]);
    await this.loadBill();
  }

  back(): void {
    this.selectedId.set(null);
    this.bill.set(null);
    this.lines.set([]);
    void this.router.navigate(['/tabs/cobro'], { replaceUrl: true });
    void this.loadTables();
  }

  async loadBill(): Promise<void> {
    const id = this.selectedId();
    if (id === null) return;
    this.loadingBill.set(true);
    try {
      const bill = await firstValueFrom(this.api.getBill(id));
      const totalChanged = this.bill()?.total_cents !== bill.total_cents;
      this.bill.set(bill);
      this.billError.set('');
      // Si el total cambió (o es la primera carga), se propone un solo pago en efectivo por el total.
      if (totalChanged || this.lines().length === 0) {
        this.lines.set([newPaymentLine('efectivo', bill.total_cents)]);
      }
    } catch (e) {
      this.billError.set(errorMessage(e));
    } finally {
      this.loadingBill.set(false);
    }
  }

  methodLabel(method: PaymentMethod): string {
    return METHOD_LABELS[method];
  }

  updateLine(uid: string, patch: Partial<PaymentLine>): void {
    this.lines.update((list) => list.map((l) => (l.uid === uid ? { ...l, ...patch } : l)));
  }

  setMethod(uid: string, value: unknown): void {
    const method = (['efectivo', 'tarjeta', 'transferencia'] as const).find((m) => m === value) ?? 'efectivo';
    this.updateLine(uid, { method, received: '' });
  }

  addLine(): void {
    const remaining = Math.max(0, this.summary().remainingCents);
    this.lines.update((list) => [...list, newPaymentLine('tarjeta', remaining)]);
  }

  removeLine(uid: string): void {
    this.lines.update((list) => (list.length > 1 ? list.filter((l) => l.uid !== uid) : list));
  }

  async confirmCharge(): Promise<void> {
    const bill = this.bill();
    const summary = this.summary();
    if (!bill || !bill.can_charge || !summary.valid) return;

    const detail = this.lines()
      .map((l) => `${METHOD_LABELS[l.method]}: ${formatMoney(parsePesos(l.amount))}`)
      .join(' · ');
    const tip = summary.tipCents > 0 ? ` Propina: ${formatMoney(summary.tipCents)}.` : '';

    const alert = await this.alerts.create({
      header: `Cobrar mesa ${bill.table.number}`,
      message: `Total ${formatMoney(bill.total_cents)}. ${detail}.${tip}`,
      buttons: [
        { text: 'Revisar', role: 'cancel' },
        { text: 'Cobrar', role: 'confirm', handler: () => void this.charge() },
      ],
    });
    await alert.present();
  }

  private async charge(): Promise<void> {
    const bill = this.bill();
    if (!bill || this.charging()) return;
    this.charging.set(true);
    try {
      await firstValueFrom(this.api.chargeTable(bill.table.id, toPaymentInputs(this.lines())));
      await this.toast(`Mesa ${bill.table.number} cobrada. ¡Gracias!`, 'success');
      void this.myOrders.refresh().catch(() => undefined);
      this.back();
    } catch (e) {
      await this.toast(errorMessage(e), 'danger');
      void this.loadBill();
    } finally {
      this.charging.set(false);
    }
  }

  private async toast(message: string, color: 'success' | 'danger'): Promise<void> {
    const t = await this.toasts.create({ message, color, duration: 3000, position: 'top' });
    await t.present();
  }
}
