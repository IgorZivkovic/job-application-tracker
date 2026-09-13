import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { JobApplication, JobApplicationPayload } from '../models/job-tracker.model';
import { JobApplicationService } from './job-application.service';

describe('JobApplicationService', () => {
  let service: JobApplicationService;
  let http: HttpTestingController;

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
    next_action_at: null,
    salary_min: 60_000,
    salary_max: 75_000,
    currency: 'EUR',
    notes: null,
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(JobApplicationService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('maps every supported list filter to the Laravel query contract', () => {
    service
      .list({
        page: 3,
        per_page: 10,
        search: '  angular  ',
        status: 'applied',
        work_mode: 'remote',
        company_id: 4,
        sort: 'applied_at',
        direction: 'desc',
      })
      .subscribe();

    const request = http.expectOne((candidate) => candidate.url === '/api/v1/job-applications');
    expect(request.request.method).toBe('GET');
    expect(request.request.params.keys().sort()).toEqual(
      [
        'page',
        'per_page',
        'search',
        'status',
        'work_mode',
        'company_id',
        'sort',
        'direction',
      ].sort(),
    );
    expect(request.request.params.get('page')).toBe('3');
    expect(request.request.params.get('per_page')).toBe('10');
    expect(request.request.params.get('search')).toBe('angular');
    expect(request.request.params.get('status')).toBe('applied');
    expect(request.request.params.get('work_mode')).toBe('remote');
    expect(request.request.params.get('company_id')).toBe('4');
    expect(request.request.params.get('sort')).toBe('applied_at');
    expect(request.request.params.get('direction')).toBe('desc');

    request.flush(paginated([application]));
  });

  it('uses the expected URLs and payloads for application CRUD', () => {
    const payload: JobApplicationPayload = {
      company_id: 4,
      position: 'Angular Developer',
      status: 'applied',
      work_mode: 'remote',
      salary_min: 60_000,
      salary_max: 75_000,
      currency: 'EUR',
    };

    service.get(application.id).subscribe();
    http.expectOne(`/api/v1/job-applications/${application.id}`).flush({
      data: { ...application, interviews: [] },
    });

    service.create(payload).subscribe();
    const createRequest = http.expectOne('/api/v1/job-applications');
    expect(createRequest.request.method).toBe('POST');
    expect(createRequest.request.body).toEqual(payload);
    createRequest.flush({ data: application });

    service.update(application.id, { status: 'interview' }).subscribe();
    const updateRequest = http.expectOne(`/api/v1/job-applications/${application.id}`);
    expect(updateRequest.request.method).toBe('PATCH');
    expect(updateRequest.request.body).toEqual({ status: 'interview' });
    updateRequest.flush({ data: { ...application, status: 'interview' } });

    service.move(application.id, { status: 'offer', target_index: 2 }).subscribe();
    const moveRequest = http.expectOne(`/api/v1/job-applications/${application.id}/move`);
    expect(moveRequest.request.method).toBe('PATCH');
    expect(moveRequest.request.body).toEqual({ status: 'offer', target_index: 2 });
    moveRequest.flush({ data: { ...application, status: 'offer', board_order: 3 } });

    service.remove(application.id).subscribe();
    const deleteRequest = http.expectOne(`/api/v1/job-applications/${application.id}`);
    expect(deleteRequest.request.method).toBe('DELETE');
    deleteRequest.flush({ deleted: true });
  });
});

function paginated(data: JobApplication[]) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 3,
      from: 21,
      last_page: 3,
      links: [],
      path: '/api/v1/job-applications',
      per_page: 10,
      to: 21,
      total: 21,
    },
  };
}
