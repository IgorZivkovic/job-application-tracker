import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { ApiOperationError, PaginatedResponse } from '../../models/api.model';
import {
  DashboardInterview,
  JobApplication,
  JobTrackerDashboard,
} from '../../models/job-tracker.model';
import { ApiErrorService } from '../../services/api-error.service';
import { DashboardService } from '../../services/dashboard.service';
import { JobApplicationService } from '../../services/job-application.service';
import { DashboardPageComponent } from './dashboard-page.component';

describe('DashboardPageComponent', () => {
  let fixture: ComponentFixture<DashboardPageComponent>;
  let component: DashboardPageComponent;
  let dashboardService: { get: ReturnType<typeof vi.fn> };
  let applicationService: {
    list: ReturnType<typeof vi.fn>;
    move: ReturnType<typeof vi.fn>;
  };

  const jobApplication = application();
  const dashboard: JobTrackerDashboard = {
    total_applications: 4,
    applications_by_status: {
      saved: 1,
      applied: 1,
      interview: 1,
      offer: 0,
      rejected: 1,
      withdrawn: 0,
    },
    recent_applications: [jobApplication],
    upcoming_interviews: [interview()],
  };

  beforeEach(async () => {
    dashboardService = { get: vi.fn(() => of(dashboard)) };
    applicationService = {
      list: vi.fn(() => of(page([jobApplication], 4))),
      move: vi.fn(() => of(jobApplication)),
    };
    const lastError = signal<ApiOperationError | null>(null);

    await TestBed.configureTestingModule({
      imports: [DashboardPageComponent],
      providers: [
        provideRouter([]),
        { provide: DashboardService, useValue: dashboardService },
        { provide: JobApplicationService, useValue: applicationService },
        {
          provide: ApiErrorService,
          useValue: { lastError: lastError.asReadonly(), clear: vi.fn() },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardPageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads the summary and board through existing services', () => {
    expect(dashboardService.get).toHaveBeenCalledOnce();
    expect(applicationService.list).toHaveBeenCalledWith({
      page: 1,
      per_page: 100,
      sort: 'board_order',
      direction: 'asc',
    });
    expect(component.dashboard()).toEqual(dashboard);
    expect(component.boardApplications()).toEqual([jobApplication]);
    expect(component.statusSummary().find(({ status }) => status === 'applied')?.count).toBe(1);
    expect(fixture.nativeElement.textContent).toContain('Recent applications');
    expect(fixture.nativeElement.textContent).toContain('Upcoming interviews');
    expect(fixture.nativeElement.textContent).toContain('Application board');
  });

  it('shows an inline retry state when either dashboard request fails', () => {
    dashboardService.get.mockReturnValueOnce(throwError(() => new Error('Offline')));

    component.loadDashboard();
    fixture.detectChanges();

    expect(component.loadFailed()).toBe(true);
    expect(fixture.nativeElement.querySelector('[role="alert"]')).not.toBeNull();
    expect(fixture.nativeElement.textContent).toContain('Dashboard could not be loaded');
  });

  it('optimistically moves a card and keeps dashboard counts in sync', () => {
    const moved = { ...jobApplication, status: 'offer' as const, board_order: 1 };
    applicationService.move.mockReturnValueOnce(of(moved));

    component.moveApplication({
      application: jobApplication,
      previousStatus: 'applied',
      status: 'offer',
      previousIndex: 0,
      targetIndex: 0,
    });

    expect(applicationService.move).toHaveBeenCalledWith(jobApplication.id, {
      status: 'offer',
      target_index: 0,
    });
    expect(component.boardApplications()[0].status).toBe('offer');
    expect(component.dashboard()?.applications_by_status.applied).toBe(0);
    expect(component.dashboard()?.applications_by_status.offer).toBe(1);
    expect(component.moveAnnouncement()).toContain('moved to Offer');
  });

  it('restores the previous board and counts when a move fails', () => {
    applicationService.move.mockReturnValueOnce(throwError(() => new Error('Offline')));

    component.moveApplication({
      application: jobApplication,
      previousStatus: 'applied',
      status: 'offer',
      previousIndex: 0,
      targetIndex: 0,
    });

    expect(component.boardApplications()).toEqual([jobApplication]);
    expect(component.dashboard()).toEqual(dashboard);
    expect(component.moveAnnouncement()).toContain('previous order was restored');
  });

  it('reorders cards within the same status without changing summary counts', () => {
    const second = { ...jobApplication, id: 13, position: 'Second', board_order: 2 };
    const third = { ...jobApplication, id: 14, position: 'Third', board_order: 3 };
    component.boardApplications.set([jobApplication, second, third]);
    applicationService.move.mockReturnValueOnce(of({ ...third, board_order: 1 }));

    component.moveApplication({
      application: third,
      previousStatus: 'applied',
      status: 'applied',
      previousIndex: 2,
      targetIndex: 0,
    });

    expect(component.boardApplications().map(({ id }) => id)).toEqual([14, 12, 13]);
    expect(component.boardApplications().map(({ board_order }) => board_order)).toEqual([1, 2, 3]);
    expect(component.dashboard()?.applications_by_status.applied).toBe(1);
  });
});

function application(): JobApplication {
  return {
    id: 12,
    company_id: 4,
    company: { id: 4, name: 'Northstar Labs' },
    position: 'Angular Developer',
    status: 'applied',
    board_order: 1,
    work_mode: 'remote',
    employment_type: 'full-time',
    source_url: null,
    applied_at: '2026-09-10',
    next_action_at: '2099-09-20T08:30:00Z',
    salary_min: null,
    salary_max: null,
    currency: null,
    notes: null,
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };
}

function interview(): DashboardInterview {
  return {
    id: 8,
    job_application_id: 12,
    job_application: {
      id: 12,
      position: 'Angular Developer',
      company: { id: 4, name: 'Northstar Labs' },
    },
    type: 'technical',
    scheduled_at: '2099-09-20T08:30:00Z',
    contact_name: null,
    contact_email: null,
    location_or_link: null,
    notes: null,
    outcome: null,
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };
}

function page(data: JobApplication[], total: number): PaginatedResponse<JobApplication> {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: data.length ? 1 : null,
      last_page: 1,
      links: [],
      path: '/api/v1/job-applications',
      per_page: 100,
      to: data.length || null,
      total,
    },
  };
}
