import { TestBed } from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import { ApplyPage } from './apply.page';
import { PublicApplyService, PublicOffer } from './public-apply.service';

const OFFER: PublicOffer = {
  title: 'Développeur',
  type: 'full_time',
  description: 'Une offre de test.',
  accepting_applications: true,
  form_fields: [
    {
      key: 'full_name',
      label: 'Nom complet',
      type: 'text',
      is_required: true,
      is_locked: true,
      is_sensitive: false,
    },
    {
      key: 'email',
      label: 'Email',
      type: 'email',
      is_required: true,
      is_locked: true,
      is_sensitive: false,
    },
    {
      key: 'cv',
      label: 'CV',
      type: 'file',
      is_required: true,
      is_locked: true,
      is_sensitive: false,
    },
    {
      key: 'portfolio',
      label: 'GitHub ou portfolio',
      type: 'url',
      is_required: false,
      is_locked: false,
      is_sensitive: false,
    },
    {
      key: 'linkedin',
      label: 'LinkedIn',
      type: 'url',
      is_required: false,
      is_locked: false,
      is_sensitive: false,
    },
  ],
};

class StubPublicApplyService {
  sent: FormData | null = null;

  getOffer(): Promise<PublicOffer> {
    return Promise.resolve(OFFER);
  }

  submit(_token: string, form: FormData): Promise<{ message: string }> {
    this.sent = form;
    return Promise.resolve({ message: 'Candidature envoyée.' });
  }
}

describe('ApplyPage', () => {
  let stub: StubPublicApplyService;

  beforeEach(async () => {
    stub = new StubPublicApplyService();
    await TestBed.configureTestingModule({
      imports: [ApplyPage],
      providers: [
        { provide: PublicApplyService, useValue: stub },
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: { get: () => 'tok' } } } },
      ],
    }).compileComponents();
  });

  it('sends dynamic field answers with the application', async () => {
    const fixture = TestBed.createComponent(ApplyPage);
    fixture.detectChanges();
    await fixture.componentInstance.load();

    const c = fixture.componentInstance.form;
    c.get('full_name')?.setValue('Youssef');
    c.get('email')?.setValue('youssef@example.com');
    c.get('consent')?.setValue(true);
    c.get('portfolio')?.setValue('https://github.com/youssef');
    c.get('linkedin')?.setValue('https://linkedin.com/in/youssef');
    fixture.componentInstance.onFile('cv', {
      target: { files: [new File(['x'], 'cv.pdf')] },
    } as unknown as Event);

    await fixture.componentInstance.submit();

    expect(stub.sent).not.toBeNull();
    expect(stub.sent!.get('answers[portfolio]')).toBe('https://github.com/youssef');
    expect(stub.sent!.get('answers[linkedin]')).toBe('https://linkedin.com/in/youssef');
    expect(stub.sent!.get('full_name')).toBe('Youssef');
    expect(fixture.componentInstance.state()).toBe('done');
  });

  it('renders one input per dynamic form field', async () => {
    const fixture = TestBed.createComponent(ApplyPage);
    fixture.detectChanges();
    await fixture.componentInstance.load();
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    expect(html.textContent).toContain('GitHub ou portfolio');
    expect(html.textContent).toContain('LinkedIn');
    expect(fixture.componentInstance.dynamicFields().length).toBe(2);
  });
});
