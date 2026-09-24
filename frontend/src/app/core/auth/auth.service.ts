import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  company_name?: string | null;
  timezone?: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  readonly user = signal<AuthUser | null>(null);

  constructor(private readonly http: HttpClient) {}

  /** Fetch Sanctum CSRF cookie before session calls (same-origin). */
  csrf(): Promise<void> {
    return firstValueFrom(
      this.http.get<void>('/sanctum/csrf-cookie', { withCredentials: true }),
    ).then(() => undefined);
  }

  async register(
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string,
  ): Promise<AuthUser> {
    await this.csrf();
    const res = await firstValueFrom(
      this.http.post<{ data: AuthUser }>('/api/v1/auth/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      }),
    );
    this.user.set(res.data);
    return res.data;
  }

  async login(email: string, password: string): Promise<AuthUser> {
    await this.csrf();
    const res = await firstValueFrom(
      this.http.post<{ data: AuthUser }>('/api/v1/auth/login', { email, password }),
    );
    this.user.set(res.data);
    return res.data;
  }

  async me(): Promise<AuthUser | null> {
    try {
      const res = await firstValueFrom(this.http.get<{ data: AuthUser }>('/api/v1/auth/me'));
      this.user.set(res.data);
      return res.data;
    } catch {
      this.user.set(null);
      return null;
    }
  }

  async logout(): Promise<void> {
    await firstValueFrom(this.http.post('/api/v1/auth/logout', {}));
    this.user.set(null);
  }
}
