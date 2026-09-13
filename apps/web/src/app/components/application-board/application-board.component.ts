import { DatePipe, TitleCasePipe } from '@angular/common';
import { CdkDragDrop, DragDropModule } from '@angular/cdk/drag-drop';
import { ScrollingModule } from '@angular/cdk/scrolling';
import { Component, computed, input, output } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';
import {
  JobApplication,
  JobApplicationStatus,
  JOB_APPLICATION_STATUSES,
} from '../../models/job-tracker.model';

type BoardColumn = {
  status: JobApplicationStatus;
  applications: JobApplication[];
};

export type ApplicationBoardMove = {
  application: JobApplication;
  previousStatus: JobApplicationStatus;
  status: JobApplicationStatus;
  previousIndex: number;
  targetIndex: number;
};

@Component({
  selector: 'app-application-board',
  standalone: true,
  imports: [
    DatePipe,
    DragDropModule,
    MatButtonModule,
    MatIconModule,
    RouterLink,
    ScrollingModule,
    TitleCasePipe,
  ],
  templateUrl: './application-board.component.html',
  styleUrl: './application-board.component.scss',
})
export class ApplicationBoardComponent {
  readonly applications = input.required<JobApplication[]>();
  readonly totalApplications = input(0);
  readonly movePending = input(false);
  readonly announcement = input('');
  readonly applicationMove = output<ApplicationBoardMove>();

  readonly columns = computed<BoardColumn[]>(() =>
    JOB_APPLICATION_STATUSES.map((status) => ({
      status,
      applications: this.applications().filter((application) => application.status === status),
    })),
  );

  readonly isTruncated = computed(() => this.totalApplications() > this.applications().length);

  drop(event: CdkDragDrop<BoardColumn, BoardColumn, JobApplication>): void {
    if (
      this.movePending() ||
      (event.previousContainer === event.container && event.previousIndex === event.currentIndex)
    ) {
      return;
    }

    this.applicationMove.emit({
      application: event.item.data,
      previousStatus: event.previousContainer.data.status,
      status: event.container.data.status,
      previousIndex: event.previousIndex,
      targetIndex: event.currentIndex,
    });
  }
}
