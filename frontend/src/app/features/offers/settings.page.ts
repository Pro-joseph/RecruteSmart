import { Component, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import {
  EmailTemplate,
  EmailTemplatesService,
  TEMPLATE_LABELS,
  TemplateKey,
} from '../../core/api/email-templates.service';

const KEYS: TemplateKey[] = ['confirmation', 'invitation', 'refusal'];

@Component({
  selector: 'app-settings-page',
  standalone: true,
  imports: [RouterLink, FormsModule],
  template: `
    <p><a routerLink="/app/offers">← Offres</a></p>
    <h1>Paramètres</h1>

    <h2 class="section-title">Modèles d'emails</h2>
    <p class="muted">
      Personnalisez la confirmation, l'invitation à l'entretien et l'email de refus (EF-906).
      Utilisez les espaces réservés comme <code>{{ sample }}</code
      >.
    </p>

    @if (error()) {
      <p class="alert" role="alert">{{ error() }}</p>
    }
    @if (notice()) {
      <p class="notice" role="status">{{ notice() }}</p>
    }

    @if (loading()) {
      <section class="panel card" aria-label="Chargement des modèles">
        <span class="skeleton" style="width: 35%; height: 1.1rem"></span>
        <span class="skeleton" style="width: 80%"></span>
        <span class="skeleton" style="width: 65%; height: 5rem"></span>
      </section>
      <section class="panel card" aria-hidden="true">
        <span class="skeleton" style="width: 30%; height: 1.1rem"></span>
        <span class="skeleton" style="width: 75%"></span>
        <span class="skeleton" style="width: 60%; height: 5rem"></span>
      </section>
    } @else {
      @for (key of keys; track key) {
        <section class="panel card">
          <header class="card-head">
            <div>
              <h3>{{ labels[key].title }}</h3>
              <p class="muted small">{{ labels[key].hint }}</p>
            </div>
            @if (drafts[key]()?.customised) {
              <span class="badge badge-teal">personnalisé</span>
            } @else {
              <span class="badge">modèle par défaut</span>
            }
          </header>

          <label>
            Objet
            <input type="text" [(ngModel)]="subjects[key]" />
          </label>

          <label>
            Corps du message
            <textarea rows="7" [(ngModel)]="bodies[key]"></textarea>
          </label>

          <div class="placeholders">
            <span class="muted small">Variables :</span>
            @for (p of labels[key].placeholders; track p) {
              <code class="chip">{{ p }}</code>
            }
          </div>

          <div class="actions">
            @if (confirmReset() === key) {
              <span class="confirm-text">Réinitialiser ce modèle ?</span>
              <button type="button" class="btn btn-danger" (click)="reset(key)" [disabled]="busy()">
                {{ busy() ? 'Réinitialisation…' : 'Confirmer' }}
              </button>
              <button
                type="button"
                class="btn btn-ghost"
                (click)="confirmReset.set(null)"
                [disabled]="busy()"
              >
                Annuler
              </button>
            } @else {
              @if (drafts[key]()?.customised) {
                <button
                  type="button"
                  class="btn btn-ghost"
                  (click)="confirmReset.set(key)"
                  [disabled]="busy()"
                >
                  Réinitialiser
                </button>
              }
              <button
                type="button"
                class="btn btn-primary"
                [class.is-busy]="busy()"
                (click)="save(key)"
                [disabled]="busy()"
              >
                {{ busy() ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
            }
          </div>
        </section>
      }
    }
  `,
  styles: [
    `
      .section-title {
        margin-top: 1.5rem;
      }

      .small {
        font-size: 0.85rem;
      }

      code {
        font-family: ui-monospace, 'Cascadia Code', Consolas, monospace;
        font-size: 0.85em;
      }

      .card {
        padding: 1.15rem 1.3rem;
        margin: 1rem 0;
      }

      .card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 0.75rem;
      }

      .card-head h3 {
        font-family: var(--font-display);
        font-size: 1.05rem;
        margin-bottom: 0.2rem;
      }

      .card label {
        margin-top: 0.7rem;
      }

      .placeholders {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: wrap;
        margin-top: 0.7rem;
      }

      .actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        margin-top: 1rem;
      }

      .notice {
        padding: 0.6rem 0.9rem;
        border: 1px solid var(--accent-soft);
        border-radius: 8px;
        background: var(--accent-soft);
        color: var(--accent);
        font-size: 0.9rem;
        font-weight: 500;
      }
    `,
  ],
})
export class SettingsPage implements OnInit {
  readonly sample = '{candidate_name}';
  readonly keys = KEYS;
  readonly labels = TEMPLATE_LABELS;

  readonly drafts = {
    confirmation: signal<EmailTemplate | null>(null),
    invitation: signal<EmailTemplate | null>(null),
    refusal: signal<EmailTemplate | null>(null),
  };

  subjects: Record<TemplateKey, string> = { confirmation: '', invitation: '', refusal: '' };
  bodies: Record<TemplateKey, string> = { confirmation: '', invitation: '', refusal: '' };

  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);
  readonly confirmReset = signal<TemplateKey | null>(null);

  constructor(private readonly api: EmailTemplatesService) {}

  ngOnInit(): void {
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    try {
      const data = await this.api.list();
      for (const key of KEYS) {
        this.drafts[key].set(data[key]);
        this.subjects[key] = data[key].subject;
        this.bodies[key] = data[key].body;
      }
      this.error.set(null);
    } catch {
      this.error.set('Impossible de charger les modèles.');
    } finally {
      this.loading.set(false);
    }
  }

  async save(key: TemplateKey): Promise<void> {
    this.busy.set(true);
    this.notice.set(null);
    try {
      const saved = await this.api.save(key, this.subjects[key], this.bodies[key]);
      this.drafts[key].set(saved);
      this.notice.set('Modèle enregistré.');
    } catch {
      this.error.set('Enregistrement impossible.');
    } finally {
      this.busy.set(false);
    }
  }

  async reset(key: TemplateKey): Promise<void> {
    this.busy.set(true);
    this.notice.set(null);
    try {
      await this.api.reset(key);
      await this.load();
      this.notice.set('Modèle réinitialisé.');
      this.confirmReset.set(null);
    } catch {
      this.error.set('Réinitialisation impossible.');
      this.confirmReset.set(null);
    } finally {
      this.busy.set(false);
    }
  }
}
