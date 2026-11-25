/**
 * Polling sencillo que no encima peticiones: espera a que termine una antes de programar la siguiente.
 * Se usa en lugar de WebSockets porque el hosting compartido no los permite.
 */
export class Poller {
  private timer: ReturnType<typeof setTimeout> | null = null;
  private running = false;

  constructor(
    private readonly task: () => Promise<unknown>,
    private readonly intervalMs = 5000,
  ) {}

  start(runNow = true): void {
    if (this.running) return;
    this.running = true;
    if (runNow) {
      void this.tick();
    } else {
      this.schedule();
    }
  }

  stop(): void {
    this.running = false;
    if (this.timer) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  }

  get isRunning(): boolean {
    return this.running;
  }

  private schedule(): void {
    if (!this.running) return;
    this.timer = setTimeout(() => void this.tick(), this.intervalMs);
  }

  private async tick(): Promise<void> {
    try {
      await this.task();
    } catch {
      // El error ya lo maneja la tarea; el polling continúa.
    } finally {
      this.schedule();
    }
  }
}
