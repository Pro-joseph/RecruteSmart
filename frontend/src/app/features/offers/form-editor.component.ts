import { Component, Input, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CatalogField, OfferFormField, OffersService } from '../../core/api/offers.service';

interface EditableField extends OfferFormField {
  fromCatalog: boolean;
}

@Component({
  selector: 'app-form-editor',
  standalone: true,
  imports: [FormsModule],
  template: `
    <h2>Formulaire de candidature</h2>
    @if (error()) {
      <p role="alert" class="alert">{{ error() }}</p>
    }
    @if (notice()) {
      <p role="status">{{ notice() }}</p>
    }

    @if (loading()) {
      <ul class="item-list" aria-label="Chargement du formulaire">
        @for (r of [1, 2, 3]; track r) {
          <li class="item-row">
            <span class="skeleton" style="width: 45%"></span>
            <span class="skeleton" style="width: 20%"></span>
          </li>
        }
      </ul>
    } @else if (fields().length === 0) {
      <div class="empty form-empty">
        <h3>Formulaire vide</h3>
        <p>
          Ajoutez des champs depuis le catalogue ci-dessous — nom, e-mail et CV sont ajoutés
          automatiquement à la publication.
        </p>
      </div>
    } @else {
      <ul class="item-list field-list">
        @for (field of fields(); track field.key; let i = $index) {
          <li class="item-row field-row" [class.tumble-out]="removingKey() === field.key">
            @if (confirmKey() === field.key) {
              <span class="confirm-text">
                Retirer <strong>{{ field.label }}</strong> du formulaire ?
              </span>
              <span class="row-actions">
                <button type="button" class="btn btn-danger" (click)="removeConfirmed(i)">
                  Retirer
                </button>
                <button type="button" class="btn btn-ghost" (click)="confirmKey.set(null)">
                  Annuler
                </button>
              </span>
            } @else {
              <span class="field-main">
                <strong>{{ field.label }}</strong>
                <span class="muted small">{{ field.key }} · {{ field.type }}</span>
                @if (field.is_locked) {
                  <span class="badge badge-amber">Verrouillé</span>
                }
                @if (field.is_sensitive) {
                  <span class="badge badge-indigo">Sensible</span>
                }
              </span>
              <span class="row-actions">
                <label class="mini-toggle">
                  <input
                    type="checkbox"
                    [(ngModel)]="field.is_required"
                    [disabled]="field.is_locked"
                  />
                  Requis
                </label>
                <label class="mini-toggle">
                  <input
                    type="checkbox"
                    [(ngModel)]="field.is_hidden"
                    [disabled]="field.is_locked"
                  />
                  Masqué
                </label>
                <button
                  type="button"
                  class="btn btn-ghost"
                  aria-label="Monter"
                  [disabled]="i === 0"
                  (click)="move(i, -1)"
                >
                  ↑
                </button>
                <button
                  type="button"
                  class="btn btn-ghost"
                  aria-label="Descendre"
                  [disabled]="i === fields().length - 1"
                  (click)="move(i, 1)"
                >
                  ↓
                </button>
                @if (!field.is_locked) {
                  <button type="button" class="btn btn-danger" (click)="confirmKey.set(field.key)">
                    Retirer
                  </button>
                }
              </span>
            }
          </li>
        }
      </ul>
    }

    <div class="row-actions form-actions">
      <select [(ngModel)]="selectedKey" aria-label="Champ à ajouter">
        @for (c of availableCatalog(); track c.key) {
          <option [value]="c.key">{{ c.label }} ({{ c.type }})</option>
        }
        @if (availableCatalog().length === 0) {
          <option value="">Tous les champs ajoutés</option>
        }
      </select>
      <button type="button" class="btn" [disabled]="!selectedKey" (click)="add()">Ajouter</button>
      <button
        type="button"
        class="btn btn-primary"
        [disabled]="busy() || loading()"
        [class.is-busy]="busy()"
        (click)="save()"
      >
        {{ busy() ? 'Enregistrement…' : 'Enregistrer le formulaire' }}
      </button>
    </div>
  `,
})
export class FormEditorComponent implements OnInit {
  @Input({ required: true }) offerId!: number;

  readonly fields = signal<EditableField[]>([]);
  readonly catalog = signal<CatalogField[]>([]);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);
  readonly busy = signal(false);
  readonly loading = signal(true);
  readonly confirmKey = signal<string | null>(null);
  readonly removingKey = signal<string | null>(null);
  selectedKey = '';

  constructor(private readonly api: OffersService) {}

  ngOnInit(): void {
    void this.load();
  }

  availableCatalog(): CatalogField[] {
    const used = new Set(this.fields().map((f) => f.key));
    return this.catalog().filter((c) => !used.has(c.key));
  }

  async load(): Promise<void> {
    this.error.set(null);
    this.loading.set(true);
    try {
      const [catalog, fields] = await Promise.all([
        this.api.catalog(),
        this.api.formFields(this.offerId),
      ]);
      this.catalog.set(catalog);
      this.fields.set(
        fields.map((f) => ({ ...f, fromCatalog: catalog.some((c) => c.key === f.key) })),
      );
    } catch {
      this.error.set('Impossible de charger la configuration du formulaire.');
    } finally {
      this.loading.set(false);
    }
  }

  add(): void {
    const def = this.catalog().find((c) => c.key === this.selectedKey);
    if (!def) return;
    this.fields.update((list) => [
      ...list,
      {
        key: def.key,
        label: def.label,
        type: def.type,
        is_required: def.required,
        is_locked: def.locked,
        is_sensitive: def.sensitive,
        is_hidden: false,
        position: list.length,
        fromCatalog: true,
      },
    ]);
    this.selectedKey = '';
  }

  move(index: number, delta: number): void {
    this.fields.update((list) => {
      const next = [...list];
      const j = index + delta;
      [next[index], next[j]] = [next[j], next[index]];
      return next.map((f, position) => ({ ...f, position }));
    });
  }

  removeConfirmed(index: number): void {
    const field = this.fields()[index];
    if (!field) return;
    this.removingKey.set(field.key);
    this.confirmKey.set(null);
    setTimeout(() => {
      this.fields.update((list) => list.filter((_, i) => i !== index));
      this.removingKey.set(null);
    }, 320);
  }

  async save(): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    this.notice.set(null);
    try {
      // is_locked / is_sensitive are server-managed (SyncFormFieldsRequest prohibits them).
      const payload = this.fields().map(
        ({ fromCatalog, id, is_locked, is_sensitive, ...rest }) => rest,
      );
      const saved = await this.api.saveFormFields(this.offerId, payload);
      this.fields.set(saved.map((f) => ({ ...f, fromCatalog: true })));
      this.notice.set('Formulaire enregistré.');
    } catch (err) {
      this.error.set(this.readSaveError(err));
    } finally {
      this.busy.set(false);
    }
  }

  private readSaveError(err: unknown): string {
    const body = (err as { error?: { message?: string; errors?: Record<string, string[]> } })
      ?.error;
    if (body?.message) return body.message;
    const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
    return (
      first ??
      'Échec de l’enregistrement. Les champs verrouillés (nom, e-mail, CV) doivent rester présents et requis.'
    );
  }
}
