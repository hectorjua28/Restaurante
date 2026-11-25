import './bootstrap';

/*
 * Indicador de "sin conexión" para los paneles con polling.
 * Si el navegador avisa que no hay red, o si pasan más de OFFLINE_AFTER_MS sin una respuesta
 * correcta de Livewire mientras hay polling activo, se muestra el aviso. Desaparece con la
 * siguiente respuesta correcta.
 */
const OFFLINE_AFTER_MS = 12000;
let lastOk = Date.now();
let pendingSince = null;

function setOffline(offline) {
    const banner = document.getElementById('offline-banner');
    if (banner) {
        banner.hidden = !offline;
    }
}

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('request', ({ succeed, fail }) => {
        pendingSince ??= Date.now();

        succeed(() => {
            lastOk = Date.now();
            pendingSince = null;
            setOffline(false);
        });

        fail(({ status, preventDefault }) => {
            pendingSince = null;
            // 419 = la sesión expiró: dejamos que Livewire pida recargar la página.
            if (status !== 419) {
                setOffline(true);
                // Evita el modal de error de Livewire en cada intento fallido del polling.
                preventDefault();
            }
        });
    });
});

setInterval(() => {
    const polling = document.querySelector('[wire\\:poll], [wire\\:poll\\.4s], [wire\\:poll\\.5s]');
    const stalled = pendingSince !== null && Date.now() - pendingSince > OFFLINE_AFTER_MS;
    const silent = polling !== null && Date.now() - lastOk > OFFLINE_AFTER_MS;
    if (!navigator.onLine || stalled || silent) {
        setOffline(true);
    }
}, 2000);

window.addEventListener('offline', () => setOffline(true));
window.addEventListener('online', () => {
    lastOk = Date.now();
    setOffline(false);
});
