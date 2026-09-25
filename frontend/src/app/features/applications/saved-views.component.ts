import { Component, effect, input, output, signal, untracked } from '@angular/core';
import { FormsModule } from '@angular/forms';

export interface SavedView {
  name: string;
  query: Record<string, string | string[]>;
}

const STORAGE_PREFIX = 'recrutesmart.saved-views.v1.';

export function loadViews(offerId: number): SavedView[] {
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + offerId);
    const parsed: unknown = raw ? JSON.parse(raw) : [];
    if (!Array.isArray(parsed)) return [];
    return parsed.filter(
      (v): v is SavedView =>
        !!v &&
        typeof v === 'object' &&
        typeof (v as SavedView).name === 'string' &&
        typeof (v as SavedView).query === 'object',
    );
  } catch {
    return [];
  }
}

function writeViews(offerId: number, views: SavedView[]): void {
  try {
    localStorage.setItem(STORAGE_PREFIX + offerId, JSON.stringify(views));
  } catch {
    /* private mode or quota exceeded: views are a local convenience */
  }
}

/** EF-809: save a filter+sort combination and reapply it in one click. */
@Component({
  selector: 'app-saved-views',
  standalone: true,
  imports: [FormsModule],
  template: `
    <select
      [ngModel]="selected()"
      (ngModelChange)="apply($event)"
      aria-label="Vues enregistrées"
      title="Vues enregistrées"
    >
      <option value="">Vues enregistrées</option>
      @for (view of views(); track view.name) {
        <option [value]="view.name">{{ view.name }}</option>
      }
    </select>

    @if (editing()) {
      <input
        type="text"
        class="view-name"
        placeholder="Nom de la vue"
        [(ngModel)]="draft"
        (keyup.enter)="save()"
        (keyup.escape)="cancel()"
        autofocus
      />
      <button type="button" class="btn btn-primary" (click)="save()">Enregistrer</button>
      <button type="button" class="btn btn-ghost" (click)="cancel()">Annuler</button>
    } @else {
      <button
        type="button"
        class="btn"
        (click)="startSave()"
        [disabled]="isEmpty()"
        title="Enregistrer les filtres actuels"
      >
        + Vue
      </button>
    }

    @if (selected()) {
      <button
        type="button"
        class="btn btn-ghost"
        (click)="removeSelected()"
        title="Supprimer cette vue"
      >
        ×
      </button>
    }
  `,
  styles: [
    `
      :host {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: wrap;
      }

      select {
        max-width: 190px;
      }

      .view-name {
        width: 170px;
      }
    `,
  ],
})
export class SavedViewsComponent {
  readonly offerId = input.required<number>();
  readonly query = input.required<Record<string, string | string[]>>();

  readonly applyView = output<Record<string, string | string[]>>();

  readonly views = signal<SavedView[]>([]);
  readonly selected = signal('');
  readonly editing = signal(false);

  draft = '';

  constructor() {
    effect(() => {
      const id = this.offerId();
      untracked(() => {
        this.views.set(loadViews(id));
        this.selected.set('');
      });
    });
  }

  isEmpty(): boolean {
    return Object.keys(this.query()).length === 0;
  }

  apply(name: string): void {
    this.selected.set(name);
    const view = this.views().find((v) => v.name === name);
    if (view) this.applyView.emit(view.query);
  }

  startSave(): void {
    this.draft = this.selected() || '';
    this.editing.set(true);
  }

  cancel(): void {
    this.editing.set(false);
  }

  save(): void {
    const name = this.draft.trim();
    if (!name) return;
    const query = { ...this.query() };
    delete query['page'];
    const views = this.views().filter((v) => v.name !== name);
    views.push({ name, query });
    views.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
    this.views.set(views);
    writeViews(this.offerId(), views);
    this.selected.set(name);
    this.editing.set(false);
  }

  removeSelected(): void {
    const name = this.selected();
    if (!name) return;
    const views = this.views().filter((v) => v.name !== name);
    this.views.set(views);
    writeViews(this.offerId(), views);
    this.selected.set('');
  }
}
