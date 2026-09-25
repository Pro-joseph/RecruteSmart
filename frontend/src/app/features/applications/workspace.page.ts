import { Component, OnDestroy, OnInit, computed, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import {
  ApplicationRow,
  Offer,
  OffersService,
  Paginated,
  SkillFacet,
} from '../../core/api/offers.service';
import {
  EMPTY_FILTERS,
  FilterPatch,
  ListFilters,
  ListFiltersComponent,
  activeFilterCount,
  filterQueryParams,
  parseFilterParams,
  toListParams,
} from './list-filters.component';
import { SavedViewsComponent } from './saved-views.component';
import { STATUS_BADGES, STATUS_LABELS, VERDICT_BADGES, VERDICT_LABELS } from './application-labels';

interface SortableColumn {
  key: string;
  label: string;
}

const SORTABLE: SortableColumn[] = [
  { key: 'full_name', label: 'Candidat' },
  { key: 'match_score', label: 'Score' },
  { key: 'ats_score', label: 'ATS' },
  { key: 'years_experience', label: 'Exp.' },
  { key: 'created_at', label: 'Date' },
];

@Component({
  selector: 'app-workspace',
  standalone: true,
  imports: [RouterLink, FormsModule, ListFiltersComponent, SavedViewsComponent],
  template: `
    <p><a routerLink="/app/offers">← Offres</a></p>
    @if (offer(); as o) {
      <h1>
        {{ o.title }} <span class="badge">{{ o.status }}</span>
      </h1>
      @if (o.public_url) {
        <p>
          Lien public : <a [href]="o.public_url" target="_blank">{{ o.public_url }}</a>
          <button type="button" class="btn btn-ghost" (click)="copy(o.public_url!)">Copier</button>
        </p>
      }
    }

    <div class="toolbar">
      <label class="search">
        Rechercher
        <input
          type="search"
          placeholder="Nom, e-mail ou compétence…"
          [ngModel]="filters().q"
          (ngModelChange)="onSearch($event)"
        />
      </label>
      <app-saved-views
        [offerId]="offerId"
        [query]="listQuery()"
        (applyView)="onApplyView($event)"
      />
      <span class="muted">{{ total() }} candidature(s)</span>
    </div>

    <app-list-filters
      [f]="filters()"
      [facets]="facets()"
      (change)="onFilterChange($event)"
      (reset)="onReset()"
    />

    @if (refreshing()) {
      <div class="refresh-bar" role="status" aria-label="Actualisation"></div>
    }
    @if (error()) {
      <p class="alert" role="alert">{{ error() }}</p>
    }

    <div class="panel table-wrap">
      <table>
        <thead>
          <tr>
            @for (col of sortable; track col.key) {
              <th
                class="sortable"
                (click)="toggleSort(col.key)"
                [attr.aria-sort]="ariaSort(col.key)"
              >
                {{ col.label }}
                @if (sortKey() === col.key) {
                  <span class="sort-caret">{{ sortDir() === 'desc' ? '▼' : '▲' }}</span>
                }
              </th>
            }
            <th>Ville</th>
            <th>Compétences</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          @if (loading()) {
            @for (row of [1, 2, 3, 4, 5]; track row) {
              <tr class="skeleton-row">
                <td colspan="8"><span class="skeleton"></span></td>
              </tr>
            }
          } @else {
            @for (app of rows(); track app.id) {
              <tr>
                <td>
                  <a
                    [routerLink]="['/app/offers', offerId, 'applications', app.id]"
                    [queryParams]="listQuery()"
                  >
                    {{ app.full_name }}
                  </a>
                  <div class="muted small">{{ app.email }}</div>
                </td>
                <td>
                  @if (app.analysis?.match_score != null) {
                    <span
                      class="score"
                      [class.score-good]="app.analysis!.match_score! >= 70"
                      [class.score-mid]="
                        app.analysis!.match_score! >= 40 && app.analysis!.match_score! < 70
                      "
                      [class.score-low]="app.analysis!.match_score! < 40"
                    >
                      <span class="score-value">{{ app.analysis!.match_score }}%</span>
                      <span class="score-meter">
                        <i [style.width.%]="app.analysis!.match_score"></i>
                      </span>
                    </span>
                    @if (app.analysis!.is_stale) {
                      <span class="badge badge-amber" title="Critères modifiés : score à revoir"
                        >obsolète</span
                      >
                    }
                  } @else if (analysisBadge(app); as b) {
                    <span [class]="'badge ' + b.cls">
                      <span
                        class="pulse-dot"
                        [class.pulse-dot-teal]="b.cls === 'badge-teal'"
                      ></span>
                      {{ b.label }}
                    </span>
                  } @else {
                    <span class="muted">—</span>
                  }
                </td>
                <td>
                  @if (app.analysis?.ats_verdict; as v) {
                    <span [class]="'badge ' + (VERDICT_BADGES[v] ?? '')">
                      {{ VERDICT_LABELS[v] ?? v }}
                    </span>
                    @if (app.analysis?.ats_score != null) {
                      <span class="muted small"> {{ app.analysis!.ats_score }}</span>
                    }
                  } @else {
                    <span class="muted">—</span>
                  }
                </td>
                <td>
                  @if (app.analysis?.years_experience != null) {
                    {{ app.analysis!.years_experience }} ans
                  } @else {
                    <span class="muted">—</span>
                  }
                </td>
                <td>{{ app.analysis?.city ?? '—' }}</td>
                <td>
                  @for (skill of topSkills(app); track skill) {
                    <span class="chip">{{ skill }}</span>
                  }
                </td>
                <td>
                  <span [class]="'badge ' + (STATUS_BADGES[app.status] ?? '')">
                    {{ STATUS_LABELS[app.status] ?? app.status }}
                  </span>
                </td>
                <td>{{ formatDate(app.created_at) }}</td>
              </tr>
            } @empty {
              <tr>
                <td colspan="8">
                  <div class="empty">
                    <h3>Aucune candidature</h3>
                    <p>
                      @if (hasFilters()) {
                        Aucun résultat avec ces filtres. Essayez de les élargir.
                      } @else {
                        Partagez le lien public pour recevoir des candidatures.
                      }
                    </p>
                    @if (hasFilters()) {
                      <button type="button" class="btn" (click)="onReset()">
                        Réinitialiser les filtres
                      </button>
                    }
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
      .toolbar {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 1rem;
        margin: 1rem 0 0.25rem;
      }

      .search {
        max-width: 340px;
        flex: 1;
      }

      .small {
        font-size: 0.8rem;
      }

      .chip {
        margin: 0.1rem 0.2rem 0.1rem 0;
      }

      .pager {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        margin-top: 1.25rem;
      }

      .alert {
        margin: 0.75rem 0;
      }

      .empty .btn {
        margin-top: 0.75rem;
      }
    `,
  ],
})
export class WorkspacePage implements OnInit, OnDestroy {
  readonly offer = signal<Offer | null>(null);
  readonly rows = signal<ApplicationRow[]>([]);
  readonly facets = signal<SkillFacet[]>([]);
  readonly page = signal(1);
  readonly lastPage = signal(1);
  readonly total = signal(0);
  readonly error = signal<string | null>(null);
  readonly loading = signal(true);
  readonly refreshing = signal(false);
  readonly filters = signal<ListFilters>(EMPTY_FILTERS);
  readonly sort = signal('-match_score');
  readonly sortKey = computed(() => this.sort().replace(/^-/, ''));
  readonly sortDir = computed<'asc' | 'desc'>(() => (this.sort().startsWith('-') ? 'desc' : 'asc'));

