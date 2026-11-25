export const environment = {
  production: true,
  /**
   * URL de la API en producción. Por defecto es relativa porque la PWA se publica en el mismo
   * dominio que Laravel (https://tu-dominio/app). Para el APK de Android se sustituye al compilar
   * con PINGUIN_API_URL (ver DEPLOY.md). También se puede cambiar desde la pantalla de inicio de sesión.
   */
  apiUrl: '/api/v1',
};
