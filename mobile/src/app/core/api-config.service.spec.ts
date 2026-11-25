import { TestBed } from '@angular/core/testing';

import { ApiConfigService } from './api-config.service';

describe('ApiConfigService', () => {
  let config: ApiConfigService;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({});
    config = TestBed.inject(ApiConfigService);
  });

  it('completa /api/v1 si el mesero escribe solo el dominio o la IP', () => {
    expect(config.setBaseUrl('http://192.168.1.50:8000')).toBe('');
    expect(config.baseUrl()).toBe('http://192.168.1.50:8000/api/v1');

    expect(config.setBaseUrl('https://pinguin.example/')).toBe('');
    expect(config.baseUrl()).toBe('https://pinguin.example/api/v1');
  });

  it('rechaza direcciones sin http:// o https://', () => {
    expect(config.setBaseUrl('192.168.1.50')).toBe('La dirección debe empezar con http:// o https://');
    expect(config.isCustom()).toBe(false);
  });

  it('vacía regresa a la dirección por defecto', () => {
    config.setBaseUrl('https://pinguin.example');
    expect(config.setBaseUrl('')).toBe('');
    expect(config.baseUrl()).toBe(config.defaultUrl);
  });

  it('en el navegador no exige configurar servidor', () => {
    expect(config.needsServer()).toBe(false);
  });
});
