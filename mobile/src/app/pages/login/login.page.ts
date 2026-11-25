import { Component, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonButton, IonContent, IonIcon, IonInput, IonInputPasswordToggle, IonNote, IonSpinner } from '@ionic/angular';
import { addIcons } from 'ionicons';
import { serverOutline } from 'ionicons/icons';

import { ApiConfigService } from '../../core/api-config.service';
import { errorMessage } from '../../core/api-error';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
  imports: [FormsModule, IonContent, IonInput, IonInputPasswordToggle, IonButton, IonSpinner, IonNote, IonIcon],
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  readonly config = inject(ApiConfigService);

  /** ?expired=1 cuando el servidor respondió 401. */
  readonly expired = input<string | undefined>();

  email = '';
  password = '';
  serverUrl = this.config.needsServer() ? '' : this.config.baseUrl();
  readonly loading = signal(false);
  readonly error = signal('');
  readonly showServer = signal(this.config.isCustom() || this.config.needsServer());

  constructor() {
    addIcons({ serverOutline });
  }

  async submit(): Promise<void> {
    this.error.set('');
    if (this.email.trim() === '' || this.password === '') {
      this.error.set('Escribe tu correo y tu contraseña.');
      return;
    }

    if (this.showServer() || this.config.needsServer()) {
      const problem = this.config.setBaseUrl(this.serverUrl);
      if (problem) {
        this.error.set(problem);
        this.showServer.set(true);
        return;
      }
      this.serverUrl = this.config.baseUrl();
    }

    this.loading.set(true);
    try {
      await this.auth.login(this.email, this.password);
      this.password = '';
      await this.router.navigateByUrl('/tabs/mesas', { replaceUrl: true });
    } catch (e) {
      this.error.set(errorMessage(e));
    } finally {
      this.loading.set(false);
    }
  }

  toggleServer(): void {
    if (this.config.needsServer()) return;
    this.showServer.update((v) => !v);
    this.serverUrl = this.config.baseUrl();
  }
}
