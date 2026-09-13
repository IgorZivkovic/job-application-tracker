import { TitleCasePipe } from '@angular/common';
import { Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { finalize, forkJoin } from 'rxjs';
import {
  ApplicationBoardComponent,
  ApplicationBoardMove,
} from '../../components/application-board/application-board.component';
import { DashboardOverviewComponent } from '../../components/dashboard-overview/dashboard-overview.component';
import {
  JobApplication,
  JobApplicationStatus,
  JobTrackerDashboard,
  JOB_APPLICATION_STATUSES,
} from '../../models/job-tracker.model';
import { ApiErrorService } from '../../services/api-error.service';
import { DashboardService } from '../../services/dashboard.service';
import { JobApplicationService } from '../../services/job-application.service';

type StatusSummary = {
  status: JobApplicationStatus;
  count: number;
};

@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [
    ApplicationBoardComponent,
    DashboardOverviewComponent,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    RouterLink,
    TitleCasePipe,
  ],
  templateUrl: './dashboard-page.component.html',
  styleUrl: './dashboard-page.component.scss',
})
export class DashboardPageComponent {
  private readonly dashboardService = inject(DashboardService);
  private readonly applicationService = inject(JobApplicationService);
  private readonly apiErrors = inject(ApiErrorService);
  private readonly destroyRef = inject(DestroyRef);

  readonly dashboard = signal<JobTrackerDashboard | null>(null);
  readonly boardApplications = signal<JobApplication[]>([]);
  readonly boardTotal = signal(0);
  readonly loading = signal(true);
  readonly loadFailed = signal(false);
  readonly movePending = signal(false);
  readonly moveAnnouncement = signal('');

  readonly statusSummary = computed<StatusSummary[]>(() => {
    const dashboard = this.dashboard();
    return JOB_APPLICATION_STATUSES.map((status) => ({
      status,
      count: dashboard?.applications_by_status[status] ?? 0,
    }));
  });

  constructor() {
    this.loadDashboard();
  }

  loadDashboard(): void {
    this.apiErrors.clear();
    this.loading.set(true);
    this.loadFailed.set(false);
    this.dashboard.set(null);
    this.boardApplications.set([]);
    this.boardTotal.set(0);

    forkJoin({
      dashboard: this.dashboardService.get(),
      board: this.applicationService.list({
        page: 1,
        per_page: 100,
        sort: 'board_order',
        direction: 'asc',
      }),
    })
      .pipe(
        finalize(() => this.loading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: ({ dashboard, board }) => {
          this.dashboard.set(dashboard);
          this.boardApplications.set(board.data);
          this.boardTotal.set(board.meta.total);
        },
        error: () => {
          this.loadFailed.set(true);
        },
      });
  }

  moveApplication(event: ApplicationBoardMove): void {
    if (this.movePending()) {
      return;
    }

    const previousApplications = this.boardApplications();
    const previousDashboard = this.dashboard();

    this.apiErrors.clear();
    this.movePending.set(true);
    this.boardApplications.set(this.reorderApplications(previousApplications, event));
    this.updateDashboardStatus(event);

    this.applicationService
      .move(event.application.id, {
        status: event.status,
        target_index: event.targetIndex,
      })
      .pipe(
        finalize(() => this.movePending.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (updatedApplication) => {
          this.boardApplications.update((applications) =>
            applications.map((application) =>
              application.id === updatedApplication.id
                ? { ...updatedApplication, board_order: application.board_order }
                : application,
            ),
          );
          this.moveAnnouncement.set(
            `${updatedApplication.position} moved to ${this.statusLabel(event.status)}, position ${event.targetIndex + 1}.`,
          );
        },
        error: () => {
          this.boardApplications.set(previousApplications);
          this.dashboard.set(previousDashboard);
          this.moveAnnouncement.set(
            `${event.application.position} could not be moved. The previous order was restored.`,
          );
        },
      });
  }

  private reorderApplications(
    applications: JobApplication[],
    event: ApplicationBoardMove,
  ): JobApplication[] {
    const grouped = new Map<JobApplicationStatus, JobApplication[]>(
      JOB_APPLICATION_STATUSES.map((status) => [
        status,
        applications.filter((application) => application.status === status),
      ]),
    );
    const source = grouped.get(event.previousStatus) ?? [];
    const movedApplication = source.find(({ id }) => id === event.application.id);

    if (!movedApplication) {
      return applications;
    }

    grouped.set(
      event.previousStatus,
      source.filter(({ id }) => id !== movedApplication.id),
    );
    const target = grouped.get(event.status) ?? [];
    const targetIndex = Math.min(Math.max(event.targetIndex, 0), target.length);
    target.splice(targetIndex, 0, { ...movedApplication, status: event.status });
    grouped.set(event.status, target);

    return JOB_APPLICATION_STATUSES.flatMap((status) =>
      (grouped.get(status) ?? []).map((application, index) => ({
        ...application,
        board_order: index + 1,
      })),
    );
  }

  private updateDashboardStatus(event: ApplicationBoardMove): void {
    if (event.previousStatus === event.status) {
      return;
    }

    this.dashboard.update((dashboard) => {
      if (!dashboard) {
        return dashboard;
      }

      return {
        ...dashboard,
        applications_by_status: {
          ...dashboard.applications_by_status,
          [event.previousStatus]: Math.max(
            0,
            dashboard.applications_by_status[event.previousStatus] - 1,
          ),
          [event.status]: dashboard.applications_by_status[event.status] + 1,
        },
        recent_applications: dashboard.recent_applications.map((application) =>
          application.id === event.application.id
            ? { ...application, status: event.status }
            : application,
        ),
      };
    });
  }

  private statusLabel(status: JobApplicationStatus): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
  }
}
