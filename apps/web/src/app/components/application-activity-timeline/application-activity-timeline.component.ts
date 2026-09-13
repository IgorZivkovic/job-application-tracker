import { CommonModule } from '@angular/common';
import { Component, DestroyRef, OnInit, computed, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { finalize } from 'rxjs';
import { ApplicationActivity } from '../../models/job-tracker.model';
import { ApiErrorService } from '../../services/api-error.service';
import { ApplicationActivityService } from '../../services/application-activity.service';

type ActivityTone = 'neutral' | 'blue' | 'purple' | 'green' | 'red' | 'amber' | 'brown';

@Component({
  selector: 'app-application-activity-timeline',
  standalone: true,
  imports: [CommonModule, MatButtonModule, MatIconModule, MatProgressBarModule],
  templateUrl: './application-activity-timeline.component.html',
  styleUrl: './application-activity-timeline.component.scss',
})
export class ApplicationActivityTimelineComponent implements OnInit {
  private readonly activityService = inject(ApplicationActivityService);
  private readonly apiErrors = inject(ApiErrorService);
  private readonly destroyRef = inject(DestroyRef);

  readonly applicationId = input.required<number>();
  readonly activities = signal<ApplicationActivity[]>([]);
  readonly page = signal(0);
  readonly lastPage = signal(1);
  readonly loading = signal(false);
  readonly loadingMore = signal(false);
  readonly loadError = signal<string | null>(null);
  readonly failedPage = signal<number | null>(null);
  readonly commentDraft = signal('');
  readonly commentSaving = signal(false);
  readonly commentError = signal<string | null>(null);
  readonly announcement = signal('');

  readonly hasOlderActivity = computed(() => this.page() < this.lastPage());

  ngOnInit(): void {
    this.loadActivities();
  }

  refresh(): void {
    this.loadActivities(true, true);
  }

  loadActivities(reset = true, preserveCurrent = false): void {
    if (this.loading() || this.loadingMore()) {
      return;
    }

    const page = reset ? 1 : this.page() + 1;
    this.loadError.set(null);

    if (reset) {
      this.loading.set(true);
      if (!preserveCurrent) {
        this.activities.set([]);
      }
    } else {
      this.loadingMore.set(true);
    }

    this.activityService
      .list(this.applicationId(), page)
      .pipe(
        finalize(() => {
          this.loading.set(false);
          this.loadingMore.set(false);
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          this.activities.update((current) => {
            if (reset) {
              return response.data;
            }

            const loadedIds = new Set(current.map(({ id }) => id));
            return [...current, ...response.data.filter(({ id }) => !loadedIds.has(id))];
          });
          this.page.set(response.meta.current_page);
          this.lastPage.set(response.meta.last_page);
          this.failedPage.set(null);
        },
        error: () => {
          this.failedPage.set(page);
          this.loadError.set(
            this.apiErrors.lastError()?.message ?? 'Activity could not be loaded.',
          );
        },
      });
  }

  retryLoad(): void {
    const failedPage = this.failedPage();
    this.loadActivities(failedPage === 1, failedPage === 1 && this.activities().length > 0);
  }

  updateCommentDraft(value: string): void {
    this.commentDraft.set(value);
    this.commentError.set(null);
  }

  addComment(): void {
    const comment = this.commentDraft().trim();
    if (comment === '' || this.commentSaving()) {
      return;
    }

    this.apiErrors.clear();
    this.commentError.set(null);
    this.announcement.set('');
    this.commentSaving.set(true);
    this.activityService
      .addComment(this.applicationId(), comment)
      .pipe(
        finalize(() => this.commentSaving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (activity) => {
          this.activities.update((current) => [
            activity,
            ...current.filter(({ id }) => id !== activity.id),
          ]);
          this.commentDraft.set('');
          this.announcement.set('Timeline note added.');
        },
        error: () => {
          this.commentError.set(
            this.apiErrors.lastError()?.fieldErrors?.['comment']?.[0] ??
              this.apiErrors.lastError()?.message ??
              'Comment could not be added.',
          );
        },
      });
  }

  activityTitle(activity: ApplicationActivity): string {
    switch (activity.type) {
      case 'tracking_started':
        return 'Activity tracking started';
      case 'application_created':
        return 'Application created';
      case 'status_changed':
        return 'Status changed';
      case 'application_updated':
        return 'Application details updated';
      case 'interview_scheduled':
        return 'Interview scheduled';
      case 'interview_rescheduled':
        return 'Interview rescheduled';
      case 'interview_outcome_recorded':
        return 'Interview outcome recorded';
      case 'interview_updated':
        return 'Interview details updated';
      case 'interview_deleted':
        return 'Interview deleted';
      case 'comment_added':
        return 'Timeline note added';
    }
  }

  activityDescription(activity: ApplicationActivity): string {
    switch (activity.type) {
      case 'tracking_started':
        return 'Activity tracking began for this application.';
      case 'application_created':
        return `${activity.metadata.application.position} at ${activity.metadata.application.company.name} was added as ${this.label(activity.metadata.application.status)}.`;
      case 'status_changed':
        return `${this.label(activity.metadata.from_status)} → ${this.label(activity.metadata.to_status)}`;
      case 'application_updated':
        return `${this.changedFieldLabels(activity.metadata.changes).join(' and ')} changed.`;
      case 'interview_scheduled':
        return `${this.label(activity.metadata.interview.type)} interview set for ${this.formatMoment(activity.metadata.interview.scheduled_at)}.`;
      case 'interview_rescheduled':
        return `${this.label(activity.metadata.interview_type)} interview moved from ${this.formatMoment(activity.metadata.from_scheduled_at)} to ${this.formatMoment(activity.metadata.to_scheduled_at)}.`;
      case 'interview_outcome_recorded':
        return `${this.label(activity.metadata.interview_type)} interview outcome changed from ${this.outcomeLabel(activity.metadata.from_outcome)} to ${this.outcomeLabel(activity.metadata.to_outcome)}.`;
      case 'interview_updated':
        return `${this.changedFieldLabels(activity.metadata.changes).join(' and ')} changed.`;
      case 'interview_deleted':
        return `${this.label(activity.metadata.interview.type)} interview scheduled for ${this.formatMoment(activity.metadata.interview.scheduled_at)} was removed.`;
      case 'comment_added':
        return activity.comment;
    }
  }

  activityIcon(type: ApplicationActivity['type']): string {
    switch (type) {
      case 'tracking_started':
        return 'history';
      case 'application_created':
        return 'note_add';
      case 'status_changed':
        return 'swap_horiz';
      case 'application_updated':
        return 'edit_note';
      case 'interview_scheduled':
        return 'event_available';
      case 'interview_rescheduled':
        return 'event_repeat';
      case 'interview_outcome_recorded':
        return 'task_alt';
      case 'interview_updated':
        return 'edit_calendar';
      case 'interview_deleted':
        return 'event_busy';
      case 'comment_added':
        return 'chat_bubble';
    }
  }

  activityTone(activity: ApplicationActivity): ActivityTone {
    switch (activity.type) {
      case 'tracking_started':
        return 'neutral';
      case 'application_created':
        return this.statusTone(activity.metadata.application.status);
      case 'status_changed':
        return this.statusTone(activity.metadata.to_status);
      case 'application_updated':
      case 'interview_rescheduled':
      case 'interview_updated':
        return 'amber';
      case 'interview_scheduled':
        return 'blue';
      case 'interview_outcome_recorded':
        if (activity.metadata.to_outcome === 'passed') return 'green';
        if (activity.metadata.to_outcome === 'failed') return 'red';
        if (activity.metadata.to_outcome === 'cancelled') return 'neutral';
        return 'amber';
      case 'interview_deleted':
        return 'red';
      case 'comment_added':
        return 'purple';
    }
  }

  relativeTime(value: string): string {
    const difference = new Date(value).getTime() - Date.now();
    const absolute = Math.abs(difference);
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    if (absolute < 60_000) {
      return formatter.format(Math.round(difference / 1000), 'second');
    }

    if (absolute < 3_600_000) {
      return formatter.format(Math.round(difference / 60_000), 'minute');
    }

    if (absolute < 86_400_000) {
      return formatter.format(Math.round(difference / 3_600_000), 'hour');
    }

    return formatter.format(Math.round(difference / 86_400_000), 'day');
  }

  private changedFieldLabels(changes: Record<string, unknown>): string[] {
    const labels: Record<string, string> = {
      company: 'Company',
      position: 'Position',
      type: 'Interview type',
    };

    return Object.keys(changes).map((field) => labels[field] ?? this.label(field));
  }

  private outcomeLabel(value: string | null): string {
    return value === null ? 'Pending' : this.label(value);
  }

  private statusTone(status: string): ActivityTone {
    const tones: Record<string, ActivityTone> = {
      saved: 'neutral',
      applied: 'blue',
      interview: 'purple',
      offer: 'green',
      rejected: 'red',
      withdrawn: 'brown',
    };

    return tones[status] ?? 'neutral';
  }

  private label(value: string): string {
    if (value === 'hr') {
      return 'HR';
    }

    return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
  }

  private formatMoment(value: string): string {
    return new Intl.DateTimeFormat('en', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value));
  }
}
