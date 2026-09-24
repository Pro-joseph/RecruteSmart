import { Component, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApplicationRow, Offer, OffersService, Paginated } from '../../core/api/offers.service';

@Component({
  selector: 'app-workspace',
  standalone: true,
  imports: [RouterLink],
  template: `
    <p><a routerLink="/app/offers">← Offers</a></p>
    @if (offer(); as o) {
      <h1>{{ o.title }} [{{ o.status }}]</h1>
      @if (o.public_url) {
        <p>
          Public link: <a [href]="o.public_url" target="_blank">{{ o.public_url }}</a>
          <button type="button" (click)="copy(o.public_url!)">Copy</button>
        </p>
      }
      <p>{{ o.applications_count ?? 0 }} applications</p>
    }
    @if (error()) {
      <p role="alert">{{ error() }}</p>
    }
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        @for (app of rows(); track app.id) {
          <tr>
            <td>{{ app.full_name }}</td>
            <td>{{ app.email }}</td>
            <td>{{ app.status }}</td>
            <td>{{ app.created_at }}</td>
          </tr>
        } @empty {
          <tr>
            <td colspan="4">No applications yet.</td>
          </tr>
        }
      </tbody>
    </table>
    <p>
      <button type="button" (click)="go(-1)" [disabled]="page() <= 1">← Prev</button>
      Page {{ page() }} of {{ lastPage() }}
      <button type="button" (click)="go(1)" [disabled]="page() >= lastPage()">Next →</button>
    </p>
    <p>Candidate details land here in Lot 4.</p>
  `,
})
export class WorkspacePage implements OnInit {
  readonly offer = signal<Offer | null>(null);
  readonly rows = signal<ApplicationRow[]>([]);
  readonly page = signal(1);
  readonly lastPage = signal(1);
  readonly error = signal<string | null>(null);

  private offerId = 0;

  constructor(
    private readonly api: OffersService,
    private readonly route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    this.offerId = Number(this.route.snapshot.paramMap.get('id'));
    void this.loadOffer();
    void this.loadPage(1);
  }

  async loadOffer(): Promise<void> {
    try {
      this.offer.set(await this.api.get(this.offerId));
    } catch {
      this.error.set('Could not load offer.');
    }
  }

  async loadPage(page: number): Promise<void> {
    this.error.set(null);
    try {
      const res: Paginated<ApplicationRow> = await this.api.listApplications(this.offerId, page);
      this.rows.set(res.data);
      this.page.set(res.meta.current_page);
      this.lastPage.set(res.meta.last_page);
    } catch {
      this.error.set('Could not load applications.');
    }
  }

  go(delta: number): void {
    void this.loadPage(this.page() + delta);
  }

  async copy(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
  }
}
