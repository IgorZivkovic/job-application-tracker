import { signal, WritableSignal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatSnackBar } from '@angular/material/snack-bar';
import { provideRouter, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { ApiOperationError } from '../../models/api.model';
import { JobApplication, JobApplicationPayload } from '../../models/job-tracker.model';
import { ApiErrorService } from '../../services/api-error.service';
import { CompanyService } from '../../services/company.service';
import { JobApplicationService } from '../../services/job-application.service';
import { ApplicationsPageComponent } from './applications-page.component';

describe('ApplicationsPageComponent', () => {
  let fixture: ComponentFixture<ApplicationsPageComponent>;
  let component: ApplicationsPageComponent;
  let applicationService: {
    list: ReturnType<typeof vi.fn>;
    create: ReturnType<typeof vi.fn>;
    update: ReturnType<typeof vi.fn>;
    remove: ReturnType<typeof vi.fn>;
  };
  let companyService: { list: ReturnType<typeof vi.fn> };
  let router: Router;
  let apiError: WritableSignal<ApiOperationError | null>;

  const application: JobApplication = {
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
    next_action_at: '2026-09-20',
    salary_min: 60_000,
    salary_max: 75_000,
    currency: 'EUR',
    notes: null,
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };

  beforeEach(async () => {
    applicationService = {
      list: vi.fn(() => of(paginated([application], 1))),
      create: vi.fn(() => of(application)),
      update: vi.fn(() => of(application)),
      remove: vi.fn(() => of({ deleted: true })),
    };
    companyService = {
      list: vi.fn(() => of(companyPage())),
    };
    apiError = signal<ApiOperationError | null>(null);

    await TestBed.configureTestingModule({
      imports: [ApplicationsPageComponent],
      providers: [
        provideRouter([]),
        { provide: JobApplicationService, useValue: applicationService },
        { provide: CompanyService, useValue: companyService },
        {
          provide: ApiErrorService,
          useValue: { lastError: apiError.asReadonly(), clear: vi.fn(() => apiError.set(null)) },
        },
        { provide: MatSnackBar, useValue: { open: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ApplicationsPageComponent);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
    fixture.detectChanges();
  });

  it('loads the first Laravel pagination page', () => {
    expect(applicationService.list).toHaveBeenCalledWith({
      page: 1,
      per_page: 10,
      sort: 'created_at',
      direction: 'desc',
    });
    expect(companyService.list).toHaveBeenCalledWith({ page: 1, per_page: 100 });
    expect(component.applications()).toEqual([application]);
    expect(component.total()).toBe(21);
    expect(component.currentPage()).toBe(1);
    expect(fixture.nativeElement.textContent).toContain('Angular Developer');
    expect(fixture.nativeElement.textContent).toContain('Northstar Labs');
  });

  it('keeps the selected page in the URL and loads it', async () => {
    applicationService.list.mockReturnValueOnce(of(paginated([application], 2)));

    component.handlePageChange({ pageIndex: 1, pageSize: 10, length: 21 });
    await fixture.whenStable();

    expect(router.url).toBe('/?page=2');
    expect(applicationService.list).toHaveBeenLastCalledWith({
      page: 2,
      per_page: 10,
      sort: 'created_at',
      direction: 'desc',
    });
    expect(component.currentPage()).toBe(2);
  });

  it('combines filters and sorting in the URL and API request', async () => {
    component.searchTerm = '  angular  ';
    component.selectedStatus = 'interview';
    component.selectedWorkMode = 'remote';
    component.selectedCompanyId = 4;
    component.selectedSort = 'position';
    component.selectedDirection = 'asc';

    component.applyFilters();
    await fixture.whenStable();

    expect(router.url).toBe(
      '/?search=angular&status=interview&work_mode=remote&company_id=4&sort=position&direction=asc',
    );
    expect(applicationService.list).toHaveBeenLastCalledWith({
      page: 1,
      per_page: 10,
      search: 'angular',
      status: 'interview',
      work_mode: 'remote',
      company_id: 4,
      sort: 'position',
      direction: 'asc',
    });
    expect(component.emptyMessage).toBe('No applications match your filters.');
  });

  it('restores filters, sorting and pagination from the URL', async () => {
    applicationService.list.mockReturnValueOnce(of(paginated([application], 2)));

    await router.navigateByUrl(
      '/?search=backend&status=offer&work_mode=hybrid&company_id=4&sort=applied_at&direction=asc&page=2',
    );
    await fixture.whenStable();

    expect(component.searchTerm).toBe('backend');
    expect(component.selectedStatus).toBe('offer');
    expect(component.selectedWorkMode).toBe('hybrid');
    expect(component.selectedCompanyId).toBe(4);
    expect(component.selectedSort).toBe('applied_at');
    expect(component.selectedDirection).toBe('asc');
    expect(component.currentPage()).toBe(2);
    expect(applicationService.list).toHaveBeenLastCalledWith({
      page: 2,
      per_page: 10,
      search: 'backend',
      status: 'offer',
      work_mode: 'hybrid',
      company_id: 4,
      sort: 'applied_at',
      direction: 'asc',
    });
  });

  it('debounces search before updating the URL', async () => {
    vi.useFakeTimers();
    const initialCallCount = applicationService.list.mock.calls.length;

    component.handleSearchChange('angular');
    vi.advanceTimersByTime(299);
    expect(applicationService.list).toHaveBeenCalledTimes(initialCallCount);

    vi.advanceTimersByTime(1);
    await vi.runAllTimersAsync();
    await fixture.whenStable();

    expect(router.url).toBe('/?search=angular');
    expect(applicationService.list).toHaveBeenLastCalledWith({
      page: 1,
      per_page: 10,
      search: 'angular',
      sort: 'created_at',
      direction: 'desc',
    });
    vi.useRealTimers();
  });

  it('resets filters, sorting and pagination to their defaults', async () => {
    component.searchTerm = 'angular';
    component.selectedStatus = 'offer';
    component.selectedCompanyId = 4;
    component.selectedSort = 'position';
    component.selectedDirection = 'asc';
    component.currentPage.set(3);
    component.applyFilters();
    await fixture.whenStable();

    component.resetFilters();
    await fixture.whenStable();

    expect(router.url).toBe('/');
    expect(component.hasActiveFilters).toBe(false);
    expect(component.currentPage()).toBe(1);
    expect(applicationService.list).toHaveBeenLastCalledWith({
      page: 1,
      per_page: 10,
      sort: 'created_at',
      direction: 'desc',
    });
  });

  it('creates an application and refreshes without losing filters', async () => {
    await router.navigateByUrl('/?status=applied&company_id=4');
    await fixture.whenStable();
    component.openCreate();

    component.saveApplication(payload());

    expect(applicationService.create).toHaveBeenCalledWith(payload());
    expect(component.dialogVisible()).toBe(false);
    expect(router.url).toBe('/?status=applied&company_id=4');
    expect(applicationService.list).toHaveBeenLastCalledWith({
      page: 1,
      per_page: 10,
      status: 'applied',
      company_id: 4,
      sort: 'created_at',
      direction: 'desc',
    });
  });

  it('updates the selected application', () => {
    component.openEdit(application);

    component.saveApplication(payload());

    expect(applicationService.update).toHaveBeenCalledWith(application.id, payload());
    expect(component.dialogVisible()).toBe(false);
  });

  it('shows backend validation errors beside form fields', () => {
    applicationService.create.mockImplementationOnce(() => {
      apiError.set({
        message: 'Validation failed',
        occurredAt: Date.now(),
        fieldErrors: { salary_max: ['Salary maximum must not be lower than salary minimum.'] },
      });
      return throwError(() => new Error('Validation failed'));
    });
    component.openCreate();

    component.saveApplication(payload());

    expect(component.dialogVisible()).toBe(true);
    expect(component.validationErrors()).toEqual({
      salary_max: ['Salary maximum must not be lower than salary minimum.'],
    });
  });

  it('deletes only after confirmation and refreshes the current filters', async () => {
    await router.navigateByUrl('/?work_mode=remote');
    await fixture.whenStable();
    component.requestDelete(application);

    component.confirmDelete();

    expect(applicationService.remove).toHaveBeenCalledWith(application.id);
    expect(component.confirmDeleteVisible()).toBe(false);
    expect(router.url).toBe('/?work_mode=remote');
    expect(applicationService.list).toHaveBeenLastCalledWith({
      page: 1,
      per_page: 10,
      work_mode: 'remote',
      sort: 'created_at',
      direction: 'desc',
    });
  });

  it('exposes a distinct error state when loading fails', () => {
    applicationService.list.mockReturnValueOnce(throwError(() => new Error('Network error')));

    component.loadApplications();

    expect(component.loadFailed()).toBe(true);
    expect(component.applications()).toEqual([]);
    expect(component.emptyMessage).toBe(
      'Applications could not be loaded. Try refreshing the page.',
    );
  });
});

function paginated(data: JobApplication[], currentPage: number) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: currentPage,
      from: (currentPage - 1) * 10 + 1,
      last_page: 3,
      links: [],
      path: '/api/v1/job-applications',
      per_page: 10,
      to: currentPage * 10,
      total: 21,
    },
  };
}

function payload(): JobApplicationPayload {
  return {
    company_id: 4,
    position: 'Angular Developer',
    status: 'applied',
    work_mode: 'remote',
    employment_type: 'full-time',
    source_url: null,
    applied_at: '2026-09-10',
    next_action_at: '2026-09-20',
    salary_min: 60_000,
    salary_max: 75_000,
    currency: 'EUR',
    notes: null,
  };
}

function companyPage() {
  return {
    data: [
      {
        id: 4,
        name: 'Northstar Labs',
        website: null,
        location: null,
        notes: null,
        created_at: '2026-09-12T10:00:00Z',
        updated_at: '2026-09-12T10:00:00Z',
      },
    ],
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: 1,
      last_page: 1,
      links: [],
      path: '/api/v1/companies',
      per_page: 100,
      to: 1,
      total: 1,
    },
  };
}
