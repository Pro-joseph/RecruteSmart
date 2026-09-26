import { Component, signal } from '@angular/core';
import {
  AbstractControl,
  FormControl,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Créer un compte</h1>
    <form [formGroup]="form" (ngSubmit)="submit()">
      <label>
        Nom
        <input formControlName="name" autocomplete="name" />
        @if (invalid('name')) {
          <span class="field-error">Le nom est requis.</span>
        }
      </label>
      <label>
        E-mail
        <input type="email" formControlName="email" autocomplete="email" />
        @if (invalid('email')) {
          <span class="field-error">{{ emailError() }}</span>
        }
      </label>
      <label>
        Mot de passe
        <span class="pw-wrap">
          <input
            [type]="showPw() ? 'text' : 'password'"
            formControlName="password"
            autocomplete="new-password"
          />
          <button
            type="button"
            class="pw-toggle"
            [attr.aria-pressed]="showPw()"
            [attr.aria-label]="showPw() ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
            (click)="showPw.set(!showPw())"
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </button>
        </span>
        @if (invalid('password')) {
          <span class="field-error">{{ passwordError() }}</span>
        }
      </label>
      <label>
        Confirmer le mot de passe
        <input
          [type]="showPw() ? 'text' : 'password'"
          formControlName="password_confirmation"
          autocomplete="new-password"
        />
        @if (invalid('password_confirmation')) {
          <span class="field-error">{{ confirmationError() }}</span>
        }
      </label>
      @if (error()) {
        <p role="alert" class="alert">{{ error() }}</p>
      }
      <button
        type="submit"
        class="btn btn-primary"
        [disabled]="form.invalid || busy()"
        [class.is-busy]="busy()"
      >
        {{ busy() ? 'Création en cours…' : 'Créer mon compte' }}
      </button>
    </form>
    <p><a routerLink="/login">Retour à la connexion</a></p>
  `,
})
export class RegisterPage {
  readonly form = new FormGroup({
    name: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(255)],
    }),
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    password: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(8)],
    }),
    password_confirmation: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, RegisterPage.passwordsMatch],
    }),
  });
  readonly error = signal<string | null>(null);
  readonly busy = signal(false);
  readonly showPw = signal(false);
  readonly submitted = signal(false);

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
  ) {
    // Re-validate the confirmation whenever the password changes.
    this.form.controls.password.valueChanges.subscribe(() => {
      this.form.controls.password_confirmation.updateValueAndValidity();
    });
  }

  static passwordsMatch(c: AbstractControl): Record<string, boolean> | null {
    const parent = c.parent;
    if (!parent) return null;
    return c.value === parent.get('password')?.value ? null : { mismatch: true };
  }

  invalid(name: 'name' | 'email' | 'password' | 'password_confirmation'): boolean {
    const c = this.form.controls[name];
    return c.invalid && (c.touched || this.submitted());
  }

  emailError(): string {
    return this.form.controls.email.hasError('required')
      ? "L'e-mail est requis."
      : "Format d'e-mail invalide.";
  }

  passwordError(): string {
    return this.form.controls.password.hasError('minlength')
      ? '8 caractères minimum.'
      : 'Le mot de passe est requis.';
  }

  confirmationError(): string {
    return this.form.controls.password_confirmation.hasError('mismatch')
      ? 'Les mots de passe ne correspondent pas.'
      : 'La confirmation est requise.';
  }

  async submit(): Promise<void> {
    this.submitted.set(true);
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    try {
      const v = this.form.getRawValue();
      await this.auth.register(v.name, v.email, v.password, v.password_confirmation);
      await this.router.navigate(['/app/offers']);
    } catch (err) {
      const e = err as { error?: { errors?: Record<string, string[]>; message?: string } };
      const list = e.error?.errors ? Object.values(e.error.errors).flat() : [];
      this.error.set(list[0] ?? e.error?.message ?? 'La création du compte a échoué.');
    } finally {
      this.busy.set(false);
    }
  }
}
