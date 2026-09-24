import { Component, OnInit, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth/auth.service';
import { Offer, OffersService } from '../../core/api/offers.service';

@Component({
  selector: 'app-offers',
  standalone: true,
  imports: [RouterLink, FormsModule],
  template: `
    <h1>Offers</h1>
    @if (auth.user()) {
      <p>
        Signed in as {{ auth.user()?.email }}
        <button type="button" (click)="logout()">Logout</button>
      </p>
    }
    <p>
      <a routerLink="/app/offers/new">New offer</a>
      <label>
        Status
        <select [(ngModel)]="statusFilter" (ngModelChange)="load()">
          <option value="">All</option>
          <option value="draft">Draft</option>
          <option value="published">Published</option>
          <option value="closed">Closed</option>
          <option value="archived">Archived</option>
        </select>
      </label>
    </p>
    @if (error()) {
      <p role="alert">{{ error() }}</p>
    }
    @if (notice()) {
      <p role="status">{{ notice() }}</p>
    }
    <ul>
      @for (offer of offers(); track offer.id) {
        <li>
          <strong>{{ offer.title }}</strong> [{{ offer.status }}]
          @if (offer.public_url) {
            <button type="button" (click)="copy(offer.public_url!)">Copy link</button>
          }
          <a [routerLink]="['/app/offers', offer.id]">Applications</a>
          <a [routerLink]="['/app/offers', offer.id, 'edit']">Edit</a>
          @if (offer.status === 'draft' || offer.status === 'closed') {
            <button type="button" (click)="publish(offer.id)">Publish</button>
          }
          @if (offer.status === 'published') {
            <button type="button" (click)="close(offer.id)">Close</button>
          }
          <button type="button" (click)="duplicate(offer.id)">Duplicate</button>
          <button type="button" (click)="remove(offer.id)">Delete</button>
        </li>
      } @empty {
        <li>No offers yet.</li>
      }
    </ul>
  `,
})
export class OffersPage implements OnInit {
  readonly offers = signal<Offer[]>([]);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);
  statusFilter = '';

  constructor(
    readonly auth: AuthService,
    private readonly api: OffersService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    void this.load();
  }

  async load(): Promise<void> {
    this.error.set(null);
    try {
      this.offers.set(await this.api.list(this.statusFilter || undefined));
    } catch {
      this.error.set('Could not load offers.');
    }
  }

  async copy(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
    this.notice.set('Link copied.');
  }

  async publish(id: number): Promise<void> {
    await this.api.publish(id);
    await this.load();
  }

  async close(id: number): Promise<void> {
    await this.api.close(id);
    await this.load();
  }

  async duplicate(id: number): Promise<void> {
    const copy = await this.api.duplicate(id);
    await this.router.navigate(['/app/offers', copy.id, 'edit']);
  }

  async remove(id: number): Promise<void> {
    if (!confirm('Delete this offer? Only offers without applications can be deleted.')) return;
    try {
      await this.api.delete(id);
      await this.load();
    } catch {
      this.error.set('Delete refused: close or archive offers with applications.');
    }
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    await this.router.navigate(['/login']);
  }
}
