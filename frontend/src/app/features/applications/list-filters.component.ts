import { Component, computed, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SkillFacet } from '../../core/api/offers.service';
import {
  STATUSES,
  STATUS_BADGES,
  STATUS_LABELS,
  VERDICTS,
  VERDICT_LABELS,
} from './application-labels';

export interface ListFilters {
  q: string;
  min_score: string;
  max_score: string;
  ats: string;
  ats_min: string;
  ats_max: string;
  skills: string[];
  skills_mode: 'any' | 'all';
  exp_min: string;
  exp_max: string;
  city: string;
  country: string;
  status: string[];
  applied_from: string;
  applied_to: string;
}

export const EMPTY_FILTERS: ListFilters = {
  q: '',
  min_score: '',
  max_score: '',
  ats: '',
  ats_min: '',
  ats_max: '',
  skills: [],
  skills_mode: 'any',
  exp_min: '',
  exp_max: '',
  city: '',
  country: '',
  status: [],
  applied_from: '',
  applied_to: '',
};

export interface FilterPatch {
  patch: Partial<ListFilters>;
  debounce?: boolean;
}

const SCALAR_KEYS = [
  'min_score',
  'max_score',
  'ats',
  'ats_min',
  'ats_max',
  'exp_min',
  'exp_max',
  'city',
  'country',
  'applied_from',
  'applied_to',
] as const;

export function activeFilterCount(f: ListFilters): number {
  let count = f.q ? 1 : 0;
  for (const key of SCALAR_KEYS) {
    if (f[key]) count++;
  }
  if (f.skills.length) count++;
  if (f.status.length) count++;
  if (f.skills.length && f.skills_mode === 'all') count++;
  return count;
}

