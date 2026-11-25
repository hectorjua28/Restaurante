/**
 * Envoltorio mínimo de IndexedDB (sin dependencias) para la bandeja de salida de comandas.
 * Cada comanda se guarda con su idempotency_key como llave, así nunca se duplica.
 */

const DB_NAME = 'pinguin-meseros';
const DB_VERSION = 1;
const STORE = 'outbox';

export interface OutboxRecord<T = unknown> {
  key: string;
  value: T;
}

let dbPromise: Promise<IDBDatabase> | null = null;

function open(): Promise<IDBDatabase> {
  if (typeof indexedDB === 'undefined') {
    return Promise.reject(new Error('Este dispositivo no permite guardar comandas sin conexión.'));
  }
  dbPromise ??= new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains(STORE)) {
        request.result.createObjectStore(STORE);
      }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => {
      dbPromise = null;
      reject(request.error ?? new Error('No se pudo abrir el almacenamiento local.'));
    };
  });
  return dbPromise;
}

function run<T>(mode: IDBTransactionMode, fn: (store: IDBObjectStore) => IDBRequest<T>): Promise<T> {
  return open().then(
    (db) =>
      new Promise<T>((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const request = fn(tx.objectStore(STORE));
        tx.oncomplete = () => resolve(request.result);
        tx.onerror = () => reject(tx.error ?? request.error);
        tx.onabort = () => reject(tx.error ?? new Error('Operación cancelada.'));
      }),
  );
}

export const outboxDb = {
  put<T>(key: string, value: T): Promise<IDBValidKey> {
    return run('readwrite', (s) => s.put(value, key));
  },
  delete(key: string): Promise<undefined> {
    return run('readwrite', (s) => s.delete(key));
  },
  getAll<T>(): Promise<T[]> {
    return run('readonly', (s) => s.getAll() as IDBRequest<T[]>);
  },
  clear(): Promise<undefined> {
    return run('readwrite', (s) => s.clear());
  },
  /** Solo para pruebas: olvida la conexión abierta. */
  reset(): void {
    dbPromise = null;
  },
};
