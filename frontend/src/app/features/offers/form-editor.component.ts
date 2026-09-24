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
    <h2>Application form</h2>
    @if (error()) {
      <p role="alert">{{ error() }}</p>
    }
    @if (notice()) {
      <p role="status">{{ notice() }}</p>
    }
    <ol>
      @for (field of fields(); track field.key; let i = $index) {
        <li>
          <strong>{{ field.label }}</strong> ({{ field.key }} · {{ field.type }})
          @if (field.is_locked) {
            <em>locked</em>
          }
          @if (field.is_sensitive) {
            <em>sensitive</em>
          }
          <label
            ><input type="checkbox" [(ngModel)]="field.is_required" [disabled]="field.is_locked" />
            required</label
          >
          <label
            ><input type="checkbox" [(ngModel)]="field.is_hidden" [disabled]="field.is_locked" />
            hidden</label
          >
          <button type="button" (click)="move(i, -1)" [disabled]="i === 0">↑</button>
          <button type="button" (click)="move(i, 1)" [disabled]="i === fields().length - 1">
            ↓
          </button>
          @if (!field.is_locked) {
            <button type="button" (click)="remove(i)">Remove</button>
          }
        </li>
      }
    </ol>
    <p>
      <select [(ngModel)]="selectedKey">
        @for (c of availableCatalog(); track c.key) {
          <option [value]="c.key">{{ c.label }} ({{ c.type }})</option>
        }
      </select>
      <button type="button" (click)="add()" [disabled]="!selectedKey">Add field</button>
      <button type="button" (click)="save()" [disabled]="busy()">Save form</button>
    </p>
  `,
})
export class FormEditorComponent implements OnInit {
  @Input({ required: true }) offerId!: number;

  readonly fields = signal<EditableField[]>([]);
  readonly catalog = signal<CatalogField[]>([]);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);
  readonly busy = signal(false);
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
      this.error.set('Could not load form configuration.');
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

  remove(index: number): void {
    this.fields.update((list) => list.filter((_, i) => i !== index));
  }

  async save(): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    this.notice.set(null);
    try {
      const payload = this.fields().map(({ fromCatalog, id, ...rest }) => rest);
      const saved = await this.api.saveFormFields(this.offerId, payload);
      this.fields.set(saved.map((f) => ({ ...f, fromCatalog: true })));
      this.notice.set('Form saved.');
    } catch {
      this.error.set(
        'Save failed. Locked fields (name, email, CV) must stay present and required.',
      );
    } finally {
      this.busy.set(false);
    }
  }
}
