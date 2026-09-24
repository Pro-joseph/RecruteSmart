import { Component, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-offers',
  standalone: true,
  template: `
    <h1>Offers</h1>
    @if (auth.user()) {
      <p>Signed in as {{ auth.user()?.email }}</p>
      <button type="button" (click)="logout()">Logout</button>
    }
    <p>Offer workspace list lands here (Lot 1/2).</p>
  `,
})
export class OffersPage {
  constructor(
    readonly auth: AuthService,
    private readonly router: Router,
  ) {}

  async logout(): Promise<void> {
    await this.auth.logout();
    await this.router.navigate(['/login']);
  }
}
