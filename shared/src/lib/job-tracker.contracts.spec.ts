import {
  APPLICATION_ACTIVITY_TYPES,
  INTERVIEW_OUTCOMES,
  INTERVIEW_TYPES,
  JOB_APPLICATION_SORT_FIELDS,
  JOB_APPLICATION_STATUSES,
  SORT_DIRECTIONS,
  WORK_MODES,
  type Company,
  type Interview,
  type JobApplication,
  type JobApplicationPayload,
  type JobApplicationFilters,
  type JobTrackerDashboard,
} from './job-tracker.contracts';

describe('job tracker contracts', () => {
  it('defines the API enum values', () => {
    expect(JOB_APPLICATION_STATUSES).toEqual([
      'saved',
      'applied',
      'interview',
      'offer',
      'rejected',
      'withdrawn',
    ]);
    expect(WORK_MODES).toEqual(['onsite', 'hybrid', 'remote']);
    expect(INTERVIEW_TYPES).toEqual(['screening', 'technical', 'hr', 'final']);
    expect(INTERVIEW_OUTCOMES).toEqual(['passed', 'failed', 'cancelled']);
    expect(APPLICATION_ACTIVITY_TYPES).toEqual([
      'tracking_started',
      'application_created',
      'status_changed',
      'application_updated',
      'interview_scheduled',
      'interview_rescheduled',
      'interview_outcome_recorded',
      'interview_updated',
      'interview_deleted',
      'comment_added',
    ]);
  });

  it('defines the supported application sorting contract', () => {
    expect(JOB_APPLICATION_SORT_FIELDS).toEqual([
      'position',
      'status',
      'board_order',
      'work_mode',
      'applied_at',
      'next_action_at',
      'created_at',
    ]);
    expect(SORT_DIRECTIONS).toEqual(['asc', 'desc']);
  });

  it('keeps domain data and filters aligned with Laravel JSON keys', () => {
    const company: Company = {
      id: 1,
      name: 'Acme',
      website: null,
      location: 'Remote',
      notes: null,
      created_at: '2026-09-12T08:00:00Z',
      updated_at: '2026-09-12T08:00:00Z',
    };
    const application: JobApplication = {
      id: 10,
      company_id: company.id,
      company: { id: company.id, name: company.name },
      position: 'Frontend Developer',
      status: 'applied',
      board_order: 1,
      work_mode: 'remote',
      employment_type: 'full-time',
      source_url: null,
      applied_at: '2026-09-12',
      next_action_at: null,
      salary_min: null,
      salary_max: null,
      currency: null,
      notes: null,
      created_at: '2026-09-12T08:00:00Z',
      updated_at: '2026-09-12T08:00:00Z',
    };
    const interview: Interview = {
      id: 20,
      job_application_id: application.id,
      type: 'technical',
      scheduled_at: '2026-09-15T10:00:00Z',
      contact_name: null,
      contact_email: null,
      location_or_link: null,
      notes: null,
      outcome: null,
      created_at: '2026-09-12T08:00:00Z',
      updated_at: '2026-09-12T08:00:00Z',
    };
    const filters: JobApplicationFilters = {
      page: 1,
      per_page: 15,
      status: application.status,
      work_mode: application.work_mode,
      sort: 'applied_at',
      direction: 'desc',
    };

    expect(application.company.name).toBe(company.name);
    expect(interview.job_application_id).toBe(application.id);
    expect(filters).toEqual({
      page: 1,
      per_page: 15,
      status: 'applied',
      work_mode: 'remote',
      sort: 'applied_at',
      direction: 'desc',
    });
  });

  it('defines write payloads and the dashboard response', () => {
    const payload: JobApplicationPayload = {
      company_id: 1,
      position: 'Laravel Developer',
      status: 'applied',
      work_mode: 'hybrid',
    };
    const dashboard: JobTrackerDashboard = {
      total_applications: 0,
      applications_by_status: {
        saved: 0,
        applied: 0,
        interview: 0,
        offer: 0,
        rejected: 0,
        withdrawn: 0,
      },
      recent_applications: [],
      upcoming_interviews: [],
    };

    expect(payload.status).toBe('applied');
    expect(dashboard.applications_by_status.saved).toBe(0);
  });
});
