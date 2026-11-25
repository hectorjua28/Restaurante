import type { CapacitorConfig } from '@capacitor/cli';

/**
 * PINGUIN_ALLOW_HTTP=1 solo para pruebas en la red local (API en http://IP:8000).
 * En producción la API va por HTTPS y no hace falta.
 */
const allowHttp = process.env['PINGUIN_ALLOW_HTTP'] === '1';

const config: CapacitorConfig = {
  appId: 'mx.antojeriapinguin.meseros',
  appName: 'Pingüin Meseros',
  webDir: 'www',
  server: {
    androidScheme: allowHttp ? 'http' : 'https',
    cleartext: allowHttp,
  },
  android: {
    allowMixedContent: allowHttp,
  },
};

export default config;
