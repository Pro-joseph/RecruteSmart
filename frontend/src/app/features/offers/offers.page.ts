import { Component, OnInit, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Offer, OffersService } from '../../core/api/offers.service';
import { CelebrationComponent } from '../../core/ui/celebration.component';

const STATUS_LABELS: Record<string, string> = {
  draft: 'Brouillon',
  published: 'Publiée',
  closed: 'Clôturée',
  archived: 'Archivée',
};

const STATUS_CLASSES: Record<string, string> = {
  draft: 'badge-amber',
  published: 'badge-green',
  closed: 'badge-blue',
  archived: '',
};

const FIRST_OFFER_KEY = 'recrutesmart.first-offer.seen';

@Component({
  selector: 'app-offers',
  standalone: true,
  imports: [RouterLink, FormsModule, CelebrationComponent],
  template: `
    <div class="page-head">
      <h1>Offres</h1>
      <a class="btn btn-primary" routerLink="/app/offers/new">Nouvelle offre</a>
    </div>

    <p class="toolbar">
      <label class="inline-label">
        Statut
        <select [(ngModel)]="statusFilter" (ngModelChange)="load()">
          <option value="">Tous</option>
          <option value="draft">Brouillons</option>
          <option value="published">Publiées</option>
          <option value="closed">Clôturées</option>
          <option value="archived">Archivées</option>
        </select>
      </label>
    </p>

    @if (error()) {
      <p role="alert" class="alert">{{ error() }}</p>
    }
    @if (notice()) {
      <p role="status">{{ notice() }}</p>
    }

    @if (loading()) {
      <ul class="item-list" aria-label="Chargement des offres">
        @for (r of [1, 2, 3]; track r) {
          <li class="item-row">
            <span class="skeleton" style="width: 35%"></span>
            <span class="skeleton" style="width: 18%"></span>
          </li>
        }
      </ul>
    } @else {
      <ul class="item-list">
        @for (offer of offers(); track offer.id) {
          <li class="item-row" [class.tumble-out]="removingId() === offer.id">
            @if (confirmId() === offer.id) {
              <span class="confirm-text">
                Supprimer <strong>{{ offer.title }}</strong> ? Seules les offres sans candidature
                peuvent être supprimées.
              </span>
              <span class="row-actions">
                <button
                  type="button"
                  class="btn btn-danger"
                  [disabled]="busy()"
                  [class.is-busy]="busy()"
                  (click)="removeConfirmed(offer.id)"
                >
                  {{ busy() ? 'Suppression…' : 'Supprimer' }}
                </button>
                <button type="button" class="btn btn-ghost" (click)="confirmId.set(null)">
                  Annuler
                </button>
              </span>
            } @else {
              <span class="offer-main">
                <strong>{{ offer.title }}</strong>
                <span class="badge {{ STATUS_CLASSES[offer.status] }}">
                  {{ statusLabel(offer.status) }}
                </span>
              </span>
              <span class="row-actions">
                @if (offer.public_url) {
                  <button type="button" class="btn btn-ghost" (click)="copy(offer.public_url!)">
                    Copier le lien
                  </button>
                }
                <a class="btn btn-ghost" [routerLink]="['/app/offers', offer.id]"> Candidatures </a>
                <a class="btn btn-ghost" [routerLink]="['/app/offers', offer.id, 'edit']">
                  Modifier
                </a>
                @if (offer.status === 'draft' || offer.status === 'closed') {
                  <button type="button" class="btn" (click)="publish(offer.id)">Publier</button>
                }
                @if (offer.status === 'published') {
                  <button type="button" class="btn" (click)="close(offer.id)">Clôturer</button>
                }
                <button type="button" class="btn btn-ghost" (click)="duplicate(offer.id)">
                  Dupliquer
                </button>
                <button type="button" class="btn btn-danger" (click)="confirmId.set(offer.id)">
                  Supprimer
                </button>
              </span>
            }
          </li>
        } @empty {
          <li class="empty">
            @if (statusFilter) {
              <h3>Aucune offre {{ statusLabel(statusFilter).toLowerCase() }}</h3>
              <p>Aucun résultat avec ce filtre.</p>
              <p>
                <button type="button" class="btn" (click)="statusFilter = ''; load()">
                  Voir toutes les offres
                </button>
              </p>
            } @else {
              <h3>Aucune offre pour l'instant</h3>
              <p>
                Publiez votre première offre
                <span class="hint-arrow">→</span>
                puis partagez son lien pour recevoir des candidatures.
              </p>
              <p>
                <a class="btn btn-primary" routerLink="/app/offers/new">Créer une offre</a>
              </p>
            }
          </li>
        }
      </ul>
    }

    @if (celebrate()) {
      <app-celebration (finished)="celebrate.set(false)" />
    }
  `,
})
export class OffersPage implements OnInit {
  readonly STATUS_CLASSES = STATUS_CLASSES;

  readonly offers = signal<Offer[]>([]);
  readonly loading = signal(true);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);
  readonly confirmId = signal<number | null>(null);
  readonly removingId = signal<number | null>(null);
  readonly busy = signal(false);
  readonly celebrate = signal(false);
  statusFilter = '';

  constructor(
    private readonly api: OffersService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    void this.load();
  }

  statusLabel(status: string): string {
    return STATUS_LABELS[status] ?? status;
  }

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    const hadOffers = this.offers().length > 0;
    try {
      const list = await this.api.list(this.statusFilter || undefined);
      this.offers.set(list);
      if (!hadOffers && list.length === 1 && !sessionStorage.getItem(FIRST_OFFER_KEY)) {
        sessionStorage.setItem(FIRST_OFFER_KEY, '1');
        this.celebrate.set(true);
      }
    } catch {
      this.error.set('Impossible de charger les offres.');
    } finally {
      this.loading.set(false);
    }
  }

  async copy(url: string): Promise<void> {
    await navigator.clipboard.writeText(url);
    this.notice.set('Lien copié.');
  }

  async publish(id: number): Promise<void> {
    try {
      await this.api.publish(id);
      await this.load();
      this.celebrate.set(true);
    } catch {
      this.error.set('La publication a échoué.');
    }
  }

  async close(id: number): Promise<void> {
    try {
      await this.api.close(id);
      await this.load();
    } catch {
      this.error.set('La clôture a échoué.');
    }
  }

  async duplicate(id: number): Promise<void> {
    try {
      const copy = await this.api.duplicate(id);
      await this.router.navigate(['/app/offers', copy.id, 'edit']);
    } catch {
      this.error.set('La duplication a échoué.');
    }
  }

  async removeConfirmed(id: number): Promise<void> {
    this.busy.set(true);
    try {
      await this.api.delete(id);
      this.confirmId.set(null);
      this.removingId.set(id);
      await new Promise((r) => setTimeout(r, 320));
      await this.load();
    } catch {
      this.error.set('Suppression refusée : clôturez ou archivez les offres avec candidatures.');
      this.confirmId.set(null);
    } finally {
      this.busy.set(false);
      this.removingId.set(null);
    }
  }
}