  readonly sortable = SORTABLE;
  readonly VERDICT_LABELS = VERDICT_LABELS;
  readonly VERDICT_BADGES = VERDICT_BADGES;
  readonly STATUS_LABELS = STATUS_LABELS;
  readonly STATUS_BADGES = STATUS_BADGES;

  offerId = 0;

  private searchTimer: ReturnType<typeof setTimeout> | null = null;
  private patchTimer: ReturnType<typeof setTimeout> | null = null;
  private pollTimer: ReturnType<typeof setInterval> | null = null;
  private destroyed = false;

  constructor(
    private readonly api: OffersService,
    private readonly route: ActivatedRoute,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    this.offerId = Number(this.route.snapshot.paramMap.get('id'));
    void this.loadOffer();
    void this.loadFacets();

    this.route.queryParams.subscribe((params) => {
      this.filters.set(parseFilterParams(params));
      this.sort.set(typeof params['sort'] === 'string' ? params['sort'] : '-match_score');
      void this.loadPage(Number(params['page']) || 1, { silent: false });
    });

    this.pollTimer = setInterval(() => this.poll(), 5000);
    document.addEventListener('visibilitychange', this.onVisibility);
  }

  ngOnDestroy(): void {
    this.destroyed = true;
    if (this.pollTimer !== null) clearInterval(this.pollTimer);
    if (this.searchTimer !== null) clearTimeout(this.searchTimer);
    if (this.patchTimer !== null) clearTimeout(this.patchTimer);
    document.removeEventListener('visibilitychange', this.onVisibility);
  }

  async loadOffer(): Promise<void> {
    try {
      this.offer.set(await this.api.get(this.offerId));
    } catch {
      this.error.set('Impossible de charger cette offre.');
    }
  }

  async loadFacets(): Promise<void> {
    try {
      this.facets.set(await this.api.applicationSkills(this.offerId));
    } catch {
      this.facets.set([]);
    }
  }

