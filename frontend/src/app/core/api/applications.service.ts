import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

export interface FicheAnswer {
  key: string;
  label: string;
  type: string;
  value: string | null;
}

export interface FicheFile {
  key: string;
  name: string;
  mime: string;
  size: number;
  url: string;
}

export interface AtsCheck {
  code: string;
  points: number;
  max: number;
  passed: boolean;
  message: string;
  advice: string;
}

export interface MatchCriterion {
  score: number;
  weight: number;
  evidence: string;
}

export interface FicheAnalysis {
  status: 'pending' | 'processing' | 'completed' | 'failed';
  match_score: number | null;
  match_breakdown: Record<string, MatchCriterion> | null;
  knockout_flags: Record<string, boolean> | null;
  ats_score: number | null;
  ats_verdict: 'compliant' | 'improvable' | 'non_compliant' | null;
  ats_checks: AtsCheck[] | null;
  summary: string | null;
  strengths: string[] | null;
  gaps: string[] | null;
  anomalies: string[] | null;
  years_experience: number | null;
  city: string | null;
  country: string | null;
  skills: string[];
  is_stale: boolean;
  error_code: string | null;
  error_message: string | null;
  analyzed_at: string | null;
}

export interface ApplicationFiche {
  id: number;
  full_name: string;
  email: string;
  phone: string | null;
  status: string;
  rating: number | null;
  created_at: string;
  offer: { id: number; title: string };
  answers: FicheAnswer[];
  files: FicheFile[];
  analysis: FicheAnalysis | null;
}

export interface ApplicationEvent {
  id: number;
  type: string;
  payload: Record<string, unknown>;
  user: { id: number; name: string; email: string } | null;
  created_at: string;
}

@Injectable({ providedIn: 'root' })
export class ApplicationsService {
  constructor(private readonly http: HttpClient) {}

  get(id: number): Promise<ApplicationFiche> {
    return firstValueFrom(
      this.http.get<{ data: ApplicationFiche }>(`/api/v1/applications/${id}`),
    ).then((r) => r.data);
  }

  events(id: number): Promise<ApplicationEvent[]> {
    return firstValueFrom(
      this.http.get<{ data: ApplicationEvent[] }>(`/api/v1/applications/${id}/events`),
    ).then((r) => r.data);
  }

  setStatus(id: number, status: string): Promise<string> {
    return firstValueFrom(
      this.http.patch<{ data: { status: string } }>(`/api/v1/applications/${id}/status`, {
        status,
      }),
    ).then((r) => r.data.status);
  }

  addNote(id: number, payload: { note?: string; rating?: number }): Promise<number | null> {
    return firstValueFrom(
      this.http
        .post<{ data: { rating: number | null } }>(`/api/v1/applications/${id}/notes`, payload)
        .pipe(),
    ).then((r) => r.data.rating);
  }

  /** Authenticated fetch of a private file (CV) as an object URL for preview. */
  async blobUrl(url: string): Promise<string> {
    const response = await fetch(url, { credentials: 'include' });
    if (!response.ok) throw new Error(`Download failed: ${response.status}`);
    return URL.createObjectURL(await response.blob());
  }
}
