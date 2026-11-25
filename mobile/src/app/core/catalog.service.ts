import { Injectable, inject, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { ApiService } from './api.service';
import type { Category, Product } from './models';
import { StorageService } from './storage.service';

interface CatalogCache {
  categories: Category[];
  products: Product[];
}

/**
 * Menú (categorías y platillos). Se guarda una copia en el teléfono para poder
 * armar comandas aunque se pierda la señal.
 */
@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly api = inject(ApiService);
  private readonly storage = inject(StorageService);
  private readonly cache = this.storage.get<CatalogCache>('catalog', { categories: [], products: [] });

  readonly categories = signal<Category[]>(this.cache.categories);
  readonly products = signal<Product[]>(this.cache.products);

  async refresh(): Promise<void> {
    const [categories, products] = await Promise.all([
      firstValueFrom(this.api.getCategories()),
      firstValueFrom(this.api.getProducts()),
    ]);
    this.categories.set(categories);
    this.products.set(products);
    this.storage.set('catalog', { categories, products } satisfies CatalogCache);
  }

  hasData(): boolean {
    return this.products().length > 0;
  }
}
