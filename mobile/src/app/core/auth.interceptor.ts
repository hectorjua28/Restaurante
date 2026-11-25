import type { HttpInterceptorFn } from '@angular/common/http';
import { HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, tap, throwError } from 'rxjs';

import { ApiConfigService } from './api-config.service';
import { AuthService } from './auth.service';
import { ConnectivityService } from './connectivity.service';

/**
 * Agrega el token Bearer a las peticiones de la API y, si el servidor responde 401,
 * cierra la sesión local y manda al login. También informa si hay conexión con el servidor.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const config = inject(ApiConfigService);
  const auth = inject(AuthService);
  const router = inject(Router);
  const connectivity = inject(ConnectivityService);

  if (!config.isApiRequest(req.url)) {
    return next(req);
  }

  const token = auth.token();
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token && !req.headers.has('Authorization')) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  return next(req.clone({ setHeaders: headers })).pipe(
    tap(() => connectivity.reportSuccess()),
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 0) {
          connectivity.reportNetworkFailure();
        } else {
          connectivity.reportSuccess();
        }

        const isLogin = req.url.endsWith('/auth/login');
        if (error.status === 401 && !isLogin) {
          auth.clearSession();
          void router.navigate(['/login'], { replaceUrl: true, queryParams: { expired: 1 } });
        }
      }
      return throwError(() => error);
    }),
  );
};
