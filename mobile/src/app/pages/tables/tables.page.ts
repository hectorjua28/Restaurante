import { Component, computed, inject, signal } from '@angular/core';
import type { OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import {
  AlertController,
  IonContent,
  IonIcon,
  IonLabel,
  IonRefresher,
  IonRefresherContent,
  IonSegment,
  IonSegmentButton,
  IonSkeletonText,
  IonButton,
} from '@ionic/angular';
import type { RefresherCustomEvent } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { peopleOutline, refreshOutline, restaurantOutline } from 'ionicons/icons';
import { firstValueFrom } from 'rxjs';

import { errorMessage } from '../../core/api-error';
import { ApiService } from '../../core/api.service';
import type { DiningTable, TableCounts, TableStatus } from '../../core/models';
import { OrderDraftService } from '../../core/order-draft.service';
import { AppHeaderComponent } from '../../shared/app-header.component';
import { MoneyPipe } from '../../shared/pipes';
import { Poller } from '../../shared/polling';

type Filter = 'todas' | TableStatus;

@Component({
  selector: 'app-tables',
  templateUrl: './tables.page.html',
  styleUrls: ['./tables.page.scss'],
  imports: [
    AppHeaderComponent,
    IonContent,
    IonSegment,
    IonSegmentButton,
    IonLabel,
    IonIcon,
    IonRefresher,
    IonRefresherContent,
    IonSkeletonText,
    IonButton,
    MoneyPipe,
  ],
})
export class TablesPage implements OnDestroy {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);
  private readonly alerts = inject(AlertController);
  readonly draft = inject(OrderDraftService);

  readonly tables = signal<DiningTable[]>([]);
  readonly counts = signal<TableCounts>({ total: 0, libre: 0, ocupada: 0, por_cobrar: 0 });
  readonly filter = signal<Filter>('todas');
  readonly loaded = signal(false);
  readonly error = signal('');

  readonly visible = computed(() => {
    const f = this.filter();
    return f === 'todas' ? this.tables() : this.tables().filter((t) => t.status === f);
  });

  private readonly poller = new Poller(() => this.load(), 5000);

  constructor() {
    addIcons({ peopleOutline, restaurantOutline, refreshOutline });
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

  async load(): Promise<void> {
    try {
      const response = await firstValueFrom(this.api.getTables());
      this.tables.set(response.data);
      this.counts.set(response.meta.counts);
      this.error.set('');
    } catch (e) {
      this.error.set(errorMessage(e));
    } finally {
      this.loaded.set(true);
    }
  }

  async refresh(event: RefresherCustomEvent): Promise<void> {
    await this.load();
    await event.target.complete();
  }

  setFilter(value: unknown): void {
    this.filter.set((value as Filter) ?? 'todas');
  }

  async choose(table: DiningTable): Promise<void> {
    const currentId = this.draft.tableId();
    if (currentId !== null && currentId !== table.id && !this.draft.isEmpty()) {
      const alert = await this.alerts.create({
        header: 'Comanda en curso',
        message: `Tienes ${this.draft.itemCount()} platillo(s) para la mesa ${this.draft.tableNumber()}. ¿Quieres pasarlos a la mesa ${table.number}?`,
        buttons: [
          { text: 'Cancelar', role: 'cancel' },
          {
            text: 'Pasar a esta mesa',
            handler: () => {
              this.draft.selectTable(table.id, table.number);
              void this.router.navigateByUrl('/tabs/comanda');
            },
          },
        ],
      });
      await alert.present();
      return;
    }

    this.draft.selectTable(table.id, table.number);
    await this.router.navigateByUrl('/tabs/comanda');
  }
}
