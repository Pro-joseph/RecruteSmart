import { TestBed } from '@angular/core/testing';
import { FormEditorComponent } from './form-editor.component';
import { CatalogField, OfferFormField, OffersService } from '../../core/api/offers.service';

const LINKEDIN: CatalogField = {
  key: 'linkedin',
  label: 'LinkedIn',
  type: 'url',
  locked: false,
  sensitive: false,
  required: false,
};

const LOCKED_FIELDS: OfferFormField[] = ['full_name', 'email', 'cv'].map((key, i) => ({
  id: i + 1,
  key,
  label: key,
  type: key === 'cv' ? 'file' : key === 'email' ? 'email' : 'text',
  is_required: true,
  is_locked: true,
  is_sensitive: false,
  is_hidden: false,
  position: i,
}));

class StubOffersService {
  savedPayload: Partial<OfferFormField>[] | null = null;

  catalog(): Promise<CatalogField[]> {
    return Promise.resolve([LINKEDIN]);
  }

  formFields(): Promise<OfferFormField[]> {
    return Promise.resolve(LOCKED_FIELDS);
  }

  saveFormFields(offerId: number, fields: Partial<OfferFormField>[]): Promise<OfferFormField[]> {
    this.savedPayload = fields;
    return Promise.resolve(
      fields.map((f, i) => ({
        id: i + 1,
        is_locked: false,
        is_sensitive: false,
        options: null,
        rules: null,
        ...f,
      })) as OfferFormField[],
    );
  }
}

describe('FormEditorComponent', () => {
  let stub: StubOffersService;

  beforeEach(async () => {
    stub = new StubOffersService();
    await TestBed.configureTestingModule({
      imports: [FormEditorComponent],
      providers: [{ provide: OffersService, useValue: stub }],
    }).compileComponents();
  });

  it('adds the LinkedIn catalog field to the list', async () => {
    const fixture = TestBed.createComponent(FormEditorComponent);
    fixture.componentInstance.offerId = 1;
    fixture.detectChanges();
    await fixture.componentInstance.load();
    fixture.detectChanges();

    const select = (fixture.nativeElement as HTMLElement).querySelector(
      'select',
    ) as HTMLSelectElement;
    select.value = 'linkedin';
    select.dispatchEvent(new Event('change'));
    await fixture.whenStable();
    fixture.detectChanges();

    const addButton = Array.from(
      (fixture.nativeElement as HTMLElement).querySelectorAll('button'),
    ).find((b) => b.textContent?.includes('Ajouter'))!;
    expect(addButton.hasAttribute('disabled')).toBe(false);
    addButton.click();
    fixture.detectChanges();

    expect(fixture.componentInstance.fields().some((f) => f.key === 'linkedin')).toBe(true);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('LinkedIn');
  });

  it('saves without sending server-managed is_locked / is_sensitive flags', async () => {
    const fixture = TestBed.createComponent(FormEditorComponent);
    fixture.componentInstance.offerId = 1;
    fixture.detectChanges();
    await fixture.componentInstance.load();

    fixture.componentInstance.selectedKey = 'linkedin';
    fixture.componentInstance.add();
    expect(fixture.componentInstance.fields().some((f) => f.key === 'linkedin')).toBe(true);
    expect(stub.savedPayload).toBeNull();

    await fixture.componentInstance.save();

    expect(stub.savedPayload).not.toBeNull();
    expect(stub.savedPayload!.some((f) => f.key === 'linkedin')).toBe(true);
    for (const field of stub.savedPayload!) {
      expect(Object.keys(field)).not.toContain('is_locked');
      expect(Object.keys(field)).not.toContain('is_sensitive');
      expect(Object.keys(field)).not.toContain('fromCatalog');
      expect(Object.keys(field)).not.toContain('id');
    }
    expect(fixture.componentInstance.error()).toBeNull();
    expect(fixture.componentInstance.notice()).toBe('Formulaire enregistré.');
  });
});
