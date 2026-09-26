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

const TYPE_LABELS: Record<string, string> = {
  full_time: 'CDI',
  part_time: 'Temps partiel',
  contract: 'Contrat',
  internship: 'Stage',
  apprenticeship: 'Alternance',
  freelance: 'Freelance',
  other: 'Autre',
};

const CRITERION_LABELS: Record<string, string> = {
  required_skills: 'Compétences requises',
  preferred_skills: 'Compétences souhaitées',
  experience: 'Expérience',
  education: 'Formation',
  languages: 'Langues',
};

const STATUS_LABELS: Record<string, string> = {
  draft: 'Brouillon',
  published: 'Publiée',
  closed: 'Clôturée',
  archived: 'Archivée',
};

const STEP_LABELS = ['Poste', 'Conditions', 'Critères & score'];

@Component({
  selector: 'app-offer-form',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink, FormEditorComponent],
  template: `
    <p><a routerLink="/app/offers">← Offres</a></p>
    <div class="page-head">
      <h1>{{ isNew() ? 'Nouvelle offre' : "Modifier l'offre" }}</h1>
      @if (offer(); as o) {
        <span class="badge badge-green">{{ statusLabel(o.status) }}</span>
      }
    </div>

    <ol class="steps" aria-label="Étapes">
      @for (label of stepLabels; track label; let i = $index) {
        <li [class.done]="i < step()" [class.current]="i === step()">{{ i + 1 }}. {{ label }}</li>
      }
    </ol>

    @if (loading()) {
      <div role="status" aria-label="Chargement de l'offre">
        <span class="skeleton" style="width: 40%; height: 2rem; margin-bottom: 0.8rem"></span>
        <span class="skeleton" style="width: 80%; margin-bottom: 0.5rem"></span>
        <span class="skeleton" style="width: 65%; margin-bottom: 0.5rem"></span>
        <span class="skeleton" style="width: 72%"></span>
      </div>
    } @else {
      @if (error()) {
        <p role="alert" class="alert">{{ error() }}</p>
      }
      @if (notice()) {
        <p role="status">{{ notice() }}</p>
      }
      <form [formGroup]="form" (ngSubmit)="submit()">
        @if (step() === 0) {
          <div class="step-pane" [class.back]="dir() === 'back'">
            <section class="extract-panel" aria-label="Importer une description de poste">
              <h2>Importer depuis une description de poste</h2>
              <p class="muted small">
                Collez l'offre complète : l'IA en extrait l'intitulé, la description, les missions,
                les compétences, le salaire et la localisation. Vérifiez ensuite chaque champ.
              </p>
              <textarea
                rows="7"
                [value]="jdDraft()"
                (input)="onJdInput($event)"
                placeholder="Intitulé, société, localisation, missions, compétences requises, salaire…"
                aria-label="Description de poste à coller"
              ></textarea>
              @if (extractError()) {
                <p class="alert" role="alert">{{ extractError() }}</p>
              }
              <div class="row-actions">
                <button
                  type="button"
                  class="btn btn-primary"
                  [class.is-busy]="extracting()"
                  [disabled]="extracting() || jdDraft().trim().length < 80"
                  (click)="extract()"
                >
                  {{ extracting() ? 'Extraction en cours…' : 'Extraire les informations' }}
                </button>
                @if (jdDraft().trim().length < 80) {
                  <span class="muted small">80 caractères minimum pour extraire.</span>
                }
              </div>
            </section>
            <label>
              Intitulé
              <input formControlName="title" />
              @if (invalid('title')) {
                <span class="field-error">L'intitulé est requis (255 caractères max).</span>
              }
            </label>
            <label>
              Type
              <select formControlName="type">
                @for (t of types; track t) {
                  <option [value]="t">{{ typeLabel(t) }}</option>
                }
              </select>
            </label>
            @if (form.controls.type.value === 'other') {
              <label>Libellé du type <input formControlName="type_label" /></label>
            }
            <label>
              Description
              <textarea formControlName="description" rows="4"></textarea>
              @if (invalid('description')) {
                <span class="field-error">La description est requise.</span>
              }
            </label>
            <label>
              Missions
              <textarea formControlName="missions" rows="3"></textarea>
            </label>
            <label>
              Profil recherché
              <textarea formControlName="profile_wanted" rows="3"></textarea>
            </label>
            <div class="step-nav">
              <span></span>
              <button type="button" class="btn btn-primary" (click)="next()">Continuer →</button>
            </div>
          </div>
        } @else if (step() === 1) {
          <div class="step-pane" [class.back]="dir() === 'back'">
            <div class="grid-2">
              <label>
                Ville
                <input formControlName="city" />
              </label>
              <label>
                Pays
                <input formControlName="country" />
              </label>
              <label>
                Mode de travail
                <select formControlName="work_mode">
                  <option value="">—</option>
                  <option value="onsite">Sur site</option>
                  <option value="hybrid">Hybride</option>
                  <option value="remote">À distance</option>
                </select>
              </label>
              <label>
                Postes à pourvoir
                <input type="number" min="1" formControlName="positions_count" />
              </label>
              <label>
                Salaire min
                <input type="number" formControlName="salary_min" />
              </label>
              <label>
                Salaire max
                <input type="number" formControlName="salary_max" />
              </label>
              <label>
                Devise (3 lettres)
                <input formControlName="salary_currency" maxlength="3" placeholder="EUR" />
              </label>
              <label>
                Date limite
                <input type="datetime-local" formControlName="deadline_at" />
              </label>
            </div>
            <div class="step-nav">
              <button type="button" class="btn" (click)="back()">← Retour</button>
              <button type="button" class="btn btn-primary" (click)="next()">Continuer →</button>
            </div>
          </div>
        } @else {
          <div class="step-pane" [class.back]="dir() === 'back'">
            <div class="grid-2">
              <label>
                Compétences requises (virgules)
                <input formControlName="required_skills" placeholder="PHP, Laravel, SQL" />
              </label>
              <label>
                Compétences souhaitées (virgules)
                <input formControlName="preferred_skills" placeholder="Docker, Redis" />
              </label>
              <label>
                Expérience min. (années)
                <input type="number" step="0.5" min="0" formControlName="min_experience_years" />
              </label>
              <label>
                Niveau d'études
                <input formControlName="education_level" placeholder="Bac+3" />
              </label>
              <label>
                Langues (nom:niveau, virgules)
                <input formControlName="languages" placeholder="Anglais:C1, Français:C2" />
              </label>
            </div>

            <fieldset>
              <legend>Critères éliminatoires</legend>
              <p class="muted small">
                Une candidature qui remplit un critère éliminatoire est automatiquement filtrée.
              </p>
              <div class="chip-row">
                @for (k of knockoutKeys; track k) {
                  <label class="chip-toggle" [class.on]="knockout().includes(k)">
                    <input
                      type="checkbox"
                      [checked]="knockout().includes(k)"
                      (change)="toggleKnockout(k)"
                    />
                    {{ criterionLabel(k) }}
                  </label>
                }
              </div>
            </fieldset>

            <fieldset>
              <legend>Pondération du score</legend>
              <div class="grid-2">
                @for (k of weightKeys; track k) {
                  <label>
                    {{ criterionLabel(k) }}
                    <input
                      type="number"
                      [value]="weights()[k]"
                      (input)="setWeight(k, $event)"
                      min="0"
                      max="100"
                    />
                  </label>
                }
              </div>
            </fieldset>

            <div class="step-nav">
              <button type="button" class="btn" (click)="back()">← Retour</button>
              <button
                type="submit"
                class="btn btn-primary"
                [disabled]="form.invalid || busy()"
                [class.is-busy]="busy()"
              >
                {{ busy() ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
            </div>
          </div>
        }
      </form>

      @if (offer(); as o) {
        <div class="panel publish-panel">
          <h2>Publication</h2>
          <p class="muted">
            Statut : {{ statusLabel(o.status) }} · critères v{{ o.criteria_version }}
          </p>
          @if (o.public_url) {
            <p>
              Lien public :
              <a [href]="o.public_url" target="_blank" rel="noopener">{{ o.public_url }}</a>
            </p>
            <div class="row-actions">
              <button type="button" class="btn" (click)="copy(o.public_url!)">Copier</button>
              @if (confirmRegen()) {
                <button
                  type="button"
                  class="btn btn-danger"
                  [disabled]="busy()"
                  [class.is-busy]="busy()"
                  (click)="regenerate()"
                >
                  {{ busy() ? 'Régénération…' : 'Oui, régénérer' }}
                </button>
                <button type="button" class="btn btn-ghost" (click)="confirmRegen.set(false)">
                  Annuler
                </button>
              } @else {
                <button type="button" class="btn btn-ghost" (click)="confirmRegen.set(true)">
                  Régénérer le lien
                </button>
              }
            </div>
            <p class="muted small">
              Régénérer invalide immédiatement l'ancien lien (utile s'il a fuité).
            </p>
          } @else {
            <button
              type="button"
              class="btn btn-primary"
              [disabled]="busy()"
              [class.is-busy]="busy()"
              (click)="publish()"
            >
              {{ busy() ? 'Publication…' : 'Publier (générer le lien)' }}
            </button>
          }
          <app-form-editor [offerId]="o.id" />
        </div>
      }
    }
  `,
})
export class OfferFormPage implements OnInit {
  readonly types = OFFER_TYPES;
  readonly knockoutKeys = KNOCKOUT_KEYS;
  readonly weightKeys = WEIGHT_KEYS;
  readonly stepLabels = STEP_LABELS;

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
  readonly notice = signal<string | null>(null);
  readonly busy = signal(false);
  readonly loading = signal(false);
  readonly step = signal(0);
  readonly dir = signal<'fwd' | 'back'>('fwd');
  readonly confirmRegen = signal(false);
  readonly submitted = signal(false);
  readonly jdDraft = signal('');
  readonly extracting = signal(false);
  readonly extractError = signal<string | null>(null);

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

