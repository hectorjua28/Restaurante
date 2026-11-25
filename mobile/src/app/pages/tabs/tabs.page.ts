import { Component, inject } from '@angular/core';
import { IonBadge, IonIcon, IonLabel, IonTabBar, IonTabButton, IonTabs } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { gridOutline, restaurantOutline } from 'ionicons/icons';

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

  constructor() {
    addIcons({ gridOutline, restaurantOutline });
  }
}
