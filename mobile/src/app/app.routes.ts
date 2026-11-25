import type { Routes } from '@angular/router';

import { authGuard, guestGuard } from './core/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'tabs',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/tabs/tabs.page').then((m) => m.TabsPage),
    children: [
      {
        path: 'mesas',
        loadComponent: () => import('./pages/tables/tables.page').then((m) => m.TablesPage),
      },
      {
        path: 'comanda',
        loadComponent: () => import('./pages/order/order.page').then((m) => m.OrderPage),
      },
      {
        path: 'ordenes',
        loadComponent: () => import('./pages/orders/orders.page').then((m) => m.OrdersPage),
      },
      {
        path: 'cobro',
        loadComponent: () => import('./pages/checkout/checkout.page').then((m) => m.CheckoutPage),
      },
      { path: '', redirectTo: 'mesas', pathMatch: 'full' },
    ],
  },
  { path: '', redirectTo: 'tabs/mesas', pathMatch: 'full' },
  { path: '**', redirectTo: 'tabs/mesas' },
];
