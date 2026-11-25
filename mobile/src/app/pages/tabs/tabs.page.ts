import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { Haptics, NotificationType } from '@capacitor/haptics';
import { IonBadge, IonIcon, IonLabel, IonTabBar, IonTabButton, IonTabs, ToastController } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { gridOutline, receiptOutline, restaurantOutline, walletOutline } from 'ionicons/icons';

import type { Order } from '../../core/models';
import { MyOrdersService } from '../../core/my-orders.service';
import { OfflineQueueService } from '../../core/offline-queue.service';
import { OrderDraftService } from '../../core/order-draft.service';

@Component({
  selector: 'app-tabs',
  template: `
    <ion-tabs>
      <ion-tab-bar slot="bottom">
        <ion-tab-button tab="mesas" href="/tabs/mesas" data-testid="tab-mesas">
          <ion-icon aria-hidden="true" name="grid-outline"></ion-icon>
          <ion-label>Mesas</ion-label>
        </ion-tab-button>

        <ion-tab-button tab="comanda" href="/tabs/comanda" data-testid="tab-comanda">
          <ion-icon aria-hidden="true" name="restaurant-outline"></ion-icon>
          <ion-label>Comanda</ion-label>
          @if (draft.itemCount() > 0) {
            <ion-badge color="primary">{{ draft.itemCount() }}</ion-badge>
          }
        </ion-tab-button>

        <ion-tab-button tab="ordenes" href="/tabs/ordenes" data-testid="tab-ordenes">
          <ion-icon aria-hidden="true" name="receipt-outline"></ion-icon>
          <ion-label>Órdenes</ion-label>
          @if (myOrders.readyCount() > 0) {
            <ion-badge color="success" data-testid="ready-badge">{{ myOrders.readyCount() }}</ion-badge>
          }
        </ion-tab-button>

        <ion-tab-button tab="cobro" href="/tabs/cobro" data-testid="tab-cobro">
          <ion-icon aria-hidden="true" name="wallet-outline"></ion-icon>
          <ion-label>Cobro</ion-label>
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>
  `,
  styles: `
    ion-tab-button {
      min-height: 56px;
      font-size: 0.8rem;
    }
  `,
  imports: [IonTabs, IonTabBar, IonTabButton, IonIcon, IonLabel, IonBadge],
})
export class TabsPage {
  readonly draft = inject(OrderDraftService);
  readonly myOrders = inject(MyOrdersService);
  private readonly toasts = inject(ToastController);
  private readonly router = inject(Router);
  private readonly queue = inject(OfflineQueueService);

  constructor() {
    addIcons({ gridOutline, restaurantOutline, receiptOutline, walletOutline });

    // Las órdenes del mesero se vigilan en todas las pestañas para avisar cuando una esté lista.
    this.myOrders.start();
    // Bandeja de comandas guardadas sin conexión: se envían solas al volver la señal.
    void this.queue.start();
    inject(DestroyRef).onDestroy(() => {
      this.myOrders.stop();
      this.queue.stop();
    });

    this.myOrders.becameReady$.pipe(takeUntilDestroyed()).subscribe((order) => void this.notifyReady(order));
    this.queue.sent$.pipe(takeUntilDestroyed()).subscribe((order) => void this.notifySent(order));
  }

  private async notifySent(order: Order): Promise<void> {
    void this.myOrders.refresh().catch(() => undefined);
    const toast = await this.toasts.create({
      message: `Se envió la comanda guardada de la mesa ${order.table.number} (#${order.id}).`,
      color: 'success',
      position: 'top',
      duration: 3500,
    });
    await toast.present();
  }

  private async notifyReady(order: Order): Promise<void> {
    try {
      await Haptics.notification({ type: NotificationType.Success });
    } catch {
      // En navegadores sin vibración no pasa nada.
    }
    const toast = await this.toasts.create({
      message: `¡Orden #${order.id} de la mesa ${order.table.number} lista para entregar!`,
      color: 'success',
      position: 'top',
      duration: 5000,
      buttons: [{ text: 'Ver', handler: () => void this.router.navigateByUrl('/tabs/ordenes') }],
    });
    await toast.present();
  }
}
