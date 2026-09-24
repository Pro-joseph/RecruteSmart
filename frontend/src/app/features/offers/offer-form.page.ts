import { Component, OnInit, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Offer, OffersService } from '../../core/api/offers.service';
import { FormEditorComponent } from './form-editor.component';

const KNOCKOUT_KEYS = [
  'required_skills',
  'preferred_skills',
  'experience',
  'education',
  'languages',
];
const WEIGHT_KEYS = ['required_skills', 'preferred_skills', 'experience', 'education', 'languages'];
const OFFER_TYPES = [
  'full_time',
  'part_time',
  'contract',
  'internship',
  'apprenticeship',
  'freelance',
  'other',
];

@Component({
  selector: 'app-offer-form',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, FormEditorComponent],
  template: `
    <p><a routerLink="/app/offers">← Offers</a></p>
    <h1>{{ isNew() ? 'New offer' : 'Edit offer' }}</h1>
    @if (error()) {
      <p role="alert">{{ error() }}</p>
    }
    <form [formGroup]="form" (ngSubmit)="submit()">
      <label>Title <input formControlName="title" /></label>
      <label
        >Type
        <select formControlName="type">
          @for (t of types; track t) {
            <option [value]="t">{{ t }}</option>
          }
        </select>
      </label>
      @if (form.controls.type.value === 'other') {
        <label>Type label <input formControlName="type_label" /></label>
      }
      <label>Description <textarea formControlName="description"></textarea></label>
      <label>Missions <textarea formControlName="missions"></textarea></label>
      <label>Wanted profile <textarea formControlName="profile_wanted"></textarea></label>
      <label>City <input formControlName="city" /></label>
      <label>Country <input formControlName="country" /></label>
      <label
        >Work mode
        <select formControlName="work_mode">
          <option value="">—</option>
          <option value="onsite">onsite</option>
          <option value="hybrid">hybrid</option>
          <option value="remote">remote</option>
        </select>
      </label>
      <label>Salary min <input type="number" formControlName="salary_min" /></label>
      <label>Salary max <input type="number" formControlName="salary_max" /></label>
      <label>Currency <input formControlName="salary_currency" maxlength="3" /></label>
      <label>Positions <input type="number" formControlName="positions_count" /></label>
      <label>Required skills (comma-separated) <input formControlName="required_skills" /></label>
      <label>Preferred skills (comma-separated) <input formControlName="preferred_skills" /></label>
      <label
        >Min experience (years)
        <input type="number" step="0.5" formControlName="min_experience_years"
      /></label>
      <label>Education level <input formControlName="education_level" /></label>
      <label>Languages (name:level, comma-separated) <input formControlName="languages" /></label>
      <fieldset>
        <legend>Knockout criteria</legend>
        @for (k of knockoutKeys; track k) {
          <label
            ><input
              type="checkbox"
              [checked]="knockout().includes(k)"
              (change)="toggleKnockout(k)"
            />
            {{ k }}</label
          >
        }
      </fieldset>
      <fieldset>
        <legend>Scoring weights</legend>
        @for (k of weightKeys; track k) {
          <label
            >{{ k }}
            <input
              type="number"
              [value]="weights()[k]"
              (input)="setWeight(k, $event)"
              min="0"
              max="100"
          /></label>
        }
      </fieldset>
      <label>Deadline <input type="datetime-local" formControlName="deadline_at" /></label>
      <button type="submit" [disabled]="form.invalid || busy()">Save</button>
    </form>

    @if (offer(); as o) {
      <h2>Publication</h2>
      <p>Status: {{ o.status }} · criteria v{{ o.criteria_version }}</p>
      @if (o.public_url) {
        <p>
          Link: <a [href]="o.public_url" target="_blank">{{ o.public_url }}</a>
          <button type="button" (click)="copy(o.public_url!)">Copy</button>
          <button type="button" (click)="regenerate()">Regenerate link</button>
        </p>
      } @else {
        <button type="button" (click)="publish()">Publish (generate link)</button>
      }
      <app-form-editor [offerId]="o.id" />
    }
  `,
})
export class OfferFormPage implements OnInit {
  readonly types = OFFER_TYPES;
  readonly knockoutKeys = KNOCKOUT_KEYS;
  readonly weightKeys = WEIGHT_KEYS;

