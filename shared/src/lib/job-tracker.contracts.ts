export const JOB_APPLICATION_STATUSES = [
  'saved',
  'applied',
  'interview',
  'offer',
  'rejected',
  'withdrawn',
] as const;

export const WORK_MODES = ['onsite', 'hybrid', 'remote'] as const;

export const INTERVIEW_TYPES = ['screening', 'technical', 'hr', 'final'] as const;

export const INTERVIEW_OUTCOMES = ['passed', 'failed', 'cancelled'] as const;

export const APPLICATION_ACTIVITY_TYPES = [
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
] as const;

export const JOB_APPLICATION_SORT_FIELDS = [
  'position',
  'status',
  'board_order',
  'work_mode',
  'applied_at',
  'next_action_at',
  'created_at',
] as const;

export const SORT_DIRECTIONS = ['asc', 'desc'] as const;

export type JobApplicationStatus = (typeof JOB_APPLICATION_STATUSES)[number];
export type WorkMode = (typeof WORK_MODES)[number];
export type InterviewType = (typeof INTERVIEW_TYPES)[number];
export type InterviewOutcome = (typeof INTERVIEW_OUTCOMES)[number];
export type ApplicationActivityType = (typeof APPLICATION_ACTIVITY_TYPES)[number];
export type JobApplicationSortField = (typeof JOB_APPLICATION_SORT_FIELDS)[number];
export type SortDirection = (typeof SORT_DIRECTIONS)[number];

export interface Company {
  id: number;
  name: string;
  website: string | null;
  location: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export type CompanySummary = Pick<Company, 'id' | 'name'>;

export interface JobApplication {
  id: number;
  company_id: number;
  company: CompanySummary;
  position: string;
  status: JobApplicationStatus;
  board_order: number;
  work_mode: WorkMode;
  employment_type: string | null;
  source_url: string | null;
  applied_at: string | null;
  next_action_at: string | null;
  salary_min: number | null;
  salary_max: number | null;
  currency: string | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface Interview {
  id: number;
  job_application_id: number;
  type: InterviewType;
  scheduled_at: string;
  contact_name: string | null;
  contact_email: string | null;
  location_or_link: string | null;
  notes: string | null;
  outcome: InterviewOutcome | null;
  created_at: string;
  updated_at: string;
}

export interface ApplicationActivityActor {
  id: number;
  email: string;
}

export type ApplicationActivityValue = string | number | boolean | null | CompanySummary;

export interface ApplicationActivityChange {
  from: ApplicationActivityValue;
  to: ApplicationActivityValue;
}

export type ApplicationActivityChanges = Record<string, ApplicationActivityChange>;

interface ApplicationActivityBase<TType extends ApplicationActivityType, TMetadata> {
  id: number;
  job_application_id: number;
  type: TType;
  metadata: TMetadata;
  actor: ApplicationActivityActor | null;
  occurred_at: string;
}

interface InterviewActivitySummary {
  id: number;
  type: InterviewType;
  scheduled_at: string;
  outcome: InterviewOutcome | null;
}

export type ApplicationActivity =
  | (ApplicationActivityBase<'tracking_started', null> & { comment: null })
  | (ApplicationActivityBase<
      'application_created',
      {
        application: {
          company: CompanySummary;
          position: string;
          status: JobApplicationStatus;
        };
      }
    > & { comment: null })
  | (ApplicationActivityBase<
      'status_changed',
      { from_status: JobApplicationStatus; to_status: JobApplicationStatus }
    > & { comment: null })
  | (ApplicationActivityBase<'application_updated', { changes: ApplicationActivityChanges }> & {
      comment: null;
    })
  | (ApplicationActivityBase<'interview_scheduled', { interview: InterviewActivitySummary }> & {
      comment: null;
    })
  | (ApplicationActivityBase<
      'interview_rescheduled',
      {
        interview_id: number;
        interview_type: InterviewType;
        from_scheduled_at: string;
        to_scheduled_at: string;
      }
    > & { comment: null })
  | (ApplicationActivityBase<
      'interview_outcome_recorded',
      {
        interview_id: number;
        interview_type: InterviewType;
        from_outcome: InterviewOutcome | null;
        to_outcome: InterviewOutcome | null;
      }
    > & { comment: null })
  | (ApplicationActivityBase<
      'interview_updated',
      { interview_id: number; changes: ApplicationActivityChanges }
    > & { comment: null })
  | (ApplicationActivityBase<'interview_deleted', { interview: InterviewActivitySummary }> & {
      comment: null;
    })
  | (ApplicationActivityBase<'comment_added', null> & { comment: string });

export interface JobApplicationDetail extends JobApplication {
  interviews: Interview[];
}

export interface DashboardInterview extends Interview {
  job_application: Pick<JobApplication, 'id' | 'position'> & {
    company: CompanySummary;
  };
}

export type JobApplicationStatusCounts = Record<JobApplicationStatus, number>;

export interface JobTrackerDashboard {
  total_applications: number;
  applications_by_status: JobApplicationStatusCounts;
  recent_applications: JobApplication[];
  upcoming_interviews: DashboardInterview[];
}

export type CompanyPayload = Pick<Company, 'name' | 'website' | 'location' | 'notes'>;
export type UpdateCompanyPayload = Partial<CompanyPayload>;

export interface JobApplicationPayload {
  company_id: number;
  position: string;
  status: JobApplicationStatus;
  work_mode: WorkMode;
  employment_type?: string | null;
  source_url?: string | null;
  applied_at?: string | null;
  next_action_at?: string | null;
  salary_min?: number | null;
  salary_max?: number | null;
  currency?: string | null;
  notes?: string | null;
}

export type UpdateJobApplicationPayload = Partial<JobApplicationPayload>;

export interface MoveJobApplicationPayload {
  status: JobApplicationStatus;
  target_index: number;
}

export interface InterviewPayload {
  type: InterviewType;
  scheduled_at: string;
  contact_name?: string | null;
  contact_email?: string | null;
  location_or_link?: string | null;
  notes?: string | null;
  outcome?: InterviewOutcome | null;
}

export type UpdateInterviewPayload = Partial<InterviewPayload>;

export interface CompanyFilters {
  page?: number;
  per_page?: number;
  search?: string;
}

export interface JobApplicationFilters {
  page?: number;
  per_page?: number;
  search?: string;
  status?: JobApplicationStatus;
  work_mode?: WorkMode;
  company_id?: number;
  sort?: JobApplicationSortField;
  direction?: SortDirection;
}
