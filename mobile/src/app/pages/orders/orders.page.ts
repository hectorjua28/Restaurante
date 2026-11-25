import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import {
  AlertController,
  IonButton,
  IonContent,
  IonIcon,
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
import {
  chatbubbleEllipsesOutline,
  checkmarkDoneOutline,
  closeCircleOutline,
  notificationsOutline,
  receiptOutline,
  walletOutline,
} from 'ionicons/icons';

import { errorMessage } from '../../core/api-error';
import type { Order, OrderStatus } from '../../core/models';
import { MyOrdersService } from '../../core/my-orders.service';
import { OfflineQueueService } from '../../core/offline-queue.service';
import { AppHeaderComponent } from '../../shared/app-header.component';
import { ElapsedPipe, LocalTimePipe, MoneyPipe } from '../../shared/pipes';

type Filter = 'todas' | OrderStatus;

const ORDER: OrderStatus[] = ['lista', 'pendiente', 'en_preparacion', 'entregada'];

@Component({
  selector: 'app-orders',
  templateUrl: './orders.page.html',
  styleUrls: ['./orders.page.scss'],
  imports: [
    AppHeaderComponent,
    IonContent,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonButton,
    IonIcon,
    IonSpinner,
    IonRefresher,
    IonRefresherContent,
    MoneyPipe,
    LocalTimePipe,
    ElapsedPipe,
  ],
})
export class OrdersPage {
  readonly myOrders = inject(MyOrdersService);
  readonly queue = inject(OfflineQueueService);
  private readonly alerts = inject(AlertController);
  private readonly toasts = inject(ToastController);
  private readonly router = inject(Router);

  readonly filter = signal<Filter>('todas');
  readonly busyId = signal<number | null>(null);

  readonly counts = computed(() => {
    const c: Record<OrderStatus, number> = {
      pendiente: 0,
      en_preparacion: 0,
      lista: 0,
      entregada: 0,
      cobrada: 0,
      cancelada: 0,
    };
    for (const o of this.myOrders.orders()) c[o.status]++;
    return c;
  });

  /** Las listas primero (son las que requieren acción), luego por orden de llegada. */
  readonly visible = computed(() => {
    const f = this.filter();
    return this.myOrders
      .orders()
      .filter((o) => f === 'todas' || o.status === f)
      .sort((a, b) => ORDER.indexOf(a.status) - ORDER.indexOf(b.status) || a.created_at.localeCompare(b.created_at));
  });

  constructor() {
    addIcons({
      checkmarkDoneOutline,
      closeCircleOutline,
      notificationsOutline,
      chatbubbleEllipsesOutline,
      walletOutline,
      receiptOutline,
    });
  }

  setFilter(value: unknown): void {
    this.filter.set((value as Filter) ?? 'todas');
  }

  async refresh(event: RefresherCustomEvent): Promise<void> {
    try {
      await this.myOrders.refresh();
    } catch {
      // El aviso de error ya se muestra en la página.
    }
    await event.target.complete();
  }

  modifierNames(names: { name: string }[]): string {
    return names.map((m) => m.name).join(', ');
  }

  async deliver(order: Order): Promise<void> {
    this.busyId.set(order.id);
    try {
      await this.myOrders.deliver(order);
      await this.toast(`Orden #${order.id} entregada en la mesa ${order.table.number}.`, 'success');
    } catch (e) {
      await this.toast(errorMessage(e), 'danger');
      void this.myOrders.refresh().catch(() => undefined);
    } finally {
      this.busyId.set(null);
    }
  }

  async askCancel(order: Order): Promise<void> {
    const alert = await this.alerts.create({
      header: `Cancelar orden #${order.id}`,
      message: `Mesa ${order.table.number}. Escribe el motivo de la cancelación.`,
      inputs: [
        {
          name: 'reason',
          type: 'textarea',
          placeholder: 'Ej. el cliente cambió de opinión',
          attributes: { maxlength: 255 },
        },
      ],
      buttons: [
        { text: 'Volver', role: 'cancel' },
        {
          text: 'Cancelar orden',
          role: 'destructive',
          handler: (data: { reason?: string }) => {
            const reason = (data.reason ?? '').trim();
            if (reason.length < 3) {
              void this.toast('Escribe un motivo de al menos 3 letras.', 'danger');
              return false;
            }
            void this.cancel(order, reason);
            return true;
          },
        },
      ],
    });
    await alert.present();
  }

  async retryQueued(key: string): Promise<void> {
    const sent = await this.queue.retry(key);
    if (sent.length === 0 && this.queue.items().some((q) => q.payload.idempotency_key === key)) {
      const item = this.queue.items().find((q) => q.payload.idempotency_key === key);
      await this.toast(item?.error || 'Todavía no hay conexión. Se volverá a intentar sola.', 'danger');
    }
  }

  async discardQueued(key: string): Promise<void> {
    const alert = await this.alerts.create({
      header: 'Descartar comanda',
      message: 'La comanda no se enviará a cocina. ¿Descartarla?',
      buttons: [
        { text: 'Volver', role: 'cancel' },
        { text: 'Descartar', role: 'destructive', handler: () => void this.queue.discard(key) },
      ],
    });
    await alert.present();
  }

  goToCheckout(order: Order): void {
    void this.router.navigate(['/tabs/cobro'], { queryParams: { mesa: order.table.id } });
  }

  private async cancel(order: Order, reason: string): Promise<void> {
    this.busyId.set(order.id);
    try {
      await this.myOrders.cancel(order, reason);
      await this.toast(`Orden #${order.id} cancelada.`, 'success');
    } catch (e) {
      await this.toast(errorMessage(e), 'danger');
      void this.myOrders.refresh().catch(() => undefined);
    } finally {
      this.busyId.set(null);
    }
  }

  private async toast(message: string, color: 'success' | 'danger'): Promise<void> {
    const t = await this.toasts.create({ message, color, duration: 2500, position: 'top' });
    await t.present();
  }
}
