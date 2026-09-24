import { Component, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  template: `
    <h1>Register</h1>
    <form [formGroup]="form" (ngSubmit)="submit()">
      <label>Name <input formControlName="name" autocomplete="name" /></label>
      <label>Email <input type="email" formControlName="email" autocomplete="email" /></label>
      <label>Password <input type="password" formControlName="password" autocomplete="new-password" /></label>
      <label>Confirm <input type="password" formControlName="password_confirmation" autocomplete="new-password" /></label>
      @if (error()) { <p role="alert">{{ error() }}</p> }
      <button type="submit" [disabled]="form.invalid || busy()">Create account</button>
    </form>
    <p><a routerLink="/login">Back to login</a></p>
  `,
})
export class RegisterPage {
  readonly form = new FormGroup({
    name: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.maxLength(255)] }),
    email: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.email] }),
    password: new FormControl('', { nonNullable: true, validators: [Validators.required, Validators.minLength(8)] }),
    password_confirmation: new FormControl('', { nonNullable: true, validators: [Validators.required] }),
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
      const v = this.form.getRawValue();
      await this.auth.register(v.name, v.email, v.password, v.password_confirmation);
      await this.router.navigate(['/app/offers']);
    } catch {
      this.error.set('Registration failed. Email may already be used.');
    } finally {
      this.busy.set(false);
    }
  }
}
