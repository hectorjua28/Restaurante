import { Component, Input, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  IonButton,
  IonButtons,
  IonCheckbox,
  IonContent,
  IonFooter,
  IonHeader,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonListHeader,
  IonTextarea,
  IonTitle,
  IonToolbar,
  ModalController,
} from '@ionic/angular';
import { addIcons } from 'ionicons';
import { addOutline, closeOutline, removeOutline } from 'ionicons/icons';

import type { Modifier, Product } from '../../core/models';
import { MAX_QUANTITY } from '../../core/order-draft.service';
import { MoneyPipe } from '../../shared/pipes';

export interface ProductOptionsResult {
  modifiers: Modifier[];
  note: string;
  quantity: number;
}

/** Modal para elegir modificadores, cantidad y nota de un platillo. */
@Component({
  selector: 'app-product-options',
  template: `
    <ion-header>
      <ion-toolbar>
        <ion-title>{{ product.name }}</ion-title>
        <ion-buttons slot="end">
          <ion-button color="light" aria-label="Cerrar" (click)="close()">
            <ion-icon slot="icon-only" name="close-outline"></ion-icon>
          </ion-button>
        </ion-buttons>
      </ion-toolbar>
    </ion-header>

    <ion-content class="ion-padding">
      @if (product.description) {
        <p class="muted">{{ product.description }}</p>
      }
      <p class="base">
        Precio: <strong>{{ product.price_cents | money }}</strong>
      </p>

      @if (product.modifiers.length > 0) {
        <ion-list lines="full" class="card">
          <ion-list-header><ion-label>Modificadores</ion-label></ion-list-header>
          @for (m of product.modifiers; track m.id) {
            <ion-item>
              <ion-checkbox
                justify="space-between"
                [checked]="isSelected(m.id)"
                (ionChange)="toggle(m)"
                [attr.data-testid]="'modifier-' + m.id"
              >
                {{ m.name }}
                @if (m.price_delta_cents !== 0) {
                  <span class="delta">+{{ m.price_delta_cents | money }}</span>
                }
              </ion-checkbox>
            </ion-item>
          }
        </ion-list>
      }

      <div class="qty card">
        <span>Cantidad</span>
        <div class="stepper">
          <ion-button
            fill="outline"
            color="secondary"
            aria-label="Menos"
            [disabled]="quantity() <= 1"
            (click)="changeQty(-1)"
          >
            <ion-icon slot="icon-only" name="remove-outline"></ion-icon>
          </ion-button>
          <strong data-testid="option-qty">{{ quantity() }}</strong>
          <ion-button
            fill="outline"
            color="secondary"
            aria-label="Más"
            [disabled]="quantity() >= max"
            (click)="changeQty(1)"
          >
            <ion-icon slot="icon-only" name="add-outline"></ion-icon>
          </ion-button>
        </div>
      </div>

      <ion-textarea
        label="Nota para cocina (opcional)"
        labelPlacement="stacked"
        fill="outline"
        placeholder="Ej. sin picante, bien dorado…"
        [maxlength]="255"
        [counter]="true"
        [autoGrow]="true"
        [(ngModel)]="note"
        data-testid="option-note"
      ></ion-textarea>
    </ion-content>

    <ion-footer class="ion-padding">
      <ion-button expand="block" class="button-large" (click)="confirm()" data-testid="option-add">
        Agregar · {{ total() | money }}
      </ion-button>
    </ion-footer>
  `,
  styles: `
    .base {
      margin: 0 0 12px;
    }
    .delta {
      margin-left: 6px;
      color: rgba(46, 59, 68, 0.7);
      font-size: 0.9rem;
    }
    ion-list {
      margin-bottom: 12px;
      overflow: hidden;
    }
    .qty {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 12px;
      margin-bottom: 14px;
    }
    .stepper {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .stepper strong {
      min-width: 28px;
      text-align: center;
      font-size: 1.25rem;
    }
    ion-footer {
      background: #fff;
    }
  `,
  imports: [
    FormsModule,
    IonHeader,
    IonToolbar,
    IonTitle,
    IonButtons,
    IonButton,
    IonIcon,
    IonContent,
    IonList,
    IonListHeader,
    IonItem,
    IonLabel,
    IonCheckbox,
    IonTextarea,
    IonFooter,
    MoneyPipe,
  ],
})
export class ProductOptionsComponent {
  private readonly modalCtrl = inject(ModalController);

  /**
   * Ionic asigna las props del modal directamente a la propiedad, por eso se usa un setter
   * (una señal input() quedaría sobrescrita).
   */
  private readonly productSig = signal<Product | null>(null);
  @Input({ required: true })
  set product(value: Product) {
    this.productSig.set(value);
  }
  get product(): Product {
    const p = this.productSig();
    if (!p) throw new Error('Falta el platillo.');
    return p;
  }
  readonly max = MAX_QUANTITY;
  readonly quantity = signal(1);
  readonly selected = signal<Modifier[]>([]);
  note = '';

  readonly total = computed(
    () =>
      ((this.productSig()?.price_cents ?? 0) + this.selected().reduce((s, m) => s + m.price_delta_cents, 0)) *
      this.quantity(),
  );

  constructor() {
    addIcons({ closeOutline, addOutline, removeOutline });
  }

  isSelected(id: number): boolean {
    return this.selected().some((m) => m.id === id);
  }

  toggle(modifier: Modifier): void {
    this.selected.update((list) =>
      list.some((m) => m.id === modifier.id) ? list.filter((m) => m.id !== modifier.id) : [...list, modifier],
    );
  }

  changeQty(delta: number): void {
    this.quantity.update((q) => Math.min(this.max, Math.max(1, q + delta)));
  }

  close(): void {
    void this.modalCtrl.dismiss(null, 'cancel');
  }

  confirm(): void {
    const result: ProductOptionsResult = { modifiers: this.selected(), note: this.note, quantity: this.quantity() };
    void this.modalCtrl.dismiss(result, 'confirm');
  }
}