  readonly form = new FormGroup({
    title: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255)],
    }),
    type: new FormControl('full_time', { nonNullable: true, validators: [Validators.required] }),
    type_label: new FormControl('', { nonNullable: true }),
    description: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
    missions: new FormControl('', { nonNullable: true }),
    profile_wanted: new FormControl('', { nonNullable: true }),
    city: new FormControl('', { nonNullable: true }),
    country: new FormControl('', { nonNullable: true }),
    work_mode: new FormControl('', { nonNullable: true }),
    salary_min: new FormControl('', { nonNullable: true }),
    salary_max: new FormControl('', { nonNullable: true }),
    salary_currency: new FormControl('', { nonNullable: true }),
    positions_count: new FormControl('1', { nonNullable: true }),
    required_skills: new FormControl('', { nonNullable: true }),
    preferred_skills: new FormControl('', { nonNullable: true }),
    min_experience_years: new FormControl('', { nonNullable: true }),
    education_level: new FormControl('', { nonNullable: true }),
    languages: new FormControl('', { nonNullable: true }),
    deadline_at: new FormControl('', { nonNullable: true }),
  });

  readonly offer = signal<Offer | null>(null);
  readonly knockout = signal<string[]>([]);
  readonly weights = signal<Record<string, number>>({});
  readonly error = signal<string | null>(null);
  readonly busy = signal(false);

  constructor(
    private readonly api: OffersService,
    private readonly route: ActivatedRoute,
    private readonly router: Router,
  ) {}

  isNew(): boolean {
    return this.offer() === null && this.route.snapshot.paramMap.get('id') === null;
  }

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) void this.load(Number(id));
  }

  async load(id: number): Promise<void> {
    const o = await this.api.get(id);
    this.offer.set(o);
    this.knockout.set(o.knockout_criteria ?? []);
    this.weights.set({ ...(o.scoring_weights ?? {}) });
    this.form.patchValue({
      title: o.title,
      type: o.type,
      type_label: o.type_label ?? '',
      description: o.description,
      missions: o.missions ?? '',
      profile_wanted: o.profile_wanted ?? '',
      city: o.city ?? '',
      country: o.country ?? '',
      work_mode: o.work_mode ?? '',
      salary_min: o.salary_min ?? '',
      salary_max: o.salary_max ?? '',
      salary_currency: o.salary_currency ?? '',
      positions_count: String(o.positions_count),
      required_skills: (o.required_skills ?? []).join(', '),
      preferred_skills: (o.preferred_skills ?? []).join(', '),
      min_experience_years: o.min_experience_years != null ? String(o.min_experience_years) : '',
      education_level: o.education_level ?? '',
      languages: (o.languages ?? [])
        .map((l) => (l.level ? `${l.name}:${l.level}` : l.name))
        .join(', '),
      deadline_at: o.deadline_at ? o.deadline_at.slice(0, 16) : '',
    });
  }

  toggleKnockout(key: string): void {
    this.knockout.update((list) =>
      list.includes(key) ? list.filter((k) => k !== key) : [...list, key],
    );
  }

  setWeight(key: string, event: Event): void {
    const value = Number((event.target as HTMLInputElement).value);
    this.weights.update((w) => ({ ...w, [key]: value }));
  }

  private csv(value: string): string[] {
    return value
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
  }

  private payload(): Partial<Offer> {
    const v = this.form.getRawValue();
    const num = (s: string): number | undefined => (s === '' ? undefined : Number(s));
    const payload: Partial<Offer> & Record<string, unknown> = {
      title: v.title,
      type: v.type,
      type_label: v.type_label || undefined,
      description: v.description,
      missions: v.missions || undefined,
      profile_wanted: v.profile_wanted || undefined,
      city: v.city || undefined,
      country: v.country || undefined,
      work_mode: v.work_mode || undefined,
      salary_min: num(v.salary_min) as unknown as string,
      salary_max: num(v.salary_max) as unknown as string,
      salary_currency: v.salary_currency || undefined,
      positions_count: Number(v.positions_count) || 1,
      required_skills: this.csv(v.required_skills),
      preferred_skills: this.csv(v.preferred_skills),
      min_experience_years: num(v.min_experience_years),
      education_level: v.education_level || undefined,
      languages: this.csv(v.languages).map((entry) => {
        const [name, level] = entry.split(':').map((s) => s.trim());
        return level ? { name, level } : { name };
      }),
      knockout_criteria: this.knockout(),
      deadline_at: v.deadline_at ? new Date(v.deadline_at).toISOString() : undefined,
    };
    const weights = Object.fromEntries(
      Object.entries(this.weights()).filter(([, n]) => !Number.isNaN(n)),
    );
    if (Object.keys(weights).length > 0) payload['scoring_weights'] = weights;
    return payload;
  }

  async submit(): Promise<void> {
    if (this.form.invalid) return;
    this.busy.set(true);
    this.error.set(null);
    try {
      const current = this.offer();
      if (current) {
        this.offer.set(await this.api.update(current.id, this.payload()));
      } else {
        const created = await this.api.create(this.payload());
        await this.router.navigate(['/app/offers', created.id, 'edit']);
      }
    } catch {
      this.error.set('Save failed. Check required fields (title, type, description).');
    } finally {
      this.busy.set(false);
    }
  }

  async publish(): Promise<void> {
    const o = this.offer();
    if (o) this.offer.set(await this.api.publish(o.id));
  }

  async regenerate(): Promise<void> {
    const o = this.offer();
    if (o) this.offer.set(await this.api.regenerateLink(o.id));
  }

  async copy(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
  }
}
