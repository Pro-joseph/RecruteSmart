import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

export interface ForwardCandidate {
  application_id: number;
  name: string;
}

export interface Forward {
  id: number;
  to_emails: string[];
  subject: string;
  message: string | null;
  delivery: 'attachments' | 'links';
  include_analysis: boolean;
  status: 'queued' | 'sent' | 'failed';
  error_message: string | null;
  sent_at: string | null;
  created_at: string;
  candidates: ForwardCandidate[];
}

export interface CreateForwardPayload {
  to_emails: string[];
  subject: string;
  message?: string;
  include_analysis?: boolean;
  application_ids?: number[];
  offer_id?: number;
  select_all?: boolean;
  filters?: Record<string, unknown>;
}

@Injectable({ providedIn: 'root' })
export class ForwardsService {
  constructor(private readonly http: HttpClient) {}

  create(payload: CreateForwardPayload): Promise<Forward> {
    return firstValueFrom(this.http.post<{ data: Forward }>('/api/v1/forwards', payload)).then(
      (r) => r.data,
    );
  }

  list(page = 1): Promise<{
    data: Forward[];
    meta: { total: number; last_page: number; current_page: number };
  }> {
    const params = new HttpParams().set('page', String(page));
    return firstValueFrom(
      this.http.get<{
        data: Forward[];
        meta: { total: number; last_page: number; current_page: number };
      }>('/api/v1/forwards', { params }),
    );
  }

  retry(id: number): Promise<void> {
    return firstValueFrom(
      this.http.post<{ message: string }>(`/api/v1/forwards/${id}/retry`, {}),
    ).then(() => undefined);
  }
}
