import { HttpErrorResponse } from '@angular/common/http';
import { signal, WritableSignal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { ApiOperationError } from '../../models/api.model';
import { Interview, InterviewPayload, JobApplicationDetail } from '../../models/job-tracker.model';
import { ApiErrorService } from '../../services/api-error.service';
import { InterviewService } from '../../services/interview.service';
import { JobApplicationService } from '../../services/job-application.service';
import { ApplicationDetailsPageComponent } from './application-details-page.component';

describe('ApplicationDetailsPageComponent', () => {
  let fixture: ComponentFixture<ApplicationDetailsPageComponent>;
  let component: ApplicationDetailsPageComponent;
  let applicationService: { get: ReturnType<typeof vi.fn> };
  let interviewService: {
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    remove: ReturnType<typeof vi.fn>;
  };
  let apiError: WritableSignal<ApiOperationError | null>;

  const upcomingInterview: Interview = {
    id: 8,
    job_application_id: 12,
    type: 'technical',
    scheduled_at: '2099-09-20T08:30:00Z',
    contact_name: 'Alex Recruiter',
    contact_email: 'alex@example.com',
    location_or_link: 'https://meet.example.com/interview',
    notes: null,
    outcome: null,
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };
  const completedInterview: Interview = {
    ...upcomingInterview,
    id: 7,
    type: 'screening',
    scheduled_at: '2020-09-18T08:30:00Z',
    outcome: 'passed',
  };
  const application: JobApplicationDetail = {
    id: 12,
    company_id: 4,
    company: { id: 4, name: 'Northstar Labs' },
    position: 'Angular Developer',
    status: 'interview',
    board_order: 1,
    work_mode: 'remote',
    employment_type: 'full-time',
    source_url: 'https://northstar.example.com/jobs/angular',
    applied_at: '2026-09-10',
    next_action_at: '2099-09-20T08:30:00Z',
    salary_min: 60_000,
    salary_max: 75_000,
    currency: 'EUR',
    notes: 'Prepare portfolio.',
    interviews: [upcomingInterview, completedInterview],
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };

  beforeEach(async () => {
    applicationService = { get: vi.fn(() => of(application)) };
    interviewService = {
      create: vi.fn(() => of(upcomingInterview)),
      update: vi.fn(() => of(upcomingInterview)),
      remove: vi.fn(() => of({ deleted: true })),
    };
    apiError = signal<ApiOperationError | null>(null);

    await TestBed.configureTestingModule({
      imports: [ApplicationDetailsPageComponent],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { paramMap: of(convertToParamMap({ id: '12' })) },
        },
        { provide: JobApplicationService, useValue: applicationService },
        { provide: InterviewService, useValue: interviewService },
        {
          provide: ApiErrorService,
          useValue: { lastError: apiError.asReadonly(), clear: vi.fn(() => apiError.set(null)) },
        },
        { provide: MatSnackBar, useValue: { open: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ApplicationDetailsPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads and displays application details with separated interview groups', () => {
    expect(applicationService.get).toHaveBeenCalledWith(12);
    expect(component.loadState()).toBe('ready');
    expect(component.upcomingInterviews()).toEqual([upcomingInterview]);
    expect(component.completedInterviews()).toEqual([completedInterview]);
    expect(fixture.nativeElement.textContent).toContain('Angular Developer');
    expect(fixture.nativeElement.textContent).toContain('Upcoming');
    expect(fixture.nativeElement.textContent).toContain('Completed');
  });

  it('schedules an interview and keeps the collection chronological', () => {
    const created = {
      ...upcomingInterview,
      id: 9,
      type: 'final' as const,
      scheduled_at: '2099-09-19T08:30:00Z',
    };
    interviewService.create.mockReturnValueOnce(of(created));
    component.openCreateInterview();

    component.saveInterview(payload());

    expect(interviewService.create).toHaveBeenCalledWith(12, payload());
    expect(component.application()?.interviews.map(({ id }) => id)).toEqual([7, 9, 8]);
    expect(component.dialogVisible()).toBe(false);
  });

  it('updates the selected interview', () => {
    const updated = { ...upcomingInterview, outcome: 'passed' as const };
    interviewService.update.mockReturnValueOnce(of(updated));
    component.openEditInterview(upcomingInterview);

    component.saveInterview(payload());

    expect(interviewService.update).toHaveBeenCalledWith(12, upcomingInterview.id, payload());
    expect(component.application()?.interviews.find(({ id }) => id === 8)?.outcome).toBe('passed');
  });

  it('shows backend validation errors in the open interview dialog', () => {
    interviewService.create.mockImplementationOnce(() => {
      apiError.set({
        message: 'Validation failed',
        occurredAt: Date.now(),
        fieldErrors: { scheduled_at: ['The scheduled at field must be a valid date.'] },
      });
      return throwError(() => new Error('Validation failed'));
    });
    component.openCreateInterview();

    component.saveInterview(payload());

    expect(component.dialogVisible()).toBe(true);
    expect(component.validationErrors()).toEqual({
      scheduled_at: ['The scheduled at field must be a valid date.'],
    });
  });

  it('deletes an interview only after confirmation', () => {
    component.requestDeleteInterview(upcomingInterview);

    component.confirmDeleteInterview();

    expect(interviewService.remove).toHaveBeenCalledWith(12, upcomingInterview.id);
    expect(component.application()?.interviews).toEqual([completedInterview]);
    expect(component.confirmDeleteVisible()).toBe(false);
  });

  it.each([
    [404, 'not-found'],
    [403, 'forbidden'],
    [500, 'error'],
  ] as const)('shows the correct inline state for HTTP %s', (status, expectedState) => {
    applicationService.get.mockReturnValueOnce(throwError(() => new HttpErrorResponse({ status })));

    component.loadApplication();
    fixture.detectChanges();

    expect(component.loadState()).toBe(expectedState);
    expect(fixture.nativeElement.querySelector('[role="alert"]')).not.toBeNull();
  });
});

function payload(): InterviewPayload {
  return {
    type: 'technical',
    scheduled_at: '2099-09-20T08:30:00Z',
    contact_name: 'Alex Recruiter',
    contact_email: 'alex@example.com',
    location_or_link: null,
    notes: null,
    outcome: null,
  };
}