@Component({
  selector: 'app-list-filters',
  standalone: true,
  imports: [FormsModule],
  template: `
    <div class="filter-toolbar">
      <button
        type="button"
        class="btn"
        [class.btn-primary]="open()"
        (click)="open.set(!open())"
        [attr.aria-expanded]="open()"
      >
        Filtres
        @if (activeCount() > 0) {
          <span class="badge badge-green">{{ activeCount() }}</span>
        }
      </button>
      @if (activeCount() > 0) {
        <button type="button" class="btn btn-ghost" (click)="reset.emit()">Réinitialiser</button>
      }
    </div>

    <div class="tray" [class.tray-open]="open()">
      <div class="tray-inner">
        <div class="filter-grid">
          <fieldset>
            <legend>Score de correspondance</legend>
            <div class="row2">
              <label>
                Min
                <input
                  type="number"
                  min="0"
                  max="100"
                  placeholder="0"
                  [ngModel]="f().min_score"
                  (ngModelChange)="emit('min_score', $event, true)"
                />
              </label>
              <label>
                Max
                <input
                  type="number"
                  min="0"
                  max="100"
                  placeholder="100"
                  [ngModel]="f().max_score"
                  (ngModelChange)="emit('max_score', $event, true)"
                />
              </label>
            </div>
          </fieldset>

          <fieldset>
            <legend>Score ATS</legend>
            <label>
              Verdict
              <select [ngModel]="f().ats" (ngModelChange)="emit('ats', $event)">
                <option value="">Tous</option>
                @for (v of verdicts; track v) {
                  <option [value]="v">{{ verdictLabels[v] }}</option>
                }
              </select>
            </label>
            <div class="row2">
              <label>
                Min
                <input
                  type="number"
                  min="0"
                  max="100"
                  placeholder="0"
                  [ngModel]="f().ats_min"
                  (ngModelChange)="emit('ats_min', $event, true)"
                />
              </label>
              <label>
                Max
                <input
                  type="number"
                  min="0"
                  max="100"
                  placeholder="100"
                  [ngModel]="f().ats_max"
                  (ngModelChange)="emit('ats_max', $event, true)"
                />
              </label>
            </div>
          </fieldset>

          <fieldset>
            <legend>Expérience (années)</legend>
            <div class="row2">
              <label>
                Min
                <input
                  type="number"
                  min="0"
                  max="60"
                  placeholder="0"
                  [ngModel]="f().exp_min"
                  (ngModelChange)="emit('exp_min', $event, true)"
                />
              </label>
              <label>
                Max
                <input
                  type="number"
                  min="0"
                  max="60"
                  placeholder="60"
                  [ngModel]="f().exp_max"
                  (ngModelChange)="emit('exp_max', $event, true)"
                />
              </label>
            </div>
          </fieldset>

          <fieldset>
            <legend>Lieu</legend>
            <label>
              Ville
              <input
                type="text"
                placeholder="Casablanca"
                [ngModel]="f().city"
                (ngModelChange)="emit('city', $event, true)"
              />
            </label>
            <label>
              Pays
              <input
                type="text"
                placeholder="Maroc"
                [ngModel]="f().country"
                (ngModelChange)="emit('country', $event, true)"
              />
            </label>
          </fieldset>

          <fieldset>
            <legend>Dates de candidature</legend>
            <div class="row2">
              <label>
                Du
                <input
                  type="date"
                  [ngModel]="f().applied_from"
                  (ngModelChange)="emit('applied_from', $event)"
                />
              </label>
              <label>
                Au
                <input
                  type="date"
                  [ngModel]="f().applied_to"
                  (ngModelChange)="emit('applied_to', $event)"
                />
              </label>
            </div>
          </fieldset>

          <fieldset>
            <legend>Statut</legend>
            <div class="chip-row">
              @for (s of statuses; track s) {
                <button
                  type="button"
                  class="chip chip-toggle"
                  [class.chip-on]="f().status.includes(s)"
                  [attr.aria-pressed]="f().status.includes(s)"
                  (click)="toggleStatus(s)"
                >
                  <span class="dot" [class]="'dot ' + statusBadges[s]"></span>
                  {{ statusLabels[s] }}
                </button>
              }
            </div>
          </fieldset>

          @if (facets().length > 0) {
            <fieldset class="wide">
              <legend>
                Compétences
                @if (f().skills.length > 0) {
                  <span class="muted small">({{ f().skills.length }} sélectionnée(s))</span>
                  <span class="mode-switch">
                    <button
                      type="button"
                      class="chip chip-toggle"
                      [class.chip-on]="f().skills_mode === 'any'"
                      (click)="emit('skills_mode', 'any')"
                    >
                      Au moins une
                    </button>
                    <button
                      type="button"
                      class="chip chip-toggle"
                      [class.chip-on]="f().skills_mode === 'all'"
                      (click)="emit('skills_mode', 'all')"
                    >
                      Toutes
                    </button>
                  </span>
                }
              </legend>
              <div class="chip-row">
                @for (skill of facets(); track skill.slug) {
                  <button
                    type="button"
                    class="chip chip-toggle"
                    [class.chip-on]="f().skills.includes(skill.slug)"
                    [attr.aria-pressed]="f().skills.includes(skill.slug)"
                    (click)="toggleSkill(skill.slug)"
                  >
                    {{ skill.name }}
                    <span class="muted">{{ skill.applications_count }}</span>
                  </button>
                }
              </div>
            </fieldset>
          }
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .filter-toolbar {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      .tray {
        display: grid;
        grid-template-rows: 0fr;
        opacity: 0;
        transition:
          grid-template-rows 350ms cubic-bezier(0.16, 1, 0.3, 1),
          opacity 250ms cubic-bezier(0.16, 1, 0.3, 1);
      }

      .tray-open {
        grid-template-rows: 1fr;
        opacity: 1;
      }

      .tray-inner {
        overflow: hidden;
        min-height: 0;
      }

      .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
        gap: 1rem 1.5rem;
        margin-top: 0.75rem;
        padding: 1.1rem 1.2rem;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 10px;
        box-shadow: var(--shadow-sm);
      }

      fieldset {
        border: none;
        margin: 0;
        padding: 0;
        min-width: 0;
      }

      fieldset.wide {
        grid-column: 1 / -1;
      }

      legend {
        padding: 0;
        margin-bottom: 0.45rem;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--ink-soft);
      }

      .row2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem;
      }

      .chip-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
      }

      .chip-toggle {
        cursor: pointer;
        font-family: var(--font-body);
        transition:
          background 150ms cubic-bezier(0.16, 1, 0.3, 1),
          border-color 150ms cubic-bezier(0.16, 1, 0.3, 1),
          color 150ms cubic-bezier(0.16, 1, 0.3, 1),
          transform 150ms cubic-bezier(0.16, 1, 0.3, 1);
      }

      .chip-toggle:hover {
        border-color: var(--accent);
        transform: translateY(-1px);
      }

      .chip-on {
        background: var(--accent-soft);
        border-color: var(--accent);
        color: var(--accent);
        font-weight: 600;
      }

      .dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--ink-faint);
      }

      .mode-switch {
        display: inline-flex;
        gap: 0.35rem;
        margin-left: 0.6rem;
        vertical-align: middle;
      }

      .small {
        font-size: 0.72rem;
        text-transform: none;
        letter-spacing: normal;
        font-weight: 500;
      }

      @media (max-width: 640px) {
        .filter-grid {
          grid-template-columns: 1fr;
        }
      }
    `,
  ],
})
export class ListFiltersComponent {
  readonly f = input.required<ListFilters>();
  readonly facets = input<SkillFacet[]>([]);

  readonly change = output<FilterPatch>();
  readonly reset = output<void>();

  readonly open = signal(false);
  readonly activeCount = computed(() => activeFilterCount(this.f()));

  readonly statuses = STATUSES;
  readonly verdicts = VERDICTS;
  readonly statusLabels = STATUS_LABELS;
  readonly statusBadges = STATUS_BADGES;
  readonly verdictLabels = VERDICT_LABELS;

  emit<K extends keyof ListFilters>(key: K, value: ListFilters[K], debounce = false): void {
    this.change.emit({ patch: { [key]: value } as Partial<ListFilters>, debounce });
  }

  toggleStatus(status: string): void {
    const current = this.f().status;
    const next = current.includes(status)
      ? current.filter((s) => s !== status)
      : [...current, status];
    this.change.emit({ patch: { status: next } });
  }

  toggleSkill(slug: string): void {
    const current = this.f().skills;
    const next = current.includes(slug) ? current.filter((s) => s !== slug) : [...current, slug];
    this.change.emit({ patch: { skills: next } });
  }
}
