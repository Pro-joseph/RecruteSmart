import { Component, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Connexion</h1>
    <form [formGroup]="form" (ngSubmit)="submit()">
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
            autocomplete="current-password"
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
          <span class="field-error">Le mot de passe est requis.</span>
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
        {{ busy() ? 'Connexion en cours…' : 'Se connecter' }}
      </button>
    </form>
    <p>
      <a routerLink="/register">Créer un compte</a> ·
      <a routerLink="/forgot-password">Mot de passe oublié</a>
    </p>
  `,
})
export class LoginPage {
  readonly form = new FormGroup({
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    password: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
  });
  readonly error = signal<string | null>(null);
  readonly busy = signal(false);
  readonly showPw = signal(false);
  readonly submitted = signal(false);

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
  ) {}

  invalid(name: 'email' | 'password'): boolean {
    const c = this.form.controls[name];
    return c.invalid && (c.touched || this.submitted());
  }

  emailError(): string {
    return this.form.controls.email.hasError('required')
      ? "L'e-mail est requis."
      : "Format d'e-mail invalide.";
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
      await this.auth.login(this.form.controls.email.value, this.form.controls.password.value);
      await this.router.navigate(['/app/offers']);
    } catch {
      this.error.set('Identifiants invalides.');
    } finally {
      this.busy.set(false);
    }
  }
}
