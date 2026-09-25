import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';

export type TemplateKey = 'confirmation' | 'invitation' | 'refusal';

export interface EmailTemplate {
  subject: string;
  body: string;
  customised: boolean;
}

const LABELS: Record<TemplateKey, { title: string; hint: string; placeholders: string[] }> = {
  confirmation: {
    title: 'Confirmation de candidature',
    hint: 'Envoyée au candidat dès réception de sa candidature.',
    placeholders: ['{candidate_name}', '{offer_title}'],
  },
  invitation: {
    title: 'Invitation à un entretien',
    hint: 'Envoyée au candidat quand un entretien est planifié (fichier .ics joint).',
    placeholders: ['{candidate_name}', '{offer_title}', '{starts_at}', '{duration}', '{location}'],
  },
  refusal: {
    title: 'Email de refus',
    hint: 'Envoyée au candidat si vous cochez « envoyer un email » lors du refus.',
    placeholders: ['{candidate_name}', '{offer_title}'],
  },
};

@Injectable({ providedIn: 'root' })
export class EmailTemplatesService {
  constructor(private readonly http: HttpClient) {}

  list(): Promise<Record<TemplateKey, EmailTemplate>> {
    return firstValueFrom(
      this.http.get<{ data: Record<TemplateKey, EmailTemplate> }>(
        '/api/v1/settings/email-templates',
      ),
    ).then((r) => r.data);
  }

  save(key: TemplateKey, subject: string, body: string): Promise<EmailTemplate> {
    return firstValueFrom(
      this.http.put<{ data: EmailTemplate }>(`/api/v1/settings/email-templates/${key}`, {
        subject,
        body,
      }),
    ).then((r) => r.data);
  }

  reset(key: TemplateKey): Promise<void> {
    return firstValueFrom(this.http.delete(`/api/v1/settings/email-templates/${key}`)).then(
      () => undefined,
    );
  }
}

export { LABELS as TEMPLATE_LABELS };
