import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

export interface OfferFormField {
  id?: number;
  key: string;
  label: string;
  type: string;
  is_required: boolean;
  is_locked: boolean;
  is_sensitive: boolean;
  is_hidden: boolean;
  options?: string[] | null;
  rules?: Record<string, unknown> | null;
  position: number;
}

export interface CatalogField {
  key: string;
  label: string;
  type: string;
  locked: boolean;
  sensitive: boolean;
  required: boolean;
}

export interface Offer {
  id: number;
  title: string;
  type: string;
  type_label?: string | null;
  description: string;
  missions?: string | null;
  profile_wanted?: string | null;
  city?: string | null;
  country?: string | null;
  work_mode?: string | null;
  salary_min?: string | null;
  salary_max?: string | null;
  salary_currency?: string | null;
  positions_count: number;
  required_skills: string[];
  preferred_skills: string[];
  min_experience_years?: number | null;
  education_level?: string | null;
  languages: { name: string; level?: string }[];
  knockout_criteria: string[];
  scoring_weights?: Record<string, number> | null;
  criteria_version: number;
  status: string;
  public_token?: string;
  public_url?: string;
  deadline_at?: string | null;
  published_at?: string | null;
  closed_at?: string | null;
  form_fields?: OfferFormField[];
}

@Injectable({ providedIn: 'root' })
export class OffersService {
  constructor(private readonly http: HttpClient) {}

  list(status?: string): Promise<Offer[]> {
    let params = new HttpParams();
    if (status) params = params.set('status', status);
    return firstValueFrom(this.http.get<{ data: Offer[] }>('/api/v1/offers', { params })).then(
      (r) => r.data,
    );
  }

  get(id: number): Promise<Offer> {
    return firstValueFrom(this.http.get<{ data: Offer }>(`/api/v1/offers/${id}`)).then(
      (r) => r.data,
    );
  }

  create(payload: Partial<Offer>): Promise<Offer> {
    return firstValueFrom(this.http.post<{ data: Offer }>('/api/v1/offers', payload)).then(
      (r) => r.data,
    );
  }

  update(id: number, payload: Partial<Offer>): Promise<Offer> {
    return firstValueFrom(this.http.put<{ data: Offer }>(`/api/v1/offers/${id}`, payload)).then(
      (r) => r.data,
    );
  }

  delete(id: number): Promise<void> {
    return firstValueFrom(this.http.delete<void>(`/api/v1/offers/${id}`)).then(() => undefined);
  }

  publish(id: number): Promise<Offer> {
    return firstValueFrom(this.http.post<{ data: Offer }>(`/api/v1/offers/${id}/publish`, {})).then(
      (r) => r.data,
    );
  }

  close(id: number): Promise<Offer> {
    return firstValueFrom(this.http.post<{ data: Offer }>(`/api/v1/offers/${id}/close`, {})).then(
      (r) => r.data,
    );
  }

  duplicate(id: number): Promise<Offer> {
    return firstValueFrom(
      this.http.post<{ data: Offer }>(`/api/v1/offers/${id}/duplicate`, {}),
    ).then((r) => r.data);
  }

  regenerateLink(id: number): Promise<Offer> {
    return firstValueFrom(
      this.http.post<{ data: Offer }>(`/api/v1/offers/${id}/regenerate-link`, {}),
    ).then((r) => r.data);
  }

  catalog(): Promise<CatalogField[]> {
    return firstValueFrom(
      this.http.get<{ data: CatalogField[] }>('/api/v1/form-field-catalog'),
    ).then((r) => r.data);
  }

  formFields(offerId: number): Promise<OfferFormField[]> {
    return firstValueFrom(
      this.http.get<{ data: OfferFormField[] }>(`/api/v1/offers/${offerId}/form-fields`),
    ).then((r) => r.data);
  }

  saveFormFields(offerId: number, fields: Partial<OfferFormField>[]): Promise<OfferFormField[]> {
    return firstValueFrom(
      this.http.put<{ data: OfferFormField[] }>(`/api/v1/offers/${offerId}/form-fields`, {
        fields,
      }),
    ).then((r) => r.data);
  }
}
