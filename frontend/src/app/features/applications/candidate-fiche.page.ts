import { Component, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { FormsModule } from '@angular/forms';
import { ApplicationRow, OffersService } from '../../core/api/offers.service';
import {
  ApplicationEvent,
  ApplicationFiche,
  ApplicationsService,
  FicheFile,
} from '../../core/api/applications.service';
import {
  EMPTY_FILTERS,
  ListFilters,
  filterQueryParams,
  parseFilterParams,
  toListParams,
} from './list-filters.component';
import { STATUS_LABELS, STATUSES, VERDICT_BADGES, VERDICT_LABELS } from './application-labels';

const CRITERIA_LABELS: Record<string, string> = {
  required_skills: 'Compétences obligatoires',
  preferred_skills: 'Compétences souhaitées',
  experience: 'Expérience',
  education: 'Formation',
  languages: 'Langues',
};

const EVENT_LABELS: Record<string, string> = {
  status_changed: 'Changement de statut',
  note_added: 'Note ajoutée',
  interview_planned: 'Entretien planifié',
  forwarded: 'Transfert par email',
  analysis_completed: 'Analyse terminée',
  forward_created: 'Transfert créé',
};

const EVENT_BADGES: Record<string, string> = {
  status_changed: 'badge-blue',
  note_added: 'badge-amber',
  interview_planned: 'badge-teal',
  forwarded: 'badge-indigo',
  analysis_completed: 'badge-green',
  forward_created: 'badge-indigo',
};

@Component({
  selector: 'app-candidate-fiche',
  standalone: true,
  imports: [RouterLink, FormsModule],
  template: `
    <p>
      <a [routerLink]="['/app/offers', offerId]" [queryParams]="listQuery()">
        ← Retour à la liste
      </a>
    </p>

    @if (error()) {
      <p class="alert" role="alert">{{ error() }}</p>
    }

    @if (app(); as a) {
      <header class="fiche-head">
        <div>
          <h1>{{ a.full_name }}</h1>
          <p class="muted">
            <a [href]="'mailto:' + a.email">{{ a.email }}</a>
            @if (a.phone) {
              · {{ a.phone }}
            }
            · Candidature du {{ formatDate(a.created_at) }}
          </p>
          <p class="muted small">{{ a.offer.title }}</p>
        </div>
        <div class="head-side">
          <label>
            Statut
            <select [ngModel]="a.status" (ngModelChange)="changeStatus($event)">
              @for (s of statuses; track s) {
                <option [value]="s">{{ statusLabels[s] }}</option>
              }
            </select>
          </label>
          <div class="nav-arrows">
            <button
              type="button"
              class="btn"
              (click)="open(prev()!.id)"
              [disabled]="!prev()"
              title="Candidat précédent"
            >
              ←
            </button>
            <button
              type="button"
              class="btn"
              (click)="open(next()!.id)"
              [disabled]="!next()"
              title="Candidat suivant"
            >
              →
            </button>
          </div>
        </div>
      </header>

      <div class="fiche-grid">
        <div class="col-main">
          <section class="panel section">
            <h2>Analyse</h2>
            @if (a.analysis; as an) {
              @if (an.is_stale) {
                <p class="stale-note">
                  Les critères de l'offre ont changé : ce score est obsolète.
                  <button type="button" class="btn" (click)="reanalyze()">Recalculer</button>
                </p>
              }
              @if (an.status !== 'completed') {
                <p class="muted">
                  @switch (an.status) {
                    @case ('pending') {
                      Analyse en attente…
                    }
                    @case ('processing') {
                      Analyse en cours…
                    }
                    @case ('failed') {
                      Analyse échouée : {{ an.error_message || an.error_code }}
                      <button type="button" class="btn" (click)="reanalyze()">Relancer</button>
                    }
                  }
                </p>
              } @else {
                <div class="scores">
                  <div
                    class="score big"
                    [class.score-good]="an.match_score != null && an.match_score >= 70"
                    [class.score-mid]="
                      an.match_score != null && an.match_score >= 40 && an.match_score < 70
                    "
                    [class.score-low]="an.match_score != null && an.match_score < 40"
                  >
                    <span class="score-value">
                      {{ an.match_score != null ? an.match_score + '%' : '—' }}
                    </span>
                    <span class="muted small">Correspondance</span>
                    <span class="score-meter">
                      <i [style.width.%]="an.match_score ?? 0"></i>
                    </span>
                  </div>
                  <div class="score big">
                    <span class="score-value">{{ an.ats_score ?? '—' }}</span>
                    <span class="muted small">Score ATS</span>
                    <span class="score-meter">
                      <i [style.width.%]="an.ats_score ?? 0"></i>
                    </span>
                  </div>
                  @if (an.ats_verdict; as v) {
                    <span [class]="'badge ' + (VERDICT_BADGES[v] ?? '') + ' verdict'">
                      {{ VERDICT_LABELS[v] ?? v }}
                    </span>
                  }
                </div>

                @if (an.summary) {
                  <p class="summary">{{ an.summary }}</p>
                }

                <div class="two-cols">
                  <div>
                    <h3>Points forts</h3>
                    <ul class="ticks good">
                      @for (s of an.strengths ?? []; track s) {
                        <li>{{ s }}</li>
                      } @empty {
                        <li class="muted">Aucun point fort identifié.</li>
                      }
                    </ul>
                  </div>
                  <div>
                    <h3>Manques</h3>
                    <ul class="ticks gap">
                      @for (g of an.gaps ?? []; track g) {
                        <li>{{ g }}</li>
                      } @empty {
                        <li class="muted">Aucun manque identifié.</li>
                      }
                    </ul>
                  </div>
                </div>

                @if (an.match_breakdown; as breakdown) {
                  <h3>Détail par critère</h3>
                  <table class="breakdown">
                    <thead>
                      <tr>
                        <th>Critère</th>
                        <th>Sous-score</th>
                        <th>Poids</th>
                        <th>Justification</th>
                      </tr>
                    </thead>
                    <tbody>
                      @for (entry of breakdownEntries(breakdown); track entry.key) {
                        <tr>
                          <td>
                            {{ criteriaLabel(entry.key) }}
                            @if (an.knockout_flags?.[entry.key]) {
                              <span class="badge badge-red">éliminatoire</span>
                            }
                          </td>
                          <td class="mono">{{ entry.value.score }}/100</td>
                          <td class="mono">{{ entry.value.weight }}</td>
                          <td>{{ entry.value.evidence }}</td>
                        </tr>
                      }
                    </tbody>
                  </table>
                }

                @if (an.ats_checks; as checks) {
                  <h3>Contrôles ATS</h3>
                  <ul class="checks">
                    @for (check of checks; track check.code) {
                      <li [class]="check.passed ? 'pass' : 'fail'">
                        <span class="mono">{{ check.code }}</span>
                        <span class="mono points">{{ check.points }}/{{ check.max }}</span>
                        <span>{{ check.message }}</span>
                        @if (!check.passed && check.advice) {
                          <em>{{ check.advice }}</em>
                        }
                      </li>
                    }
                  </ul>
                }

                @if (an.anomalies?.length) {
                  <div class="anomalies">
                    <h3>Signalements</h3>
                    <ul>
                      @for (a2 of an.anomalies; track a2) {
                        <li>{{ a2 }}</li>
                      }
                    </ul>
                  </div>
                }
              }
            } @else {
              <p class="muted">Aucune analyse pour cette candidature.</p>
            }
          </section>

          <section class="panel section">
            <h2>Réponses</h2>
            <dl class="answers">
              @for (answer of a.answers; track answer.key) {
                <dt>{{ answer.label }}</dt>
                <dd>
                  @if (answer.value) {
                    {{ answer.value }}
                  } @else {
                    <span class="muted">—</span>
                  }
                </dd>
              }
            </dl>
          </section>

          <section class="panel section">
            <h2>Fichiers</h2>
            <ul class="files">
              @for (file of a.files; track file.key) {
                <li>
                  <span class="file-name">{{ file.name }}</span>
                  <span class="muted small">{{ formatSize(file.size) }}</span>
                  @if (isPdf(file)) {
                    <button type="button" class="btn" (click)="preview(file)">Aperçu</button>
                  }
                  <button type="button" class="btn" (click)="download(file)">Télécharger</button>
                </li>
              }
            </ul>
            @if (previewUrl(); as url) {
              <iframe class="cv-frame" [src]="url" title="Aperçu du CV"></iframe>
            }
          </section>
        </div>

        <div class="col-side">
          <section class="panel section">
            <h2>Évaluation</h2>
            <div class="stars" role="radiogroup" aria-label="Note du candidat">
              @for (star of [1, 2, 3, 4, 5]; track star) {
                <button
                  type="button"
                  role="radio"
                  class="star"
                  [class.on]="star <= (a.rating ?? 0)"
                  [attr.aria-checked]="star === (a.rating ?? 0)"
                  (click)="rate(star)"
                >
                  ★
                </button>
              }
            </div>

            <label>
              Note interne
              <textarea
                rows="3"
                placeholder="Visible uniquement par votre équipe…"
                [(ngModel)]="noteDraft"
              ></textarea>
            </label>
            <button type="button" class="btn btn-primary" (click)="saveNote()">
              Enregistrer la note
            </button>
          </section>

          <section class="panel section">
            <h2>Historique</h2>
            <ol class="timeline">
              @for (event of events(); track event.id) {
                <li>
                  <span [class]="'badge ' + (EVENT_BADGES[event.type] ?? '')">
                    {{ EVENT_LABELS[event.type] ?? event.type }}
                  </span>
                  <div class="muted small">
                    {{ formatDate(event.created_at) }}
                    @if (event.user) {
                      · {{ event.user.name }}
                    }
                  </div>
                  <div class="event-detail">{{ eventDetail(event) }}</div>
                </li>
              } @empty {
                <li class="muted">Aucun événement.</li>
              }
            </ol>
          </section>
        </div>
      </div>
    }
  `,
  styles: [
    `
      .fiche-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1.5rem;
        margin-bottom: 1.25rem;
      }

      .head-side {
        display: flex;
        gap: 0.75rem;
        align-items: end;
      }

      .head-side label {
        min-width: 170px;
      }

      .nav-arrows {
        display: flex;
        gap: 0.4rem;
      }

      .fiche-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 1.25rem;
        align-items: start;
      }

      @media (max-width: 900px) {
        .fiche-grid {
          grid-template-columns: 1fr;
        }
      }

      .section {
        padding: 1.1rem 1.25rem;
        margin-bottom: 1.25rem;
      }

      .section h2 {
        font-size: 1.05rem;
        margin-bottom: 0.75rem;
      }

      .section h3 {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--ink-soft);
        margin: 1.1rem 0 0.4rem;
      }

      .small {
        font-size: 0.82rem;
      }

      .stale-note {
        padding: 0.55rem 0.8rem;
        background: var(--amber-soft);
        color: var(--amber);
        border-radius: 8px;
        font-size: 0.88rem;
      }

      .scores {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
      }

      .score.big {
        min-width: 130px;
        gap: 0.3rem;
      }

      .score.big .score-value {
        font-family: var(--font-display);
        font-size: 2rem;
        line-height: 1;
      }

      .verdict {
        font-size: 0.85rem;
        padding: 0.3rem 0.75rem;
      }

      .summary {
        margin-top: 1rem;
        padding: 0.8rem 1rem;
        background: var(--surface-sunken);
        border-left: 3px solid var(--accent);
        border-radius: 0 8px 8px 0;
        font-size: 0.95rem;
      }

      .two-cols {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
      }

      .ticks {
        list-style: none;
        margin: 0;
        padding: 0;
        font-size: 0.9rem;
      }

      .ticks li {
        padding: 0.25rem 0 0.25rem 1.4rem;
        position: relative;
      }

      .ticks.good li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: var(--accent);
        font-weight: 700;
      }

      .ticks.gap li::before {
        content: '○';
        position: absolute;
        left: 0;
        color: var(--amber);
        font-weight: 700;
      }

      .breakdown {
        font-size: 0.88rem;
      }

      .breakdown th {
        position: static;
        font-size: 0.68rem;
      }

      .mono {
        font-variant-numeric: tabular-nums;
        font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
        font-size: 0.85em;
      }

      .checks {
        list-style: none;
        margin: 0;
        padding: 0;
        font-size: 0.88rem;
      }

      .checks li {
        display: grid;
        grid-template-columns: 64px 52px 1fr;
        gap: 0.5rem;
        padding: 0.45rem 0.6rem;
        border-radius: 6px;
        margin-bottom: 0.3rem;
        align-items: baseline;
      }

      .checks li em {
        grid-column: 3;
        color: var(--ink-soft);
        font-size: 0.82rem;
      }

      .checks .pass {
        background: var(--accent-soft);
      }

      .checks .fail {
        background: var(--red-soft);
      }

      .checks .points {
        color: var(--ink-soft);
      }

      .anomalies {
        margin-top: 1rem;
        padding: 0.7rem 1rem;
        background: var(--red-soft);
        border-radius: 8px;
        color: var(--red);
        font-size: 0.88rem;
      }

      .anomalies h3 {
        color: var(--red);
        margin-top: 0;
      }

      .answers {
        margin: 0;
        display: grid;
        grid-template-columns: 200px 1fr;
        row-gap: 0.5rem;
        font-size: 0.92rem;
      }

      .answers dt {
        font-weight: 600;
        color: var(--ink-soft);
      }

      .answers dd {
        margin: 0;
      }

      .files {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .files li {
        display: flex;
        align-items: center;
        gap: 0.7rem;
      }

      .file-name {
        font-weight: 600;
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .cv-frame {
        width: 100%;
        height: 540px;
        margin-top: 1rem;
        border: 1px solid var(--line-strong);
        border-radius: 8px;
        background: var(--surface-sunken);
        animation: frame-in 350ms cubic-bezier(0.16, 1, 0.3, 1);
      }

      @keyframes frame-in {
        from {
          opacity: 0;
          transform: translateY(8px) scale(0.99);
        }
        to {
          opacity: 1;
          transform: translateY(0) scale(1);
        }
      }

      .stars {
        display: flex;
        gap: 0.15rem;
        margin-bottom: 0.75rem;
      }

      .star {
        border: none;
        background: none;
        font-size: 1.6rem;
        line-height: 1;
        color: var(--line-strong);
        cursor: pointer;
        padding: 0.1rem;
        transition:
          transform 150ms cubic-bezier(0.16, 1, 0.3, 1),
          color 150ms cubic-bezier(0.16, 1, 0.3, 1);
      }

      .star:hover {
        transform: scale(1.18);
        color: var(--amber);
      }

      .star.on {
        color: var(--amber);
      }

      .timeline {
        list-style: none;
        margin: 0;
        padding: 0 0 0 0.9rem;
        border-left: 2px solid var(--line);
        font-size: 0.88rem;
      }

      .timeline li {
        position: relative;
        padding: 0 0 1rem 0.4rem;
      }

      .timeline li::before {
        content: '';
        position: absolute;
        left: -1.05rem;
        top: 0.4rem;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--accent);
        border: 2px solid var(--surface);
      }

      .event-detail {
        margin-top: 0.2rem;
      }
    `,
  ],
})
export class CandidateFichePage implements OnInit {
  readonly app = signal<ApplicationFiche | null>(null);
  readonly events = signal<ApplicationEvent[]>([]);
  readonly error = signal<string | null>(null);
  readonly prev = signal<ApplicationRow | null>(null);
  readonly next = signal<ApplicationRow | null>(null);
  readonly previewUrl = signal<SafeResourceUrl | null>(null);
  private readonly previewRaw = signal<string | null>(null);

  readonly statuses = STATUSES;
  readonly statusLabels = STATUS_LABELS;
  readonly VERDICT_LABELS = VERDICT_LABELS;
  readonly VERDICT_BADGES = VERDICT_BADGES;
  readonly EVENT_LABELS = EVENT_LABELS;
  readonly EVENT_BADGES = EVENT_BADGES;

  noteDraft = '';

  offerId = 0;
  appId = 0;
  private filters: ListFilters = EMPTY_FILTERS;
  private sort = '-match_score';
  private page = 1;

  constructor(
    private readonly api: ApplicationsService,
    private readonly offersApi: OffersService,
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly sanitizer: DomSanitizer,
  ) {}

  ngOnInit(): void {
    this.offerId = Number(this.route.snapshot.paramMap.get('id'));
    this.appId = Number(this.route.snapshot.paramMap.get('appId'));

    const params = this.route.snapshot.queryParamMap;
    this.filters = parseFilterParams(
      Object.fromEntries(params.keys.map((k) => [k, params.get(k)])),
    );
    this.sort = params.get('sort') ?? '-match_score';
    this.page = Number(params.get('page')) || 1;

    void this.load();
    void this.loadNeighbours();
  }

  async load(): Promise<void> {
    try {
      this.app.set(await this.api.get(this.appId));
      this.events.set(await this.api.events(this.appId));
      this.error.set(null);
    } catch {
      this.error.set('Impossible de charger la fiche du candidat.');
    }
  }

  async loadNeighbours(): Promise<void> {
    try {
      const list = await this.offersApi.listApplications(
        this.offerId,
        toListParams(this.filters, this.sort, this.page),
      );
      const index = list.data.findIndex((row) => row.id === this.appId);
      if (index === -1) {
        this.prev.set(null);
        this.next.set(null);
        return;
      }
      this.prev.set(index > 0 ? list.data[index - 1] : null);
      this.next.set(index < list.data.length - 1 ? list.data[index + 1] : null);

      if (index === 0 && list.meta.current_page > 1) {
        const before = await this.offersApi.listApplications(
          this.offerId,
          toListParams(this.filters, this.sort, list.meta.current_page - 1),
        );
        this.prev.set(before.data[before.data.length - 1] ?? null);
      }
      if (index === list.data.length - 1 && list.meta.current_page < list.meta.last_page) {
        const after = await this.offersApi.listApplications(
          this.offerId,
          toListParams(this.filters, this.sort, list.meta.current_page + 1),
        );
        this.next.set(after.data[0] ?? null);
      }
    } catch {
      this.prev.set(null);
      this.next.set(null);
    }
  }

  open(id: number): void {
    void this.router.navigate(['/app/offers', this.offerId, 'applications', id], {
      queryParams: this.listQuery(),
    });
  }

  listQuery(): Record<string, unknown> {
    return {
      ...filterQueryParams(this.filters),
      sort: this.sort === '-match_score' ? undefined : this.sort,
      page: this.page > 1 ? this.page : undefined,
    };
  }

  async changeStatus(status: string): Promise<void> {
    const current = this.app();
    if (!current || current.status === status) return;
    try {
      await this.api.setStatus(current.id, status);
      this.app.set({ ...current, status });
      this.events.set(await this.api.events(current.id));
    } catch {
      this.error.set('Changement de statut impossible.');
    }
  }

  async rate(star: number): Promise<void> {
    const current = this.app();
    if (!current || current.rating === star) return;
    try {
      const saved = await this.api.addNote(current.id, { rating: star });
      this.app.set({ ...current, rating: saved });
    } catch {
      this.error.set('Enregistrement impossible.');
    }
  }

  async saveNote(): Promise<void> {
    const current = this.app();
    if (!current || !this.noteDraft.trim()) return;
    try {
      await this.api.addNote(current.id, { note: this.noteDraft.trim() });
      this.noteDraft = '';
      this.events.set(await this.api.events(current.id));
    } catch {
      this.error.set('Enregistrement impossible.');
    }
  }

  async reanalyze(): Promise<void> {
    try {
      const response = await fetch(`/api/v1/applications/${this.appId}/reanalyze`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-XSRF-TOKEN': this.xsrfToken(),
          Accept: 'application/json',
        },
      });
      if (response.ok) {
        await this.load();
      } else {
        this.error.set('Relance impossible.');
      }
    } catch {
      this.error.set('Relance impossible.');
    }
  }

  async preview(file: FicheFile): Promise<void> {
    this.closePreview();
    try {
      const url = await this.api.blobUrl(file.url);
      this.previewRaw.set(url);
      this.previewUrl.set(this.sanitizer.bypassSecurityTrustResourceUrl(url));
    } catch {
      this.error.set('Aperçu indisponible.');
    }
  }

  async download(file: FicheFile): Promise<void> {
    try {
      const response = await fetch(file.url, { credentials: 'include' });
      if (!response.ok) throw new Error('download failed');
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = file.name;
      anchor.click();
      URL.revokeObjectURL(url);
    } catch {
      this.error.set('Téléchargement impossible.');
    }
  }

  isPdf(file: FicheFile): boolean {
    return file.mime === 'application/pdf';
  }

  breakdownEntries(breakdown: Record<string, { score: number; weight: number; evidence: string }>) {
    return Object.entries(breakdown).map(([key, value]) => ({ key, value }));
  }

  criteriaLabel(key: string): string {
    return CRITERIA_LABELS[key] ?? key;
  }

  eventDetail(event: ApplicationEvent): string {
    const payload = event.payload;
    if (event.type === 'status_changed') {
      const from = String(payload['from'] ?? '');
      const to = String(payload['to'] ?? '');
      return `${this.statusLabels[from] ?? from} → ${this.statusLabels[to] ?? to}`;
    }
    if (event.type === 'analysis_completed') {
      const parts = [];
      if (payload['match_score'] != null) parts.push(`correspondance ${payload['match_score']}%`);
      if (payload['ats_score'] != null) parts.push(`ATS ${payload['ats_score']}`);
      return parts.join(' · ');
    }
    if (event.type === 'note_added') {
      const note = typeof payload['note'] === 'string' ? payload['note'] : '';
      const rating = payload['rating'];
      return [rating != null ? `★ ${String(rating)}/5` : '', note].filter(Boolean).join(' — ');
    }
    return '';
  }

  formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} o`;
    return `${Math.round(bytes / 1024)} Ko`;
  }

  private xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
  }

  private closePreview(): void {
    const url = this.previewRaw();
    if (url) URL.revokeObjectURL(url);
    this.previewUrl.set(null);
    this.previewRaw.set(null);
  }
}
