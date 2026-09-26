import { Component, signal, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from './core/auth/auth.service';
import { CelebrationComponent } from './core/ui/celebration.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, CelebrationComponent],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class App {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly celebrate = signal(false);
  private nameClicks = 0;
  private lastClick = 0;

  logout(): Promise<void> {
    return this.auth.logout().then(() => void this.router.navigate(['/login']));
  }

  /** Easter egg: tap your own name five times fast. */
  onNameTap(): void {
    const now = Date.now();
    this.nameClicks = now - this.lastClick < 700 ? this.nameClicks + 1 : 1;
    this.lastClick = now;
    if (this.nameClicks >= 5) {
      this.nameClicks = 0;
      this.celebrate.set(true);
    }
  }
}
