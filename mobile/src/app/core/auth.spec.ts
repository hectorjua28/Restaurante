import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';

import { ApiConfigService } from './api-config.service';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from './auth.service';
import { ConnectivityService } from './connectivity.service';
import type { User } from './models';

const API = 'http://localhost:8000/api/v1';
const mesero: User = { id: 3, name: 'Luis', email: 'mesero@pinguin.test', role: 'mesero', role_label: 'Mesero' };

describe('AuthService + authInterceptor', () => {
  let auth: AuthService;
  let http: HttpClient;
  let httpMock: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
      ],
    });
    auth = TestBed.inject(AuthService);
    http = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
  });

  afterEach(() => httpMock.verify());

  async function login(user: User = mesero): Promise<void> {
    const promise = auth.login(' mesero@pinguin.test ', 'pinguin123');
    const req = httpMock.expectOne(`${API}/auth/login`);
    expect(req.request.body).toEqual({
      email: 'mesero@pinguin.test',
      password: 'pinguin123',
      device_name: 'App meseros',
    });
    req.flush({ token: 'tok-123', user });
    await promise;
  }

  it('inicia sesión y guarda la sesión en el teléfono', async () => {
    await login();

    expect(auth.isLoggedIn()).toBe(true);
    expect(auth.user()?.name).toBe('Luis');
    expect(JSON.parse(localStorage.getItem('pinguin.session') ?? '{}').token).toBe('tok-123');
  });

  it('rechaza usuarios que no son meseros y revoca su token', async () => {
    const promise = auth.login('cocina@pinguin.test', 'x');
    httpMock.expectOne(`${API}/auth/login`).flush({ token: 'tok-cocina', user: { ...mesero, role: 'cocina' } });

    await expect(promise).rejects.toThrow('Esta app es para meseros');
    const revoke = httpMock.expectOne(`${API}/auth/logout`);
    expect(revoke.request.headers.get('Authorization')).toBe('Bearer tok-cocina');
    revoke.flush(null);
    expect(auth.isLoggedIn()).toBe(false);
  });

  it('el interceptor agrega el token y Accept: application/json', async () => {
    await login();

    http.get(`${API}/tables`).subscribe();
    const req = httpMock.expectOne(`${API}/tables`);
    expect(req.request.headers.get('Authorization')).toBe('Bearer tok-123');
    expect(req.request.headers.get('Accept')).toBe('application/json');
    req.flush({ data: [] });
  });

  it('no agrega el token a peticiones fuera de la API', async () => {
    await login();

    http.get('https://otro-sitio.test/recurso').subscribe();
    const req = httpMock.expectOne('https://otro-sitio.test/recurso');
    expect(req.request.headers.has('Authorization')).toBe(false);
    req.flush({});
  });

  it('al recibir 401 cierra la sesión y manda al login', async () => {
    await login();

    http.get(`${API}/orders`).subscribe({ error: () => undefined });
    httpMock
      .expectOne(`${API}/orders`)
      .flush({ message: 'No autenticado' }, { status: 401, statusText: 'Unauthorized' });

    expect(auth.isLoggedIn()).toBe(false);
    expect(localStorage.getItem('pinguin.session')).toBeNull();
    expect(router.navigate).toHaveBeenCalledWith(['/login'], { replaceUrl: true, queryParams: { expired: 1 } });
  });

  it('un 401 del propio login no redirige (credenciales incorrectas)', async () => {
    const promise = auth.login('mesero@pinguin.test', 'mala');
    httpMock.expectOne(`${API}/auth/login`).flush({ message: 'x' }, { status: 401, statusText: 'Unauthorized' });

    await expect(promise).rejects.toBeTruthy();
    expect(router.navigate).not.toHaveBeenCalled();
  });

  it('marca "sin conexión" si el servidor no responde y lo quita al volver', async () => {
    const connectivity = TestBed.inject(ConnectivityService);
    await login();

    http.get(`${API}/tables`).subscribe({ error: () => undefined });
    httpMock.expectOne(`${API}/tables`).error(new ProgressEvent('error'), { status: 0 });
    expect(connectivity.online()).toBe(false);

    http.get(`${API}/tables`).subscribe();
    httpMock.expectOne(`${API}/tables`).flush({ data: [] });
    expect(connectivity.online()).toBe(true);
  });

  it('cierra sesión en el servidor y localmente aunque no haya red', async () => {
    await login();

    const promise = auth.logout();
    httpMock.expectOne(`${API}/auth/logout`).error(new ProgressEvent('error'), { status: 0 });
    await promise;

    expect(auth.isLoggedIn()).toBe(false);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login', { replaceUrl: true });
  });

  it('permite configurar otra dirección del servidor', () => {
    const config = TestBed.inject(ApiConfigService);
    config.setBaseUrl('http://192.168.1.50:8000/api/v1/');

    expect(config.baseUrl()).toBe('http://192.168.1.50:8000/api/v1');
    expect(config.isCustom()).toBe(true);
    expect(config.isApiRequest('http://192.168.1.50:8000/api/v1/tables')).toBe(true);

    config.setBaseUrl('');
    expect(config.baseUrl()).toBe(API);
  });
});
