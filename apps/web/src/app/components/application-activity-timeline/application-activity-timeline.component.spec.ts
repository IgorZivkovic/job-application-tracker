import { signal, WritableSignal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { ApiOperationError } from '../../models/api.model';
import { APPLICATION_ACTIVITY_TYPES, ApplicationActivity } from '../../models/job-tracker.model';
import { ApiErrorService } from '../../services/api-error.service';
import { ApplicationActivityService } from '../../services/application-activity.service';
import { ApplicationActivityTimelineComponent } from './application-activity-timeline.component';

describe('ApplicationActivityTimelineComponent', () => {
  let fixture: ComponentFixture<ApplicationActivityTimelineComponent>;
  let component: ApplicationActivityTimelineComponent;
  let activityService: {
    list: ReturnType<typeof vi.fn>;
    addComment: ReturnType<typeof vi.fn>;
  };
  let apiError: WritableSignal<ApiOperationError | null>;

  const statusActivity = activitiesByType().find((activity) => activity.type === 'status_changed')!;

  beforeEach(async () => {
    activityService = {
      list: vi.fn(() => of(activityPage([statusActivity], 1, 2))),
      addComment: vi.fn(() => of(commentActivity())),
    };
    apiError = signal<ApiOperationError | null>(null);

    await TestBed.configureTestingModule({
      imports: [ApplicationActivityTimelineComponent],
      providers: [
        { provide: ApplicationActivityService, useValue: activityService },
        {
          provide: ApiErrorService,
          useValue: { lastError: apiError.asReadonly(), clear: vi.fn(() => apiError.set(null)) },
        },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ApplicationActivityTimelineComponent);
    fixture.componentRef.setInput('applicationId', 12);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads and renders the first timeline page', () => {
    expect(activityService.list).toHaveBeenCalledWith(12, 1);
    expect(component.activities()).toEqual([statusActivity]);
    expect(fixture.nativeElement.textContent).toContain('Status changed');
    expect(fixture.nativeElement.textContent).toContain('Applied → Interview');
  });

  it('loads older activity without replacing the first page', () => {
    const older = activitiesByType()[0];
    activityService.list.mockReturnValueOnce(of(activityPage([statusActivity, older], 2, 2)));

    component.loadActivities(false);

    expect(activityService.list).toHaveBeenLastCalledWith(12, 2);
    expect(component.activities()).toEqual([statusActivity, older]);
    expect(component.hasOlderActivity()).toBe(false);
  });

  it('adds a server-confirmed comment to the top of the timeline', () => {
    component.updateCommentDraft('  Followed up.  ');

    component.addComment();

    expect(activityService.addComment).toHaveBeenCalledWith(12, 'Followed up.');
    expect(component.activities()[0]).toEqual(commentActivity());
    expect(component.commentDraft()).toBe('');
    expect(component.announcement()).toContain('Timeline note added');
  });

  it('keeps the draft and exposes validation errors when submission fails', () => {
    activityService.addComment.mockImplementationOnce(() => {
      apiError.set({
        message: 'Validation failed',
        occurredAt: Date.now(),
        fieldErrors: { comment: ['The comment must not be greater than 2000 characters.'] },
      });
      return throwError(() => new Error('Validation failed'));
    });
    component.updateCommentDraft('Too long');

    component.addComment();

    expect(component.commentDraft()).toBe('Too long');
    expect(component.commentError()).toContain('2000 characters');
  });

  it('shows a retry state when the timeline cannot be loaded', () => {
    activityService.list.mockImplementationOnce(() => {
      apiError.set({ message: 'Connection failed.', occurredAt: Date.now() });
      return throwError(() => new Error('Connection failed'));
    });
    component.activities.set([]);

    component.loadActivities();
    fixture.detectChanges();

    expect(component.loadError()).toBe('Connection failed.');
    expect(fixture.nativeElement.querySelector('[role="alert"]')).not.toBeNull();
  });

  it('maps every supported activity type to a readable presentation', () => {
    const activities = activitiesByType();

    expect(activities.map(({ type }) => type)).toEqual(APPLICATION_ACTIVITY_TYPES);
    for (const activity of activities) {
      expect(component.activityTitle(activity)).not.toBe('');
      expect(component.activityDescription(activity)).not.toBe('');
      expect(component.activityIcon(activity.type)).not.toBe('');
      expect(component.activityTone(activity)).not.toBe('');
    }
  });

  it('uses semantic tones for statuses and interview outcomes', () => {
    const activities = activitiesByType();
    const statusChange = activities.find(({ type }) => type === 'status_changed');
    const passedInterview = activities.find(({ type }) => type === 'interview_outcome_recorded');
    const deletedInterview = activities.find(({ type }) => type === 'interview_deleted');

    expect(component.activityTone(statusChange!)).toBe('purple');
    expect(component.activityTone(passedInterview!)).toBe('green');
    expect(component.activityTone(deletedInterview!)).toBe('red');
    expect(component.activityTone(commentActivity())).toBe('purple');
  });

  it('formats the HR interview acronym in timeline descriptions', () => {
    const activity = activitiesByType().find(({ type }) => type === 'interview_outcome_recorded');
    expect(activity).toBeDefined();

    const hrActivity = {
      ...activity!,
      metadata: { ...activity!.metadata, interview_type: 'hr' },
    } as ApplicationActivity;

    expect(component.activityDescription(hrActivity)).toContain('HR interview');
  });
});

function activitiesByType(): ApplicationActivity[] {
  const base = {
    job_application_id: 12,
    comment: null,
    actor: { id: 1, email: 'admin@example.com' },
    occurred_at: '2026-09-13T10:00:00Z',
  } as const;

  return [
    { ...base, id: 1, type: 'tracking_started', metadata: null },
    {
      ...base,
      id: 2,
      type: 'application_created',
      metadata: {
        application: {
          company: { id: 4, name: 'Northstar Labs' },
          position: 'Angular Developer',
          status: 'saved',
        },
      },
    },
    {
      ...base,
      id: 3,
      type: 'status_changed',
      metadata: { from_status: 'applied', to_status: 'interview' },
    },
    {
      ...base,
      id: 4,
      type: 'application_updated',
      metadata: { changes: { position: { from: 'Developer', to: 'Senior Developer' } } },
    },
    {
      ...base,
      id: 5,
      type: 'interview_scheduled',
      metadata: {
        interview: {
          id: 8,
          type: 'technical',
          scheduled_at: '2026-09-20T08:30:00Z',
          outcome: null,
        },
      },
    },
    {
      ...base,
      id: 6,
      type: 'interview_rescheduled',
      metadata: {
        interview_id: 8,
        interview_type: 'technical',
        from_scheduled_at: '2026-09-20T08:30:00Z',
        to_scheduled_at: '2026-09-22T08:30:00Z',
      },
    },
    {
      ...base,
      id: 7,
      type: 'interview_outcome_recorded',
      metadata: {
        interview_id: 8,
        interview_type: 'technical',
        from_outcome: null,
        to_outcome: 'passed',
      },
    },
    {
      ...base,
      id: 8,
      type: 'interview_updated',
      metadata: { interview_id: 8, changes: { type: { from: 'technical', to: 'final' } } },
    },
    {
      ...base,
      id: 9,
      type: 'interview_deleted',
      metadata: {
        interview: {
          id: 8,
          type: 'technical',
          scheduled_at: '2026-09-22T08:30:00Z',
          outcome: 'passed',
        },
      },
    },
    commentActivity(),
  ];
}

function commentActivity(): ApplicationActivity {
  return {
    id: 10,
    job_application_id: 12,
    type: 'comment_added',
    comment: 'Followed up.',
    metadata: null,
    actor: { id: 1, email: 'admin@example.com' },
    occurred_at: '2026-09-13T11:00:00Z',
  };
}

function activityPage(data: ApplicationActivity[], currentPage: number, lastPage: number) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: currentPage,
      from: data.length > 0 ? 1 : null,
      last_page: lastPage,
      links: [],
      path: '/api/v1/job-applications/12/activities',
      per_page: 10,
      to: data.length,
      total: data.length,
    },
  };
}