  typeLabel(type: string): string {
    return TYPE_LABELS[type] ?? type;
  }

  criterionLabel(key: string): string {
    return CRITERION_LABELS[key] ?? key;
  }

  statusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status;
  }

  invalid(name: 'title' | 'description'): boolean {
    const c = this.form.controls[name];
    return c.invalid && (c.touched || this.submitted());
  }

  onJdInput(event: Event): void {
    this.jdDraft.set((event.target as HTMLTextAreaElement).value);
  }

  async extract(): Promise<void> {
    const text = this.jdDraft().trim();
    if (text.length < 80 || this.extracting()) return;

    this.extracting.set(true);
    this.extractError.set(null);
    try {
      const data = await this.api.extract(text);
      const patch: Partial<ReturnType<OfferFormPage['form']['getRawValue']>> = {};
      const setText = (
        key:
          | 'title'
          | 'type_label'
          | 'description'
          | 'missions'
          | 'profile_wanted'
          | 'city'
          | 'country'
          | 'education_level'
          | 'salary_currency',
        value: string | null,
      ): void => {
        if (value && value.trim() !== '') patch[key] = value.trim();
      };

      setText('title', data.title);
      setText('description', data.description);
      setText('missions', data.missions);
      setText('profile_wanted', data.profile_wanted);
      setText('city', data.city);
      setText('country', data.country);
      setText('education_level', data.education_level);
      setText('type_label', data.type_label);
      setText('salary_currency', data.salary_currency);

      if (data.type && (OFFER_TYPES as readonly string[]).includes(data.type)) {
        patch.type = data.type;
      }
      if (data.work_mode && ['onsite', 'hybrid', 'remote'].includes(data.work_mode)) {
        patch.work_mode = data.work_mode;
      }
      if (data.salary_min != null) patch.salary_min = String(data.salary_min);
      if (data.salary_max != null) patch.salary_max = String(data.salary_max);
      if (data.positions_count != null) patch.positions_count = String(data.positions_count);
      if (data.min_experience_years != null) {
        patch.min_experience_years = String(data.min_experience_years);
      }
      if (data.required_skills?.length) {
        patch.required_skills = data.required_skills.join(', ');
      }
      if (data.preferred_skills?.length) {
        patch.preferred_skills = data.preferred_skills.join(', ');
      }
      if (data.languages?.length) {
        patch.languages = data.languages
          .map((l) => (l.level ? `${l.name}:${l.level}` : l.name))
          .join(', ');
      }

      this.form.patchValue(patch);
      this.notice.set("Informations extraites — vérifiez chaque champ avant d'enregistrer.");
    } catch (err) {
      this.extractError.set(this.readExtractError(err));
    } finally {
      this.extracting.set(false);
    }
  }

  private readExtractError(err: unknown): string {
    const body = (err as { error?: { message?: string; errors?: Record<string, string[]> } })
      ?.error;
    if (body?.message) return body.message;
    const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
    return first ?? "L'extraction a échoué. Vérifiez votre connexion et réessayez.";
  }

  async load(id: number): Promise<void> {
    this.loading.set(true);
    try {
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
    } catch {
      this.error.set('Impossible de charger cette offre.');
    } finally {
      this.loading.set(false);
    }
  }

  next(): void {
    this.submitted.set(true);
    if (
      this.step() === 0 &&
      (this.form.controls.title.invalid || this.form.controls.description.invalid)
    ) {
      this.form.controls.title.markAsTouched();
      this.form.controls.description.markAsTouched();
      this.form.controls.title.updateValueAndValidity();
      this.form.controls.description.updateValueAndValidity();
      return;
    }
    this.dir.set('fwd');
    this.step.update((s) => Math.min(2, s + 1));
  }

  back(): void {
    this.dir.set('back');
    this.step.update((s) => Math.max(0, s - 1));
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
    // Enter key on earlier steps advances instead of saving.
    if (this.step() < 2) {
      this.next();
      return;
    }
    this.submitted.set(true);
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    try {
      const current = this.offer();
      if (current) {
        this.offer.set(await this.api.update(current.id, this.payload()));
        this.notice.set('Offre enregistrée.');
      } else {
        const created = await this.api.create(this.payload());
        await this.router.navigate(['/app/offers', created.id, 'edit']);
      }
    } catch {
      this.error.set(
        "Échec de l'enregistrement. Vérifiez les champs obligatoires (intitulé, type, description).",
      );
    } finally {
      this.busy.set(false);
    }
  }

  async publish(): Promise<void> {
    const o = this.offer();
    if (!o) return;
    this.busy.set(true);
    this.error.set(null);
    try {
      this.offer.set(await this.api.publish(o.id));
      this.notice.set('Offre publiée — le lien public est actif.');
    } catch {
      this.error.set('La publication a échoué.');
    } finally {
      this.busy.set(false);
    }
  }

  async regenerate(): Promise<void> {
    const o = this.offer();
    if (!o) return;
    this.busy.set(true);
    this.confirmRegen.set(false);
    this.error.set(null);
    try {
      this.offer.set(await this.api.regenerateLink(o.id));
      this.notice.set('Lien régénéré — l’ancien lien ne fonctionne plus.');
    } catch {
      this.error.set('La régénération du lien a échoué.');
    } finally {
      this.busy.set(false);
    }
  }

  async copy(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
    this.notice.set('Lien copié.');
  }
}
