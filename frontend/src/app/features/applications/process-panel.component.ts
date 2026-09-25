import { Component, EventEmitter, Input, Output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApplicationsService, Interview, InterviewType } from '../../core/api/applications.service';

const INTERVIEW_TYPES: { value: string; label: string }[] = [
  { value: 'phone', label: 'Téléphone' },
  { value: 'video', label: 'Visio' },
  { value: 'onsite', label: 'Présentiel' },
];

const INTERVIEW_STATUSES: { value: string; label: string }[] = [
  { value: 'planned', label: 'Planifié' },
  { value: 'done', label: 'Effectué' },
  { value: 'rescheduled', label: 'Reporté' },
  { value: 'canceled', label: 'Annulé' },
];

const DECISIONS: { value: string; label: string }[] = [
  { value: 'proceed', label: 'Poursuivre → Proposition' },
  { value: 'reject', label: 'Refuser' },
];

/** EF-902/903/904 + EF-905: schedule interviews, follow them up, refuse the candidate. */
@Component({
  selector: 'app-process-panel',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section class="panel section">
      <h2>Entretiens</h2>
      @if (error()) {
        <p class="alert" role="alert">{{ error() }}</p>
      }

      <div class="interview-form">
        <label>
          Type
          <select [(ngModel)]="plan.type">
            @for (t of interviewTypes; track t.value) {
              <option [value]="t.value">{{ t.label }}</option>
            }
          </select>
        </label>
        <label>
          Date et heure
          <input type="datetime-local" [(ngModel)]="plan.starts_at" />
        </label>
        <label>
          Durée (min)
          <input type="number" min="5" max="480" step="5" [(ngModel)]="plan.duration_minutes" />
        </label>
        <label class="grow">
          Lieu ou lien
          <input
            type="text"
            placeholder="https://meet… ou adresse"
            [(ngModel)]="plan.location_or_link"
          />
        </label>
        <label class="grow">
          Participants (séparés par des virgules)
          <input type="text" [(ngModel)]="plan.participants" />
        </label>
        <button
          type="button"
          class="btn btn-primary"
          (click)="planInterview()"
          [disabled]="saving() || !plan.starts_at"
        >
          Planifier l'entretien
        </button>
      </div>
      <p class="muted small">
        Planifier fait passer le candidat au statut « Entretien » et envoie une invitation avec le
        fichier .ics.
      </p>

      @for (interview of interviews(); track interview.id) {
        <article class="interview-card">
          <header class="interview-head">
            <span class="badge badge-teal">{{ typeLabel(interview.type) }}</span>
            <span class="muted small">
              {{ formatDateTime(interview.starts_at) }} · {{ interview.duration_minutes }} min
            </span>
            @if (interview.invitation_sent_at) {
              <span class="muted small">· invitation envoyée</span>
            }
          </header>
          @if (interview.location_or_link) {
            <p class="muted small">{{ interview.location_or_link }}</p>
          }
          @if (interview.participants.length) {
            <p class="muted small">Participants : {{ interview.participants.join(', ') }}</p>
          }

          <div class="interview-controls">
            <label>
              Statut
              <select
                [ngModel]="interview.status"
                (ngModelChange)="setInterviewStatus(interview, $event)"
              >
                @for (s of interviewStatuses; track s.value) {
                  <option [value]="s.value">{{ s.label }}</option>
                }
              </select>
            </label>
            <label>
              Décision
              <select
                [ngModel]="interview.decision ?? ''"
                (ngModelChange)="setInterviewDecision(interview, $event)"
              >
                <option value="">—</option>
                @for (d of decisions; track d.value) {
                  <option [value]="d.value">{{ d.label }}</option>
                }
              </select>
            </label>
          </div>

          <label>
            Compte-rendu
            <textarea
              rows="3"
              placeholder="Points abordés, verdict du recruteur…"
              [(ngModel)]="interview.notes"
            ></textarea>
          </label>
          <button
            type="button"
            class="btn"
            (click)="saveInterview(interview)"
            [disabled]="saving()"
          >
            Enregistrer le compte-rendu
          </button>
        </article>
      } @empty {
        <p class="muted">Aucun entretien programmé.</p>
      }
    </section>

    <section class="panel section">
      <h2>Refuser la candidature</h2>
      <p class="muted small">
        Motif interne conservé dans l'historique (EF-905) ; l'email de refus est optionnel.
      </p>
      <label>
        Motif interne
        <textarea rows="2" [(ngModel)]="rejectDraft.reason"></textarea>
      </label>
      <label class="check">
        <input type="checkbox" [(ngModel)]="rejectDraft.send_email" />
        Envoyer un email de refus au candidat
      </label>
      @if (rejectDraft.send_email) {
        <label>
          Message au candidat (optionnel)
          <textarea rows="3" [(ngModel)]="rejectDraft.message"></textarea>
        </label>
      }
      <button
        type="button"
        class="btn btn-danger"
        (click)="rejectCandidate()"
        [disabled]="saving() || !rejectDraft.reason.trim()"
      >
        Refuser le candidat
      </button>
    </section>
  `,
  styles: [
    `
      .section {
        padding: 1.1rem 1.25rem;
        margin-bottom: 1.25rem;
      }

      .section h2 {
        font-size: 1.05rem;
        margin-bottom: 0.75rem;
      }

      .small {
        font-size: 0.82rem;
      }

      .interview-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.75rem;
        align-items: end;
        padding: 0.85rem;
        background: var(--surface-sunken);
        border: 1px dashed var(--line-strong);
        border-radius: 9px;
        margin-bottom: 0.5rem;
      }

      .interview-form .grow {
        grid-column: span 2;
      }

      .interview-card {
        margin-top: 1rem;
        padding: 0.9rem 1rem;
        border: 1px solid var(--line);
        border-radius: 9px;
        animation: card-in 300ms var(--ease);
      }

      .interview-head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 0.35rem;
      }

      .interview-controls {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin: 0.75rem 0;
      }

      .interview-card .btn {
        margin-top: 0.6rem;
      }

      .check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: var(--ink-soft);
        margin: 0.6rem 0;
      }

      .check input {
        display: inline-block;
        width: auto;
        margin-top: 0;
      }

      @keyframes card-in {
        from {
          opacity: 0;
          transform: translateY(6px);
        }
      }
    `,
  ],
})
export class ProcessPanelComponent {
  @Input({ required: true }) appId = 0;
  @Output() readonly changed = new EventEmitter<void>();

  readonly interviews = signal<Interview[]>([]);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);

  readonly interviewTypes = INTERVIEW_TYPES;
  readonly interviewStatuses = INTERVIEW_STATUSES;
  readonly decisions = DECISIONS;

  plan: {
    type: InterviewType;
    starts_at: string;
    duration_minutes: number;
    location_or_link: string;
    participants: string;
  } = {
    type: 'video',
    starts_at: '',
    duration_minutes: 30,
    location_or_link: '',
    participants: '',
  };

  rejectDraft = { reason: '', send_email: false, message: '' };

  constructor(private readonly api: ApplicationsService) {}

  ngOnInit(): void {
    void this.load();
  }

  async load(): Promise<void> {
    try {
      const fiche = await this.api.get(this.appId);
      this.interviews.set(fiche.interviews ?? []);
      this.error.set(null);
    } catch {
      this.error.set('Impossible de charger les entretiens.');
    }
  }

  async planInterview(): Promise<void> {
    if (!this.plan.starts_at) return;
    this.saving.set(true);
    try {
      await this.api.planInterview(this.appId, {
        type: this.plan.type,
        starts_at: new Date(this.plan.starts_at).toISOString(),
        duration_minutes: this.plan.duration_minutes,
        location_or_link: this.plan.location_or_link || undefined,
        participants: this.plan.participants
          .split(',')
          .map((p) => p.trim())
          .filter((p) => p !== ''),
      });
      this.plan.starts_at = '';
      this.plan.location_or_link = '';
      this.plan.participants = '';
      await this.load();
      this.changed.emit();
    } catch {
      this.error.set("Planification d'entretien impossible.");
    } finally {
      this.saving.set(false);
    }
  }

  setInterviewStatus(interview: Interview, status: string): void {
    void this.saveInterview(interview, { status: status as Interview['status'] });
  }

  setInterviewDecision(interview: Interview, decision: string): void {
    void this.saveInterview(
      interview,
      { decision: decision === '' ? undefined : (decision as Interview['decision']) },
      decision === '',
    );
  }

  async saveInterview(
    interview: Interview,
    extra: Record<string, unknown> = {},
    clearDecision = false,
  ): Promise<void> {
    this.saving.set(true);
    try {
      const payload: Record<string, unknown> = { ...extra };
      if (Object.keys(extra).length === 0) payload['notes'] = interview.notes;
      if (clearDecision) payload['decision'] = null;
      await this.api.updateInterview(interview.id, payload as never);
      await this.load();
      this.changed.emit();
    } catch {
      this.error.set("Enregistrement de l'entretien impossible.");
    } finally {
      this.saving.set(false);
    }
  }

  async rejectCandidate(): Promise<void> {
    if (!this.rejectDraft.reason.trim()) return;
    this.saving.set(true);
    try {
      await this.api.reject(this.appId, {
        reason: this.rejectDraft.reason.trim(),
        send_email: this.rejectDraft.send_email,
        message: this.rejectDraft.message.trim() || undefined,
      });
      this.rejectDraft = { reason: '', send_email: false, message: '' };
      this.changed.emit();
    } catch {
      this.error.set('Refus impossible.');
    } finally {
      this.saving.set(false);
    }
  }

  typeLabel(type: string): string {
    return INTERVIEW_TYPES.find((t) => t.value === type)?.label ?? type;
  }

  formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
