import { Component, computed, inject, signal } from '@angular/core';
import type { OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import {
  AlertController,
  IonButton,
  IonChip,
  IonContent,
  IonFooter,
  IonIcon,
  IonLabel,
  IonSearchbar,
  IonSegment,
  IonSegmentButton,
  IonSpinner,
  ModalController,
  ToastController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import {
  addOutline,
  chatbubbleEllipsesOutline,
  gridOutline,
  removeOutline,
  restaurantOutline,
  sendOutline,
  swapHorizontalOutline,
  trashOutline,
} from 'ionicons/icons';
import { firstValueFrom } from 'rxjs';

import { errorMessage } from '../../core/api-error';
import { ApiService } from '../../core/api.service';
import { CatalogService } from '../../core/catalog.service';
import type { Product } from '../../core/models';
import { OrderDraftService, lineUnitCents } from '../../core/order-draft.service';
import type { DraftLine } from '../../core/order-draft.service';
import { AppHeaderComponent } from '../../shared/app-header.component';
import { MoneyPipe } from '../../shared/pipes';
import { Poller } from '../../shared/polling';

import { ProductOptionsComponent } from './product-options.component';
import type { ProductOptionsResult } from './product-options.component';

type View = 'menu' | 'comanda';

@Component({
  selector: 'app-order',
  templateUrl: './order.page.html',
  styleUrls: ['./order.page.scss'],
  imports: [
    AppHeaderComponent,
    IonContent,
    IonFooter,
    IonSearchbar,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonChip,
    IonButton,
    IonIcon,
    IonSpinner,
    MoneyPipe,
  ],
})
export class OrderPage implements OnDestroy {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);
  private readonly modals = inject(ModalController);
  private readonly alerts = inject(AlertController);
  private readonly toasts = inject(ToastController);
  readonly catalog = inject(CatalogService);
  readonly draft = inject(OrderDraftService);

  readonly view = signal<View>('menu');
  readonly search = signal('');
  readonly categoryId = signal<number | null>(null);
  readonly loadError = signal('');
  readonly sending = signal(false);

  readonly filtered = computed(() => {
    const term = normalize(this.search());
    const cat = this.categoryId();
    return this.catalog
      .products()
      .filter((p) => (cat === null || p.category_id === cat) && (term === '' || normalize(p.name).includes(term)));
  });

  readonly unitCents = lineUnitCents;

  /** Refresca el menú cada 20 s para que los agotados se vean deshabilitados pronto. */
  private readonly poller = new Poller(() => this.loadCatalog(), 20000);

  constructor() {
    addIcons({
      addOutline,
      removeOutline,
      trashOutline,
      sendOutline,
      gridOutline,
      swapHorizontalOutline,
      restaurantOutline,
      chatbubbleEllipsesOutline,
    });
  }

  ionViewDidEnter(): void {
    this.poller.start();
  }

  ionViewWillLeave(): void {
    this.poller.stop();
  }

  ngOnDestroy(): void {
    this.poller.stop();
  }

  async loadCatalog(): Promise<void> {
    try {
      await this.catalog.refresh();
      this.loadError.set('');
    } catch (e) {
      this.loadError.set(errorMessage(e));
    }
  }

  setView(value: unknown): void {
    this.view.set(value === 'comanda' ? 'comanda' : 'menu');
  }

  goToTables(): void {
    void this.router.navigateByUrl('/tabs/mesas');
  }

  async openProduct(product: Product): Promise<void> {
    if (!product.available) return;

    const modal = await this.modals.create({
      component: ProductOptionsComponent,
      componentProps: { product },
    });
    await modal.present();

    const { data, role } = await modal.onWillDismiss<ProductOptionsResult>();
    if (role === 'confirm' && data) {
      this.draft.addProduct(product, data.modifiers, data.note, data.quantity);
      await this.toast(`${data.quantity} × ${product.name} agregado`, 'success', 1200);
    }
  }

  changeQty(line: DraftLine, delta: number): void {
    this.draft.setQuantity(line.uid, line.quantity + delta);
  }

  remove(line: DraftLine): void {
    this.draft.remove(line.uid);
  }

  modifierNames(line: DraftLine): string {
    return line.modifiers.map((m) => m.name).join(', ');
  }

  async confirmSend(): Promise<void> {
    if (this.draft.isEmpty() || this.draft.tableId() === null) return;

    const alert = await this.alerts.create({
      header: 'Enviar a cocina',
      message: `Mesa ${this.draft.tableNumber()} · ${this.draft.itemCount()} platillo(s). ¿Enviar la comanda?`,
      buttons: [
        { text: 'Revisar', role: 'cancel' },
        { text: 'Enviar', role: 'confirm', handler: () => void this.send() },
      ],
    });
    await alert.present();
  }

  async send(): Promise<void> {
    if (this.sending()) return;
    this.sending.set(true);
    try {
      const payload = this.draft.toPayload();
      const order = await firstValueFrom(this.api.createOrder(payload));
      this.draft.clear();
      this.view.set('menu');
      await this.toast(`Comanda #${order.id} enviada a cocina (mesa ${order.table.number}).`, 'success');
      await this.router.navigateByUrl('/tabs/mesas');
    } catch (e) {
      await this.toast(errorMessage(e), 'danger', 4000);
      // Si un platillo se agotó mientras tanto, refrescamos el menú.
      void this.loadCatalog();
    } finally {
      this.sending.set(false);
    }
  }

  private async toast(message: string, color: 'success' | 'danger', duration = 2500): Promise<void> {
    const t = await this.toasts.create({ message, color, duration, position: 'top' });
    await t.present();
  }
}

function normalize(text: string): string {
  return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
}
