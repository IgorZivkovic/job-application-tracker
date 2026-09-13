import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ApplicationActivity } from '../models/job-tracker.model';
import { ApplicationActivityService } from './application-activity.service';

describe('ApplicationActivityService', () => {
  let service: ApplicationActivityService;
  let http: HttpTestingController;

  const comment: ApplicationActivity = {
    id: 9,
    job_application_id: 12,
    type: 'comment_added',
    comment: 'Followed up with the recruiter.',
    metadata: null,
    actor: { id: 1, email: 'admin@example.com' },
    occurred_at: '2026-09-13T10:00:00Z',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });

    service = TestBed.inject(ApplicationActivityService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('loads a paginated timeline from the nested application URL', () => {
    service.list(12, 2, 5).subscribe((response) => expect(response.data).toEqual([comment]));

    const request = http.expectOne(
      (candidate) =>
        candidate.url === '/api/v1/job-applications/12/activities' &&
        candidate.params.get('page') === '2' &&
        candidate.params.get('per_page') === '5',
    );
    expect(request.request.method).toBe('GET');
    request.flush(paginated([comment]));
  });

  it('posts only the manual comment and unwraps the activity resource', () => {
    service
      .addComment(12, comment.comment)
      .subscribe((activity) => expect(activity).toEqual(comment));

    const request = http.expectOne('/api/v1/job-applications/12/activities');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ comment: comment.comment });
    request.flush({ data: comment });
  });
});

function paginated(data: ApplicationActivity[]) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: 1,
      from: data.length > 0 ? 1 : null,
      last_page: 1,
      links: [],
      path: '/api/v1/job-applications/12/activities',
      per_page: 5,
      to: data.length,
      total: data.length,
    },
  };
}
