import { Component, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ForwardsService, Forward } from '../../core/api/forwards.service';

const STATUS_LABELS: Record<string, string> = {
  queued: 'En file d’envoi',
  sent: 'Envoyé',
  failed: 'Échec',
};

const STATUS_BADGES: Record<string, string> = {
  queued: 'badge-amber',
  sent: 'badge-green',
  failed: 'badge-red',
};

@Component({
  selector: 'app-forwards-page',
  standalone: true,
  imports: [RouterLink],
  template: `
    <p><a routerLink="/app/offers">← Offres</a></p>
    <h1>Transferts par email</h1>
    <p class="muted">
      Historique des candidatures transférées à un client ou un partenariat (EF-1007).
    </p>

    @if (error()) {
      <p class="alert" role="alert">{{ error() }}</p>
    }

    <div class="panel table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Destinataires</th>
            <th>Objet</th>
            <th>Candidats</th>
            <th>Livraison</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @if (loading()) {
            @for (row of [1, 2, 3]; track row) {
              <tr class="skeleton-row">
                <td colspan="7"><span class="skeleton"></span></td>
              </tr>
            }
          } @else {
            @for (f of forwards(); track f.id) {
              <tr>
                <td>{{ formatDateTime(f.created_at) }}</td>
                <td>{{ f.to_emails.join(', ') }}</td>
                <td>
                  <strong>{{ f.subject }}</strong>
                  @if (f.message) {
                    <div class="muted small">{{ f.message }}</div>
                  }
                </td>
                <td>
                  <span class="badge">{{ f.candidates.length }}</span>
                  <div class="muted small">
                    {{
                      f.candidates
                        .slice(0, 3)
                        .map((c) => c.name)
                        .join(', ')
                    }}
                    @if (f.candidates.length > 3) {
                      …
                    }
                  </div>
                </td>
                <td>
                  {{ f.delivery === 'links' ? 'Liens signés (7 j)' : 'Pièces jointes' }}
                  @if (f.include_analysis) {
                    <div class="muted small">+ analyses</div>
                  }
                </td>
                <td>
                  <span [class]="'badge ' + (STATUS_BADGES[f.status] ?? '')">
                    {{ STATUS_LABELS[f.status] ?? f.status }}
                  </span>
                  @if (f.error_message) {
                    <div class="muted small">{{ f.error_message }}</div>
                  }
                  @if (f.sent_at) {
                    <div class="muted small">{{ formatDateTime(f.sent_at) }}</div>
                  }
                </td>
                <td>
                  @if (f.status !== 'sent') {
                    <button
                      type="button"
                      class="btn"
                      (click)="retry(f)"
                      [disabled]="busy() === f.id"
                    >
                      Relancer
                    </button>
                  }
                </td>
              </tr>
            } @empty {
              <tr>
                <td colspan="7">
                  <div class="empty hint-arrow">
                    <h3>Aucun transfert</h3>
                    <p>
                      Sélectionnez des candidatures dans l'espace de travail, puis transférez-les.
                    </p>
                    <p><a class="btn" routerLink="/app/offers">Voir les offres</a></p>
                  </div>
                </td>
              </tr>
            }
          }
        </tbody>
      </table>
    </div>

    <div class="pager">
      <button type="button" class="btn" (click)="go(-1)" [disabled]="page() <= 1 || loading()">
        ← Précédent
      </button>
      <span class="muted">Page {{ page() }} / {{ lastPage() }}</span>
      <button
        type="button"
        class="btn"
        (click)="go(1)"
        [disabled]="page() >= lastPage() || loading()"
      >
        Suivant →
      </button>
    </div>
  `,
  styles: [
    `
      .pager {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        margin-top: 1.25rem;
      }

      .small {
        font-size: 0.8rem;
      }

      .muted + .muted {
        margin-top: 0.15rem;
      }
    `,
  ],
})
export class ForwardsPage implements OnInit {
  readonly forwards = signal<Forward[]>([]);
  readonly page = signal(1);
  readonly lastPage = signal(1);
  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly busy = signal<number | null>(null);

  readonly STATUS_LABELS = STATUS_LABELS;
  readonly STATUS_BADGES = STATUS_BADGES;

  constructor(private readonly api: ForwardsService) {}

  ngOnInit(): void {
    void this.load(1);
  }

  async load(page: number): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const res = await this.api.list(page);
      this.forwards.set(res.data);
      this.page.set(res.meta.current_page);
      this.lastPage.set(res.meta.last_page);
    } catch {
      this.error.set('Impossible de charger les transferts.');
    } finally {
      this.loading.set(false);
    }
  }

  async retry(forward: Forward): Promise<void> {
    this.busy.set(forward.id);
    try {
      await this.api.retry(forward.id);
      await this.load(this.page());
    } catch {
      this.error.set('Relance impossible.');
    } finally {
      this.busy.set(null);
    }
  }

  go(delta: number): void {
    void this.load(this.page() + delta);
  }

  formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
