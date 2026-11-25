import { HttpErrorResponse } from '@angular/common/http';

/** Convierte cualquier error en un mensaje claro en español (nunca "undefined" ni "null"). */
export function errorMessage(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    if (error.status === 0) {
      return 'Sin conexión con el servidor. Revisa tu señal e inténtalo de nuevo.';
    }

    const body = error.error as { message?: unknown; errors?: Record<string, unknown> } | null;
    const firstValidation = body?.errors ? Object.values(body.errors)[0] : undefined;
    if (Array.isArray(firstValidation) && typeof firstValidation[0] === 'string') {
      return firstValidation[0];
    }
    if (typeof body?.message === 'string' && body.message.trim() !== '') {
      return body.message;
    }

    switch (error.status) {
      case 401:
        return 'Tu sesión expiró. Inicia sesión de nuevo.';
      case 403:
        return 'No tienes permiso para realizar esta acción.';
      case 404:
        return 'No se encontró la información solicitada.';
      case 429:
        return 'Demasiados intentos. Espera un momento.';
      default:
        return `Ocurrió un error en el servidor (código ${error.status}).`;
    }
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return 'Ocurrió un error inesperado.';
}

/** true si el error fue por falta de red (no llegó respuesta del servidor). */
export function isNetworkError(error: unknown): boolean {
  return error instanceof HttpErrorResponse && error.status === 0;
}
