import { Component, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Login</h1>
    <form [formGroup]="form" (ngSubmit)="submit()">
      <label>Email <input type="email" formControlName="email" autocomplete="email" /></label>
      <label
        >Password <input type="password" formControlName="password" autocomplete="current-password"
      /></label>
      @if (error()) {
        <p role="alert">{{ error() }}</p>
      }
      <button type="submit" [disabled]="form.invalid || busy()">Login</button>
    </form>
    <p>
      <a routerLink="/register">Register</a> · <a routerLink="/forgot-password">Forgot password</a>
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

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
  ) {}

  async submit(): Promise<void> {
    if (this.form.invalid) return;
    this.busy.set(true);
    this.error.set(null);
    try {
      await this.auth.login(this.form.controls.email.value, this.form.controls.password.value);
      await this.router.navigate(['/app/offers']);
    } catch {
      this.error.set('Invalid credentials.');
    } finally {
      this.busy.set(false);
    }
  }
}
