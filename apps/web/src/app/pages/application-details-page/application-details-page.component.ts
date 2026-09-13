import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, computed, inject, signal, viewChild } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { ConfirmDialogComponent } from '../../components/confirm-dialog/confirm-dialog.component';
import { ApplicationActivityTimelineComponent } from '../../components/application-activity-timeline/application-activity-timeline.component';
import {
  InterviewDialogComponent,
  InterviewDialogMode,
  InterviewFieldErrors,
} from '../../components/interview-dialog/interview-dialog.component';
import { Interview, InterviewPayload, JobApplicationDetail } from '../../models/job-tracker.model';
import { ApiErrorService } from '../../services/api-error.service';
import { InterviewService } from '../../services/interview.service';
import { JobApplicationService } from '../../services/job-application.service';

type DetailLoadState = 'loading' | 'ready' | 'not-found' | 'forbidden' | 'error';

@Component({
  selector: 'app-application-details-page',
  standalone: true,
  imports: [
    CommonModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    MatTooltipModule,
    RouterLink,
    ConfirmDialogComponent,
    ApplicationActivityTimelineComponent,
    InterviewDialogComponent,
  ],
  templateUrl: './application-details-page.component.html',
  styleUrl: './application-details-page.component.scss',
})
export class ApplicationDetailsPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly applications = inject(JobApplicationService);
  private readonly interviews = inject(InterviewService);
  private readonly apiErrors = inject(ApiErrorService);
  private readonly snackBar = inject(MatSnackBar);
  private readonly destroyRef = inject(DestroyRef);

  readonly application = signal<JobApplicationDetail | null>(null);
  readonly loadState = signal<DetailLoadState>('loading');
  readonly applicationId = signal<number | null>(null);
  readonly dialogVisible = signal(false);
  readonly dialogMode = signal<InterviewDialogMode>('create');
  readonly selectedInterview = signal<Interview | null>(null);
  readonly saving = signal(false);
  readonly validationErrors = signal<InterviewFieldErrors>({});
  readonly confirmDeleteVisible = signal(false);
  readonly interviewPendingDelete = signal<Interview | null>(null);
  readonly deleting = signal(false);
  readonly activityTimeline = viewChild(ApplicationActivityTimelineComponent);

  readonly upcomingInterviews = computed(() => {
    const now = Date.now();
    return (
      this.application()
        ?.interviews.filter(
          (interview) =>
            interview.outcome === null && new Date(interview.scheduled_at).getTime() >= now,
        )
        .sort(
          (left, right) =>
            new Date(left.scheduled_at).getTime() - new Date(right.scheduled_at).getTime() ||
            left.id - right.id,
        ) ?? []
    );
  });

  readonly completedInterviews = computed(() => {
    const upcomingIds = new Set(this.upcomingInterviews().map((interview) => interview.id));
    return (
      this.application()
        ?.interviews.filter((interview) => !upcomingIds.has(interview.id))
        .sort(
          (left, right) =>
            new Date(right.scheduled_at).getTime() - new Date(left.scheduled_at).getTime() ||
            right.id - left.id,
        ) ?? []
    );
  });

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = Number(params.get('id'));
      if (!Number.isInteger(id) || id < 1) {
        this.applicationId.set(null);
        this.application.set(null);
        this.loadState.set('not-found');
        return;
      }

      this.applicationId.set(id);
      this.loadApplication();
    });
  }

  get errorTitle(): string {
    if (this.loadState() === 'not-found') {
      return 'Application not found';
    }

    if (this.loadState() === 'forbidden') {
      return 'Access denied';
    }

    return 'Application could not be loaded';
  }

  get errorMessage(): string {
    if (this.loadState() === 'not-found') {
      return 'The application does not exist or is not available to this account.';
    }

    if (this.loadState() === 'forbidden') {
      return 'You do not have permission to view this application.';
    }

    return 'Check the connection and try again.';
  }

  loadApplication(): void {
    const id = this.applicationId();
    if (id === null) {
      return;
    }

    this.apiErrors.clear();
    this.application.set(null);
    this.loadState.set('loading');
    this.applications
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (application) => {
          this.application.set({
            ...application,
            interviews: this.sortInterviews(application.interviews),
          });
          this.loadState.set('ready');
        },
        error: (error: unknown) => {
          this.loadState.set(this.errorState(error));
        },
      });
  }

  openCreateInterview(): void {
    this.selectedInterview.set(null);
    this.dialogMode.set('create');
    this.validationErrors.set({});
    this.dialogVisible.set(true);
  }

  openEditInterview(interview: Interview): void {
    this.selectedInterview.set(interview);
    this.dialogMode.set('edit');
    this.validationErrors.set({});
    this.dialogVisible.set(true);
  }

  closeDialog(): void {
    if (!this.saving()) {
      this.dialogVisible.set(false);
      this.validationErrors.set({});
    }
  }

  saveInterview(payload: InterviewPayload): void {
    const applicationId = this.applicationId();
    if (applicationId === null) {
      return;
    }

    this.apiErrors.clear();
    this.validationErrors.set({});
    this.saving.set(true);

    const selected = this.selectedInterview();
    const request = selected
      ? this.interviews.update(applicationId, selected.id, payload)
      : this.interviews.create(applicationId, payload);

    request
      .pipe(
        finalize(() => this.saving.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (interview) => {
          this.storeInterview(interview);
          this.activityTimeline()?.refresh();
          this.dialogVisible.set(false);
          this.snackBar.open(selected ? 'Interview updated.' : 'Interview scheduled.', 'Dismiss', {
            duration: 3000,
          });
        },
        error: () => {
          const fieldErrors = this.apiErrors.lastError()?.fieldErrors ?? {};
          this.validationErrors.set(fieldErrors);
          if (Object.keys(fieldErrors).length === 0) {
            this.showOperationError('Interview could not be saved.');
          }
        },
      });
  }

  requestDeleteInterview(interview: Interview): void {
    this.interviewPendingDelete.set(interview);
    this.confirmDeleteVisible.set(true);
  }

  cancelDeleteInterview(): void {
    if (this.deleting()) {
      return;
    }

    this.confirmDeleteVisible.set(false);
    this.interviewPendingDelete.set(null);
  }

  confirmDeleteInterview(): void {
    const applicationId = this.applicationId();
    const interview = this.interviewPendingDelete();
    if (applicationId === null || !interview || this.deleting()) {
      return;
    }

    this.apiErrors.clear();
    this.deleting.set(true);
    this.interviews
      .remove(applicationId, interview.id)
      .pipe(
        finalize(() => this.deleting.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: () => {
          this.application.update((application) =>
            application
              ? {
                  ...application,
                  interviews: application.interviews.filter(({ id }) => id !== interview.id),
                }
              : application,
          );
          this.confirmDeleteVisible.set(false);
          this.interviewPendingDelete.set(null);
          this.activityTimeline()?.refresh();
          this.snackBar.open('Interview deleted.', 'Dismiss', { duration: 3000 });
        },
        error: () => {
          this.confirmDeleteVisible.set(false);
          this.interviewPendingDelete.set(null);
          this.showOperationError('Interview could not be deleted.');
        },
      });
  }

  salaryLabel(application: JobApplicationDetail): string {
    if (application.salary_min === null && application.salary_max === null) {
      return 'Not specified';
    }

    const formatter = new Intl.NumberFormat('en', {
      ...(application.currency
        ? { style: 'currency' as const, currency: application.currency }
        : {}),
      maximumFractionDigits: 2,
    });
    const minimum =
      application.salary_min === null ? null : formatter.format(application.salary_min);
    const maximum =
      application.salary_max === null ? null : formatter.format(application.salary_max);

    return minimum && maximum ? `${minimum} – ${maximum}` : (minimum ?? maximum ?? 'Not specified');
  }

  isLink(value: string | null): boolean {
    return /^https?:\/\//i.test(value ?? '');
  }

  private storeInterview(interview: Interview): void {
    this.application.update((application) => {
      if (!application) {
        return application;
      }

      const exists = application.interviews.some(({ id }) => id === interview.id);
      const interviews = exists
        ? application.interviews.map((current) =>
            current.id === interview.id ? interview : current,
          )
        : [...application.interviews, interview];

      return { ...application, interviews: this.sortInterviews(interviews) };
    });
  }

  private sortInterviews(interviews: Interview[]): Interview[] {
    return [...interviews].sort(
      (left, right) =>
        new Date(left.scheduled_at).getTime() - new Date(right.scheduled_at).getTime() ||
        left.id - right.id,
    );
  }

  private errorState(error: unknown): DetailLoadState {
    if (error instanceof HttpErrorResponse) {
      if (error.status === 404) {
        return 'not-found';
      }

      if (error.status === 401 || error.status === 403) {
        return 'forbidden';
      }
    }

    return 'error';
  }

  private showOperationError(fallback: string): void {
    this.snackBar.open(this.apiErrors.lastError()?.message ?? fallback, 'Dismiss', {
      duration: 5000,
      horizontalPosition: 'end',
      verticalPosition: 'top',
      panelClass: ['error-snackbar'],
    });
  }
}
