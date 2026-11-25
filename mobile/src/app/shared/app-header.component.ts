import { Component, inject, input } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController, IonButton, IonButtons, IonHeader, IonIcon, IonTitle, IonToolbar } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { cloudOfflineOutline, cloudUploadOutline, logOutOutline } from 'ionicons/icons';

import { AuthService } from '../core/auth.service';
import { ConnectivityService } from '../core/connectivity.service';
import { OfflineQueueService } from '../core/offline-queue.service';

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
      @if (queue.pendingCount() > 0 || queue.rejectedCount() > 0) {
        <button type="button" class="queue-banner" (click)="openQueue()" data-testid="queue-banner">
          <ion-icon name="cloud-upload-outline"></ion-icon>
          @if (queue.pendingCount() > 0) {
            {{ queue.pendingCount() === 1 ? '1 comanda pendiente' : queue.pendingCount() + ' comandas pendientes' }} de
            enviar
          }
          @if (queue.rejectedCount() > 0) {
            <span class="rejected">· {{ queue.rejectedCount() }} rechazada(s), revisar</span>
          }
        </button>
      }
    </ion-header>
  `,
  styles: `
    .title {
      font-weight: 700;
      line-height: 1.2;
    }
    .queue-banner {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      min-height: 40px;
      padding: 8px 12px;
      border: 0;
      background: var(--pinguin-amarillo);
      color: var(--pinguin-pizarra);
      font: inherit;
      font-weight: 700;
      font-size: 0.9rem;
    }
    .rejected {
      color: var(--pinguin-rojo);
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
  readonly queue = inject(OfflineQueueService);
  private readonly alerts = inject(AlertController);
  private readonly router = inject(Router);

  constructor() {
    addIcons({ logOutOutline, cloudOfflineOutline, cloudUploadOutline });
  }

  openQueue(): void {
    void this.router.navigateByUrl('/tabs/ordenes');
  }

  async confirmLogout(): Promise<void> {
    const alert = await this.alerts.create({
      header: 'Cerrar sesión',
      message:
        this.queue.pendingCount() > 0
          ? `Tienes ${this.queue.pendingCount()} comanda(s) sin enviar. Se enviarán cuando vuelvas a entrar con tu cuenta. ¿Salir?`
          : '¿Quieres salir de la app?',
      buttons: [
        { text: 'Cancelar', role: 'cancel' },
        { text: 'Salir', role: 'destructive', handler: () => void this.auth.logout() },
      ],
    });
    await alert.present();
  }
}
