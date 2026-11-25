import { Component, inject, input } from '@angular/core';
import { AlertController, IonButton, IonButtons, IonHeader, IonIcon, IonTitle, IonToolbar } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { cloudOfflineOutline, logOutOutline } from 'ionicons/icons';

import { AuthService } from '../core/auth.service';
import { ConnectivityService } from '../core/connectivity.service';

/** Encabezado común: logo, título, botón de salir y aviso de "sin conexión". */
@Component({
  selector: 'app-header',
  template: `
    <ion-header>
      <ion-toolbar>
        <img slot="start" class="toolbar-logo" src="assets/logo.png" alt="" />
        <ion-title>
          <div class="title">{{ title() }}</div>
          @if (auth.user(); as user) {
            <div class="subtitle">{{ user.name }}</div>
          }
        </ion-title>
        <ion-buttons slot="end">
          <ion-button color="light" aria-label="Cerrar sesión" (click)="confirmLogout()" data-testid="logout">
            <ion-icon slot="icon-only" name="log-out-outline"></ion-icon>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
      @if (!connectivity.online()) {
        <div class="offline-banner" role="status" data-testid="offline-banner">
          <ion-icon name="cloud-offline-outline"></ion-icon>
          Sin conexión con el servidor
        </div>
      }
    </ion-header>
  `,
  styles: `
    .title {
      font-weight: 700;
      line-height: 1.2;
    }
    .subtitle {
      font-size: 0.75rem;
      font-weight: 400;
      opacity: 0.75;
    }
  `,
  imports: [IonHeader, IonToolbar, IonTitle, IonButtons, IonButton, IonIcon],
})
export class AppHeaderComponent {
  readonly title = input.required<string>();
  readonly auth = inject(AuthService);
  readonly connectivity = inject(ConnectivityService);
  private readonly alerts = inject(AlertController);

  constructor() {
    addIcons({ logOutOutline, cloudOfflineOutline });
  }

  async confirmLogout(): Promise<void> {
    const alert = await this.alerts.create({
      header: 'Cerrar sesión',
      message: '¿Quieres salir de la app?',
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        { text: 'Salir', role: 'destructive', handler: () => void this.auth.logout() },
      ],
    });
    await alert.present();
  }
}
