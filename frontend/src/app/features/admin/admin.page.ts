import { Component, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AdminService, AdminUser, ModelUsage, UsageTotals } from '../../core/api/admin.service';
import { CountUpDirective } from '../../core/ui/count-up.directive';

@Component({
  selector: 'app-admin-page',
  standalone: true,
  imports: [RouterLink, CountUpDirective],
  template: `
    <p><a routerLink="/app/offers">← Offres</a></p>
    <h1>Administration</h1>

    @if (error()) {
      <p class="alert" role="alert">{{ error() }}</p>
    }

    <h2 class="section-title">Usages IA</h2>
    @if (usage(); as u) {
      <div class="usage-chips">
        <span class="chip"><span [appCountUp]="u.totals.analyses_count"></span> analyse(s)</span>
        <span class="chip"><span [appCountUp]="u.totals.completed_count"></span> terminée(s)</span>
        <span class="chip"><span [appCountUp]="u.totals.failed_count"></span> échec(s)</span>
        <span class="chip">{{ formatTokens(u.totals.tokens_in) }} tokens in</span>
        <span class="chip">{{ formatTokens(u.totals.tokens_out) }} tokens out</span>
      </div>

      <div class="panel table-wrap">
        <table>
          <thead>
            <tr>
              <th>Fournisseur</th>
              <th>Modèle</th>
              <th class="num">Analyses</th>
              <th class="num">Tokens in</th>
              <th class="num">Tokens out</th>
            </tr>
          </thead>
          <tbody>
            @for (row of u.per_model; track row.llm_model) {
              <tr>
                <td>{{ row.llm_provider ?? '—' }}</td>
                <td>{{ row.llm_model ?? '—' }}</td>
                <td class="num">{{ row.analyses_count }}</td>
                <td class="num">{{ formatTokens(row.tokens_in) }}</td>
                <td class="num">{{ formatTokens(row.tokens_out) }}</td>
              </tr>
            } @empty {
              <tr>
                <td colspan="5" class="muted">Aucune analyse enregistrée.</td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    } @else if (loading()) {
      <div class="panel" aria-label="Chargement des usages">
        <span class="skeleton" style="width: 40%; margin-bottom: 0.6rem"></span>
        <span class="skeleton" style="width: 70%"></span>
      </div>
    } @else if (!error()) {
      <div class="empty">
        <h3>Aucune analyse</h3>
        <p>Les analyses IA apparaîtront ici dès la première candidature traitée.</p>
      </div>
    }

    <h2 class="section-title">Comptes</h2>
    @if (users().length) {
      <div class="panel table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nom</th>
              <th>E-mail</th>
              <th class="num">Offres</th>
              <th class="num">Candidatures</th>
              <th>Rôle</th>
            </tr>
          </thead>
          <tbody>
            @for (user of users(); track user.id) {
              <tr>
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>
                <td class="num">{{ user.offers_count }}</td>
                <td class="num">{{ user.applications_count }}</td>
                <td>
                  @if (user.is_admin) {
                    <span class="badge badge-teal">administrateur</span>
                  } @else {
                    <span class="badge">recruteur</span>
                  }
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    } @else if (loading()) {
      <div class="panel table-wrap" aria-label="Chargement des comptes">
        <table>
          <tbody>
            @for (row of [1, 2, 3]; track row) {
              <tr class="skeleton-row">
                <td colspan="5"><span class="skeleton"></span></td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    } @else if (!error()) {
      <div class="empty">
        <h3>Aucun compte</h3>
        <p>Les comptes recruteurs apparaîtront ici après leur inscription.</p>
      </div>
    }
  `,
  styles: [
    `
      .section-title {
        font-size: 1.05rem;
        margin: 1.5rem 0 0.75rem;
      }

      .usage-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin-bottom: 0.9rem;
      }

      .table-wrap {
        overflow-x: auto;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.92rem;
      }

      th,
      td {
        text-align: left;
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--line);
      }

      th {
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--ink-soft);
      }

      .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
      }
    `,
  ],
})
export class AdminPage implements OnInit {
  readonly users = signal<AdminUser[]>([]);
  readonly usage = signal<{ totals: UsageTotals; per_model: ModelUsage[] } | null>(null);
  readonly error = signal<string | null>(null);
  readonly loading = signal(true);

  constructor(private readonly api: AdminService) {}

  ngOnInit(): void {
    void this.load();
  }

  async load(): Promise<void> {
    try {
      const [users, usage] = await Promise.all([this.api.users(), this.api.usage()]);
      this.users.set(users);
      this.usage.set(usage);
    } catch {
      this.error.set('Chargement des données administrateur impossible.');
    } finally {
      this.loading.set(false);
    }
  }

  formatTokens(value: number): string {
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} M`;
    if (value >= 1000) return `${(value / 1000).toFixed(1)} k`;
    return String(value);
  }
}
