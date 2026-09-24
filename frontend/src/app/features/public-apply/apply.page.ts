import { Component, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { PublicApplyService, PublicOffer } from './public-apply.service';

type PageState = 'loading' | 'not-found' | 'closed' | 'form' | 'done';

@Component({
  selector: 'app-apply',
  standalone: true,
  imports: [RouterLink, ReactiveFormsModule],
  template: `
    @if (state() === 'loading') {
      <p role="status">Loading…</p>
    } @else if (state() === 'not-found') {
      <h1>Offer not found</h1>
      <p>This application link is invalid.</p>
    } @else if (state() === 'closed') {
      <h1>{{ offer()?.title }}</h1>
      <p>{{ closedMessage() }}</p>
    } @else if (state() === 'done') {
      <h1>Application sent</h1>
      <p>{{ successMessage() }}</p>
    } @else {
      <h1>{{ offer()?.title }}</h1>
      <p>{{ offer()?.description }}</p>
      @if (offer()?.missions) {
        <h2>Missions</h2>
        <p>{{ offer()?.missions }}</p>
      }
      @if (offer()?.profile_wanted) {
        <h2>Wanted profile</h2>
        <p>{{ offer()?.profile_wanted }}</p>
      }
      <form [formGroup]="form" (ngSubmit)="submit()" enctype="multipart/form-data">
        <label>Full name <input formControlName="full_name" autocomplete="name" /></label>
        <label>Email <input type="email" formControlName="email" autocomplete="email" /></label>
        @for (field of dynamicFields(); track field.key) {
          <div>
            <label>
              {{ field.label }}
              @if (field.is_required) {
                *
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
              <p role="alert">{{ fieldError(field.key) }}</p>
            }
          </div>
        }
        <label
          >CV (PDF or DOCX, 5 MB max) *
          <input type="file" accept=".pdf,.docx" (change)="onFile('cv', $event)"
        /></label>
        @if (fieldError('files.cv')) {
          <p role="alert">{{ fieldError('files.cv') }}</p>
        }
        <label
          ><input type="checkbox" formControlName="consent" /> I accept the processing of my data
          for this recruitment. *</label
        >
        <input
          type="text"
          formControlName="website"
          tabindex="-1"
          autocomplete="off"
          aria-hidden="true"
          style="display:none"
        />
        @if (generalError()) {
          <p role="alert">{{ generalError() }}</p>
        }
        <button type="submit" [disabled]="form.invalid || busy()">Send application</button>
      </form>
      <p><a routerLink="/login">Recruiter login</a></p>
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
  readonly errors = signal<Record<string, string[]>>({});

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
    return `answers.${field.key}`;
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
  }

  async load(): Promise<void> {
    try {
      const offer = await this.api.getOffer(this.token);
      this.offer.set(offer);
      if (!offer.accepting_applications) {
        this.closedMessage.set(
          offer.closure_reason === 'closee'
            ? 'This offer is closed and no longer accepts applications.'
            : offer.closure_reason === 'depassee'
              ? 'The application deadline has passed.'
              : 'This offer is not accepting applications at the moment.',
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
        this.closedMessage.set('This offer is currently unavailable.');
      }
    }
  }

  async submit(): Promise<void> {
    if (this.form.invalid || !this.files.has('cv')) {
      if (!this.files.has('cv')) {
        this.errors.update((e) => ({ ...e, 'files.cv': ['The CV is required.'] }));
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
        const value = this.form.get(this.controlName(field))?.value;
        if (Array.isArray(value)) {
          value.forEach((v) => data.append(`answers[${field.key}][]`, v));
        } else if (value !== '' && value !== false && value != null) {
          data.append(`answers[${field.key}]`, String(value));
        }
      }
      const res = await this.api.submit(this.token, data);
      this.successMessage.set(res.message);
      this.state.set('done');
    } catch (err) {
      if (err instanceof HttpErrorResponse && err.status === 422) {
        const body = err.error as { errors?: Record<string, string[]>; message?: string };
        if (body.errors) this.errors.set(body.errors);
        else this.generalError.set(body.message ?? 'Submission refused.');
      } else if (err instanceof HttpErrorResponse && err.status === 429) {
        this.generalError.set('Too many attempts, please try again later.');
      } else {
        this.generalError.set('Submission failed, please try again.');
      }
    } finally {
      this.busy.set(false);
    }
  }
}
