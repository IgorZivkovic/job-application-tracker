import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CompanySummary, JobApplication } from '../../models/job-tracker.model';
import { ApplicationDialogComponent } from './application-dialog.component';

describe('ApplicationDialogComponent', () => {
  let fixture: ComponentFixture<ApplicationDialogComponent>;
  let component: ApplicationDialogComponent;

  const companies: CompanySummary[] = [
    { id: 4, name: 'Northstar Labs' },
    { id: 5, name: 'Acme' },
  ];
  const application: JobApplication = {
    id: 12,
    company_id: 4,
    company: companies[0],
    position: 'Angular Developer',
    status: 'applied',
    board_order: 1,
    work_mode: 'remote',
    employment_type: 'full-time',
    source_url: 'https://northstar.example.com/jobs/angular',
    applied_at: '2026-09-10',
    next_action_at: '2026-09-20T12:00:00Z',
    salary_min: 60_000,
    salary_max: 75_000,
    currency: 'EUR',
    notes: 'Prepare portfolio.',
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ApplicationDialogComponent],
    }).compileComponents();
    fixture = TestBed.createComponent(ApplicationDialogComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('companies', companies);
  });

  it('requires a company and position and validates URL and currency formats', () => {
    component.form.patchValue({
      company_id: 0,
      position: '',
      source_url: 'not-a-url',
      currency: 'EURO',
    });

    expect(component.form.controls.company_id.invalid).toBe(true);
    expect(component.form.controls.position.hasError('required')).toBe(true);
    expect(component.form.controls.source_url.hasError('pattern')).toBe(true);
    expect(component.form.controls.currency.hasError('pattern')).toBe(true);
  });

  it('normalizes optional values and numbers before saving', () => {
    const save = vi.spyOn(component.save, 'emit');
    component.form.patchValue({
      company_id: 4,
      position: '  Angular Developer  ',
      status: 'applied',
      work_mode: 'remote',
      employment_type: '  full-time  ',
      source_url: '',
      applied_at: '2026-09-10',
      next_action_at: '',
      salary_min: '60000.50',
      salary_max: '',
      currency: 'eur',
      notes: '  Prepare portfolio.  ',
    });

    component.submit();

    expect(save).toHaveBeenCalledWith({
      company_id: 4,
      position: 'Angular Developer',
      status: 'applied',
      work_mode: 'remote',
      employment_type: 'full-time',
      source_url: null,
      applied_at: '2026-09-10',
      next_action_at: null,
      salary_min: 60_000.5,
      salary_max: null,
      currency: 'EUR',
      notes: 'Prepare portfolio.',
    });
  });

  it('fills the edit form and converts API dates to date input values', () => {
    fixture.componentRef.setInput('application', application);
    fixture.componentRef.setInput('mode', 'edit');
    fixture.componentRef.setInput('visible', true);
    fixture.detectChanges();

    expect(component.form.getRawValue()).toEqual({
      company_id: 4,
      position: 'Angular Developer',
      status: 'applied',
      work_mode: 'remote',
      employment_type: 'full-time',
      source_url: 'https://northstar.example.com/jobs/angular',
      applied_at: '2026-09-10',
      next_action_at: '2026-09-20',
      salary_min: '60000',
      salary_max: '75000',
      currency: 'EUR',
      notes: 'Prepare portfolio.',
    });
    expect(component.title).toBe('Edit application');
  });

  it('places backend validation messages on their form controls', () => {
    fixture.componentRef.setInput('visible', true);
    fixture.detectChanges();
    fixture.componentRef.setInput('fieldErrors', {
      salary_max: ['The salary max field must be greater than or equal to salary min.'],
    });
    fixture.detectChanges();

    expect(component.serverError('salary_max')).toBe(
      'The salary max field must be greater than or equal to salary min.',
    );

    component.form.controls.salary_max.setValue('90000');
    expect(component.serverError('salary_max')).toBeNull();
  });
});
