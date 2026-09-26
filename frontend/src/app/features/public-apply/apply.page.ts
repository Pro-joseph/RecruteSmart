import { Component, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { PublicApplyService, PublicOffer } from './public-apply.service';
import { CelebrationComponent } from '../../core/ui/celebration.component';

const APPLIED_KEY = 'recrutesmart.applied.v1';

type PageState = 'loading' | 'not-found' | 'closed' | 'form' | 'done';

@Component({
  selector: 'app-apply',
  standalone: true,
  imports: [RouterLink, ReactiveFormsModule, CelebrationComponent],
  template: `
    @if (state() === 'loading') {
      <div aria-label="Chargement de l'offre" role="status">
        <span class="skeleton" style="width: 55%; height: 2rem; margin-bottom: 1rem"></span>
        <span class="skeleton" style="width: 90%; margin-bottom: 0.5rem"></span>
        <span class="skeleton" style="width: 75%; margin-bottom: 2rem"></span>
        <span class="skeleton" style="width: 45%; height: 2.2rem; margin-bottom: 0.8rem"></span>
        <span class="skeleton" style="width: 45%; height: 2.2rem; margin-bottom: 0.8rem"></span>
        <span class="skeleton" style="width: 45%; height: 2.2rem"></span>
      </div>
    } @else if (state() === 'not-found') {
      <h1>Offre introuvable</h1>
      <p>Ce lien de candidature n'est pas valide.</p>
    } @else if (state() === 'closed') {
      <h1>{{ offer()?.title }}</h1>
      <p>{{ closedMessage() }}</p>
    } @else if (state() === 'done') {
      <div class="done-state">
        <h1>Candidature envoyée</h1>
        <p>{{ successMessage() }}</p>
        <p class="muted">Vous recevrez un e-mail de confirmation à l'adresse indiquée.</p>
      </div>
      @if (celebrate()) {
        <app-celebration (finished)="celebrate.set(false)" />
      }
    } @else {
      <h1>{{ offer()?.title }}</h1>
      <p>{{ offer()?.description }}</p>
      @if (offer()?.missions) {
        <h2>Missions</h2>
        <p>{{ offer()?.missions }}</p>
      }
      @if (offer()?.profile_wanted) {
        <h2>Profil recherché</h2>
        <p>{{ offer()?.profile_wanted }}</p>
      }
      <form [formGroup]="form" (ngSubmit)="submit()" enctype="multipart/form-data">
        <label>
          Nom complet
          <input formControlName="full_name" autocomplete="name" />
          @if (shown('full_name')) {
            <span class="field-error">Le nom est requis.</span>
          }
        </label>
        <label>
          E-mail
          <input type="email" formControlName="email" autocomplete="email" />
          @if (shown('email')) {
            <span class="field-error">
              {{
                form.controls['email'].hasError('required')
                  ? "L'e-mail est requis."
                  : "Format d'e-mail invalide."
              }}
            </span>
          }
        </label>
        @for (field of dynamicFields(); track field.key) {
          <div>
            <label>
              {{ field.label }}
              @if (field.is_required) {
                <span aria-hidden="true" style="color: var(--red)">*</span>
              }
              @switch (field.type) {
                @case ('textarea') {
                  <textarea [formControlName]="controlName(field)"></textarea>
                }
                @case ('number') {
                  <input type="number" [formControlName]="controlName(field)" />
                }
                @case ('date') {
                  <input type="date" [formControlName]="controlName(field)" />
                }
                @case ('select') {
                  <select [formControlName]="controlName(field)">
                    <option value="">—</option>
                    @for (o of field.options ?? []; track o) {
                      <option [value]="o">{{ o }}</option>
                    }
                  </select>
                }
                @case ('multiselect') {
                  <select multiple [formControlName]="controlName(field)">
                    @for (o of field.options ?? []; track o) {
                      <option [value]="o">{{ o }}</option>
                    }
                  </select>
                }
                @case ('boolean') {
                  <input type="checkbox" [formControlName]="controlName(field)" />
                }
                @case ('file') {
                  <input type="file" accept=".pdf,.docx" (change)="onFile(field.key, $event)" />
                }
                @case ('image') {
                  <input
                    type="file"
                    accept=".jpg,.jpeg,.png"
                    (change)="onFile(field.key, $event)"
                  />
                }
                @default {
                  <input
                    [type]="
                      field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text'
                    "
                    [formControlName]="controlName(field)"
                  />
                }
              }
            </label>
            @if (fieldError(field.key)) {
              <p role="alert" class="field-error">{{ fieldError(field.key) }}</p>
            }
          </div>
        }
        <label>
          CV (PDF ou DOCX, 5 Mo max)
          <span aria-hidden="true" style="color: var(--red)">*</span>
          <input type="file" accept=".pdf,.docx" (change)="onFile('cv', $event)" />
        </label>
        @if (fieldError('files.cv')) {
          <p role="alert" class="field-error">{{ fieldError('files.cv') }}</p>
        }
        <label class="consent">
          <input type="checkbox" formControlName="consent" />
          J'accepte le traitement de mes données dans le cadre de cette recrutement.
          <span aria-hidden="true" style="color: var(--red)">*</span>
        </label>
        @if (shown('consent')) {
          <span class="field-error">Votre consentement est requis.</span>
        }
        <input
          type="text"
          formControlName="website"
          tabindex="-1"
          autocomplete="off"
          aria-hidden="true"
          style="display: none"
        />
        @if (generalError()) {
          <p role="alert" class="alert">{{ generalError() }}</p>
        }
        <button
          type="submit"
          class="btn btn-primary"
          [disabled]="form.invalid || busy()"
          [class.is-busy]="busy()"
        >
          {{ busy() ? 'Envoi en cours…' : 'Envoyer ma candidature' }}
        </button>
      </form>
      <p><a routerLink="/login">Espace recruteur</a></p>
    }
  `,
})
export class ApplyPage implements OnInit {
  readonly offer = signal<PublicOffer | null>(null);
  readonly state = signal<PageState>('loading');
  readonly closedMessage = signal('');
  readonly successMessage = signal('');
  readonly generalError = signal<string | null>(null);
  readonly busy = signal(false);
  readonly celebrate = signal(false);
  readonly errors = signal<Record<string, string[]>>({});
  readonly submitted = signal(false);

  form: FormGroup = new FormGroup({
    full_name: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255)],
    }),
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    consent: new FormControl(false, { nonNullable: true, validators: [Validators.requiredTrue] }),
    website: new FormControl('', { nonNullable: true }),
  });

  private token = '';
  private files = new Map<string, File>();

  constructor(
    private readonly route: ActivatedRoute,
    private readonly api: PublicApplyService,
  ) {}

  ngOnInit(): void {
    this.token = this.route.snapshot.paramMap.get('token') ?? '';
    if (!this.token) {
      this.state.set('not-found');
      return;
    }
    void this.load();
  }

  dynamicFields() {
    return (this.offer()?.form_fields ?? []).filter(
      (f) => !['full_name', 'email', 'cv'].includes(f.key),
    );
  }

  controlName(field: { key: string }): string {
    return field.key;
  }

  shown(name: 'full_name' | 'email' | 'consent'): boolean {
    const c = this.form.controls[name];
    return c.invalid && (c.touched || this.submitted());
  }

  fieldError(key: string): string | null {
    const flat = this.errors();
    const hit =
      flat[key] ??
      flat[`answers.${key}`] ??
      flat[`files.${key}`] ??
      (key === 'cv' ? flat['files.cv'] : undefined);
    return hit ? hit.join(' ') : null;
  }

  onFile(key: string, event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) this.files.set(key, file);
    else this.files.delete(key);
    if (key === 'cv') {
      this.errors.update((e) => {
        const next = { ...e };
        delete next['files.cv'];
        return next;
      });
    }
  }

  async load(): Promise<void> {
    try {
      const offer = await this.api.getOffer(this.token);
      this.offer.set(offer);
      if (!offer.accepting_applications) {
        this.closedMessage.set(
          offer.closure_reason === 'closee'
            ? "Cette offre est clôturée et n'accepte plus de candidatures."
            : offer.closure_reason === 'depassee'
              ? 'La date limite de candidature est dépassée.'
              : "Cette offre n'accepte pas de candidatures pour le moment.",
        );
        this.state.set('closed');
        return;
      }
      for (const field of this.dynamicFields()) {
        const validators = field.is_required ? [Validators.required] : [];
        if (field.type === 'multiselect') {
          this.form.addControl(
            this.controlName(field),
            new FormControl<string[]>([], { nonNullable: true, validators }),
          );
        } else if (field.type === 'boolean') {
          this.form.addControl(
            this.controlName(field),
            new FormControl(false, { nonNullable: true }),
          );
        } else {
          this.form.addControl(
            this.controlName(field),
            new FormControl('', { nonNullable: true, validators }),
          );
        }
      }
      this.state.set('form');
    } catch (err) {
      this.state.set(
        err instanceof HttpErrorResponse && err.status !== 404 ? 'closed' : 'not-found',
      );
      if (err instanceof HttpErrorResponse && err.status !== 404) {
        this.closedMessage.set('Cette offre est momentanément indisponible.');
      }
    }
  }

  async submit(): Promise<void> {
    this.submitted.set(true);
    if (this.form.invalid || !this.files.has('cv')) {
      this.form.markAllAsTouched();
      if (!this.files.has('cv')) {
        this.errors.update((e) => ({ ...e, 'files.cv': ['Le CV est requis.'] }));
      }
      return;
    }
    this.busy.set(true);
    this.generalError.set(null);
    this.errors.set({});
    try {
      const data = new FormData();
      data.append('full_name', String(this.form.get('full_name')?.value ?? ''));
      data.append('email', String(this.form.get('email')?.value ?? ''));
      data.append('consent', '1');
      data.append('website', String(this.form.get('website')?.value ?? ''));
      for (const [key, file] of this.files) {
        data.append(`files[${key}]`, file, file.name);
      }
      for (const field of this.dynamicFields()) {
        if (['file', 'image'].includes(field.type)) continue;
        const value = this.form.get(field.key)?.value;
        if (Array.isArray(value)) {
          value.forEach((v) => data.append(`answers[${field.key}][]`, v));
        } else if (value !== '' && value !== false && value != null) {
          data.append(`answers[${field.key}]`, String(value));
        }
      }
      const res = await this.api.submit(this.token, data);
      this.successMessage.set(res.message);
      this.state.set('done');
      if (!localStorage.getItem(APPLIED_KEY)) {
        localStorage.setItem(APPLIED_KEY, '1');
        this.celebrate.set(true);
      }
    } catch (err) {
      if (err instanceof HttpErrorResponse && err.status === 422) {
        const body = err.error as { errors?: Record<string, string[]>; message?: string };
        if (body.errors) this.errors.set(body.errors);
        else this.generalError.set(body.message ?? 'Candidature refusée.');
      } else if (err instanceof HttpErrorResponse && err.status === 429) {
        this.generalError.set('Trop de tentatives, réessayez plus tard.');
      } else {
        this.generalError.set("L'envoi a échoué, veuillez réessayer.");
      }
    } finally {
      this.busy.set(false);
    }
  }
}
