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
    <h1>Forgot password</h1>
    <form [formGroup]="form" (ngSubmit)="submit()">
      <label>Email <input type="email" formControlName="email" autocomplete="email" /></label>
      @if (message()) {
        <p role="status">{{ message() }}</p>
      }
      <button type="submit" [disabled]="form.invalid || busy()">Send reset link</button>
    </form>
    <p><a routerLink="/login">Back to login</a></p>
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

  constructor(
    private readonly http: HttpClient,
    private readonly auth: AuthService,
  ) {}

  async submit(): Promise<void> {
    if (this.form.invalid) return;
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
      this.message.set('If this email exists, a reset link was sent.');
    } finally {
      this.busy.set(false);
    }
  }
}
