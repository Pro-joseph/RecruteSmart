import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

export interface PublicFormField {
  key: string;
  label: string;
  type: string;
  is_required: boolean;
  is_locked: boolean;
  is_sensitive: boolean;
  options?: string[] | null;
}

export interface PublicOffer {
  title: string;
  type: string;
  type_label?: string | null;
  city?: string | null;
  country?: string | null;
  work_mode?: string | null;
  description: string;
  missions?: string | null;
  profile_wanted?: string | null;
  salary_min?: string | null;
  salary_max?: string | null;
  salary_currency?: string | null;
  deadline_at?: string | null;
  accepting_applications: boolean;
  closure_reason?: string | null;
  form_fields: PublicFormField[];
}

@Injectable({ providedIn: 'root' })
export class PublicApplyService {
  constructor(private readonly http: HttpClient) {}

  getOffer(token: string): Promise<PublicOffer> {
    return firstValueFrom(
      this.http.get<{ data: PublicOffer }>(`/api/v1/public/offers/${token}`),
    ).then((r) => r.data);
  }

  submit(token: string, form: FormData): Promise<{ message: string }> {
    return firstValueFrom(
      this.http.post<{ message: string }>(`/api/v1/public/offers/${token}/applications`, form),
    );
  }
}