  async loadPage(page: number, opts: { silent: boolean }): Promise<void> {
    if (!opts.silent) {
      this.error.set(null);
      this.loading.set(true);
    } else {
      this.refreshing.set(true);
    }
    try {
      const params = toListParams(this.filters(), this.sort(), page);
      const res: Paginated<ApplicationRow> = await this.api.listApplications(this.offerId, params);
      this.rows.set(res.data);
      this.page.set(res.meta.current_page);
      this.lastPage.set(res.meta.last_page);
      this.total.set(res.meta.total);
      this.error.set(null);
    } catch {
      if (!opts.silent) this.error.set('Impossible de charger les candidatures.');
    } finally {
      this.loading.set(false);
      this.refreshing.set(false);
    }
  }

  toggleSort(key: string): void {
    const dir =
      this.sortKey() === key ? (this.sortDir() === 'desc' ? 'asc' : 'desc') : this.defaultDir(key);
    this.navigate({ sort: `${dir === 'desc' ? '-' : ''}${key}`, page: undefined });
  }

  ariaSort(key: string): string {
    if (this.sortKey() !== key) return 'none';
    return this.sortDir() === 'desc' ? 'descending' : 'ascending';
  }

  onSearch(value: string): void {
    if (this.searchTimer !== null) clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      this.navigate({ q: value || undefined, page: undefined });
    }, 300);
  }

  onFilterChange(event: FilterPatch): void {
    this.filters.update((current) => ({ ...current, ...event.patch }));
    if (this.patchTimer !== null) clearTimeout(this.patchTimer);
    const apply = () => this.navigate({ page: undefined });
    if (event.debounce) {
      this.patchTimer = setTimeout(apply, 300);
    } else {
      apply();
    }
  }

  onReset(): void {
    this.filters.set(EMPTY_FILTERS);
    this.navigate({
      q: undefined,
      min_score: undefined,
      max_score: undefined,
      ats: undefined,
      ats_min: undefined,
      ats_max: undefined,
      exp_min: undefined,
      exp_max: undefined,
      city: undefined,
      country: undefined,
      applied_from: undefined,
      applied_to: undefined,
      skills: undefined,
      skills_mode: undefined,
      status: undefined,
      page: undefined,
    });
  }

  /** EF-809: reapply a saved view by writing its query params to the URL. */
  onApplyView(query: Record<string, string | string[]>): void {
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: query,
      onSameUrlNavigation: 'ignore',
    });
  }

  /** Query params shared with the fiche so prev/next and back keep the list context. */
  listQuery(): Record<string, string | string[]> {
    const query: Record<string, string | string[] | undefined> = {
      ...filterQueryParams(this.filters()),
      sort: this.sort() === '-match_score' ? undefined : this.sort(),
      page: this.page() > 1 ? String(this.page()) : undefined,
    };
    for (const key of Object.keys(query)) {
      if (query[key] === undefined) delete query[key];
    }
    return query as Record<string, string | string[]>;
  }

  go(delta: number): void {
    this.navigate({ page: this.page() + delta <= 1 ? undefined : this.page() + delta });
  }

  hasFilters(): boolean {
    return activeFilterCount(this.filters()) > 0;
  }

  analysisBadge(app: ApplicationRow): { label: string; cls: string } | null {
    const status = app.analysis?.status;
    if (status === 'pending') return { label: 'En attente', cls: 'badge-amber' };
    if (status === 'processing') return { label: 'Analyse en cours', cls: 'badge-teal' };
    if (status === 'failed') {
      return {
        label: app.analysis?.error_message ? `Échec : ${app.analysis.error_message}` : 'Échec',
        cls: 'badge-red',
      };
    }
    return null;
  }

  topSkills(app: ApplicationRow): string[] {
    return (app.analysis?.skills ?? []).slice(0, 3);
  }

  formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  async copy(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
  }

  private defaultDir(key: string): 'asc' | 'desc' {
    return key === 'full_name' || key === 'created_at' ? 'asc' : 'desc';
  }

  private navigate(overrides: Record<string, unknown>): void {
    const query: Record<string, unknown> = {
      ...this.listQuery(),
      ...overrides,
    };
    for (const key of Object.keys(query)) {
      if (query[key] === undefined) delete query[key];
    }
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: query,
      onSameUrlNavigation: 'ignore',
    });
  }

  private readonly onVisibility = (): void => {
    if (!document.hidden && this.hasPendingAnalysis()) {
      void this.loadPage(this.page(), { silent: true });
    }
  };

  private hasPendingAnalysis(): boolean {
    return this.rows().some(
      (row) => row.analysis?.status === 'pending' || row.analysis?.status === 'processing',
    );
  }

  private poll(): void {
    if (this.destroyed || document.hidden || !this.hasPendingAnalysis()) return;
    void this.loadPage(this.page(), { silent: true });
  }
}
