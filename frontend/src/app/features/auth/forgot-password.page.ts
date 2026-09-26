import { Component, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Mot de passe oublié</h1>
    <form [formGroup]="form" (ngSubmit)="submit()">
      <label>
        E-mail
        <input type="email" formControlName="email" autocomplete="email" />
        @if (invalid()) {
          <span class="field-error">
            {{
              form.controls.email.hasError('required')
                ? "L'e-mail est requis."
                : "Format d'e-mail invalide."
            }}
          </span>
        }
      </label>
      @if (message()) {
        <p role="status">{{ message() }}</p>
      }
      <button
        type="submit"
        class="btn btn-primary"
        [disabled]="form.invalid || busy()"
        [class.is-busy]="busy()"
      >
        {{ busy() ? 'Envoi en cours…' : 'Envoyer le lien' }}
      </button>
    </form>
    <p><a routerLink="/login">Retour à la connexion</a></p>
  `,
})
export class ForgotPasswordPage {
  readonly form = new FormGroup({
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
  });
  readonly message = signal<string | null>(null);
  readonly busy = signal(false);
  readonly submitted = signal(false);

  constructor(
    private readonly http: HttpClient,
    private readonly auth: AuthService,
  ) {}

  invalid(): boolean {
    const c = this.form.controls.email;
    return c.invalid && (c.touched || this.submitted());
  }

  async submit(): Promise<void> {
    this.submitted.set(true);
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    try {
      await this.auth.csrf();
      const res = await firstValueFrom(
        this.http.post<{ message: string }>('/api/v1/auth/forgot-password', {
          email: this.form.controls.email.value,
        }),
      );
      this.message.set(res.message);
    } catch {
      this.message.set('Si cet e-mail existe, un lien de réinitialisation a été envoyé.');
    } finally {
      this.busy.set(false);
    }
  }
}
