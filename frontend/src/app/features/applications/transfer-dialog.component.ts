import {
  Component,
  ElementRef,
  EventEmitter,
  Input,
  OnChanges,
  Output,
  SimpleChanges,
  ViewChild,
  signal,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CreateForwardPayload, ForwardsService } from '../../core/api/forwards.service';
import { ListFilters, filterQueryParams } from './list-filters.component';

export interface TransferScope {
  applicationIds: number[];
  selectAll: boolean;
  offerId: number;
  filters: ListFilters;
  total: number;
}

/** EF-1001→1004: transfer selected candidates (or the whole filtered set) by email. */
@Component({
  selector: 'app-transfer-dialog',
  standalone: true,
  imports: [FormsModule],
  template: `
    @if (open) {
      <div class="backdrop" (click)="requestClose()"></div>
      <div
        #dialog
        class="dialog panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="transfer-title"
        tabindex="-1"
        (keydown.escape)="requestClose()"
        (keydown.tab)="trapTab($event)"
      >
        <h2 id="transfer-title">Transférer par email</h2>
        <p class="muted small">
          @if (scope?.selectAll) {
            {{ scope!.total }} candidature(s) correspondant aux filtres courants.
          } @else {
            {{ scope?.applicationIds.length ?? 0 }} candidature(s) sélectionnée(s).
          }
          Le statut des candidatures n'est jamais modifié.
        </p>

        <label>
          Destinataires (séparés par des virgules, 10 max)
          <input
            type="text"
            placeholder="client@example.com, partner@example.com"
            [(ngModel)]="toEmails"
          />
        </label>

        <label>
          Objet
          <input type="text" [(ngModel)]="subject" />
        </label>

        <label>
          Message
          <textarea rows="4" [(ngModel)]="message"></textarea>
        </label>

        <label class="check">
          <input type="checkbox" [(ngModel)]="includeAnalysis" />
          Inclure les analyses (score de correspondance et ATS)
        </label>

        @if (error()) {
          <p class="alert" role="alert">{{ error() }}</p>
        }

        <div class="actions">
          <button type="button" class="btn" (click)="requestClose()">Annuler</button>
          <button
            type="button"
            class="btn btn-primary"
            (click)="send()"
            [disabled]="sending() || !valid()"
          >
            {{ sending() ? 'Envoi en cours…' : 'Envoyer le transfert' }}
          </button>
        </div>
      </div>
    }
  `,
  styles: [
    `
      .backdrop {
        position: fixed;
        inset: 0;
        background: rgb(29 26 22 / 38%);
        backdrop-filter: blur(2px);
        animation: fade-in 200ms var(--ease);
        z-index: 40;
      }

      .dialog {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: min(560px, calc(100vw - 2rem));
        max-height: calc(100vh - 4rem);
        overflow-y: auto;
        padding: 1.4rem 1.5rem;
        border-radius: 12px;
        box-shadow: var(--shadow-md);
        z-index: 41;
        animation: dialog-in 260ms var(--ease);
      }

      .dialog:focus,
      .dialog:focus-visible {
        outline: none;
      }

      .dialog h2 {
        font-size: 1.2rem;
      }

      .dialog label {
        margin-top: 0.7rem;
      }

      .check {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: var(--ink-soft);
        margin-top: 0.8rem;
      }

      .check input {
        display: inline-block;
        width: auto;
        margin-top: 0;
      }

      .actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        margin-top: 1.1rem;
      }

      @keyframes fade-in {
        from {
          opacity: 0;
        }
      }

      @keyframes dialog-in {
        from {
          opacity: 0;
          transform: translate(-50%, -46%);
        }
      }
    `,
  ],
})
export class TransferDialogComponent implements OnChanges {
  @Input() open = false;
  @Input() scope: TransferScope | null = null;
  @Output() readonly close = new EventEmitter<void>();
  @Output() readonly sent = new EventEmitter<void>();

  @ViewChild('dialog') private dialog?: ElementRef<HTMLDivElement>;

  toEmails = '';
  subject = 'Candidatures';
  message = '';
  includeAnalysis = false;

  readonly sending = signal(false);
  readonly error = signal<string | null>(null);

  constructor(private readonly api: ForwardsService) {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['open']?.currentValue === true) {
      // Move focus into the dialog once it exists.
      setTimeout(() => this.dialog?.nativeElement.focus());
    }
  }

  requestClose(): void {
    if (this.sending()) return;
    this.close.emit();
  }

  trapTab(event: Event): void {
    const keyboard = event as KeyboardEvent;
    const root = this.dialog?.nativeElement;
    if (!root) return;
    const focusable = Array.from(
      root.querySelectorAll<HTMLElement>(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled])',
      ),
    );
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = document.activeElement;
    if (keyboard.shiftKey && (active === first || active === root)) {
      event.preventDefault();
      last.focus();
    } else if (!keyboard.shiftKey && active === last) {
      event.preventDefault();
      first.focus();
    }
  }

  valid(): boolean {
    const emails = this.splitEmails();
    return emails.length > 0 && emails.length <= 10 && this.subject.trim() !== '';
  }

  async send(): Promise<void> {
    const scope = this.scope;
    if (!scope || !this.valid()) return;

    this.sending.set(true);
    this.error.set(null);

    const payload: CreateForwardPayload = {
      to_emails: this.splitEmails(),
      subject: this.subject.trim(),
      message: this.message.trim() || undefined,
      include_analysis: this.includeAnalysis,
    };

    if (scope.selectAll) {
      payload.offer_id = scope.offerId;
      payload.select_all = true;
      payload.filters = filterQueryParams(scope.filters) as Record<string, unknown>;
    } else {
      payload.application_ids = scope.applicationIds;
    }

    try {
      await this.api.create(payload);
      this.sent.emit();
    } catch (err) {
      this.error.set(this.readError(err));
    } finally {
      this.sending.set(false);
    }
  }

  private splitEmails(): string[] {
    return this.toEmails
      .split(/[,;\s]+/)
      .map((email) => email.trim())
      .filter((email) => email !== '');
  }

  private readError(err: unknown): string {
    const body = (err as { error?: { message?: string; errors?: Record<string, string[]> } })
      ?.error;
    if (body?.message) return body.message;
    const first = body?.errors ? Object.values(body.errors)[0]?.[0] : undefined;
    return first ?? 'Transfert impossible.';
  }
}
