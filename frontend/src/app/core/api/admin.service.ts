import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
  created_at: string;
  offers_count: number;
  applications_count: number;
}

export interface ModelUsage {
  llm_provider: string | null;
  llm_model: string | null;
  analyses_count: number;
  tokens_in: number;
  tokens_out: number;
}

export interface UsageTotals {
  analyses_count: number;
  completed_count: number;
  failed_count: number;
  tokens_in: number;
  tokens_out: number;
}

/** EF-1205: admin supervision of accounts and AI usage. */
@Injectable({ providedIn: 'root' })
export class AdminService {
  constructor(private readonly http: HttpClient) {}

  users(): Promise<AdminUser[]> {
    return firstValueFrom(this.http.get<{ data: AdminUser[] }>('/api/v1/admin/users')).then(
      (r) => r.data,
    );
  }

  usage(): Promise<{ totals: UsageTotals; per_model: ModelUsage[] }> {
    return firstValueFrom(
      this.http.get<{ data: { totals: UsageTotals; per_model: ModelUsage[] } }>(
        '/api/v1/admin/usage',
      ),
    ).then((r) => r.data);
  }
}
