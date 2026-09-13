import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { catchError, map, Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { DataResponse, DeleteResponse, PaginatedResponse } from '../models/api.model';
import {
  JobApplication,
  JobApplicationDetail,
  JobApplicationFilters,
  JobApplicationPayload,
  MoveJobApplicationPayload,
  UpdateJobApplicationPayload,
} from '../models/job-tracker.model';
import { ApiErrorService } from './api-error.service';

@Injectable({ providedIn: 'root' })
export class JobApplicationService {
  private readonly http = inject(HttpClient);
  private readonly errors = inject(ApiErrorService);
  private readonly endpoint = `${environment.apiBaseUrl}/job-applications`;

  list(filters: JobApplicationFilters = {}): Observable<PaginatedResponse<JobApplication>> {
    const params = new HttpParams({
      fromObject: {
        page: String(filters.page ?? 1),
        per_page: String(filters.per_page ?? 15),
        ...(filters.search?.trim() ? { search: filters.search.trim() } : {}),
        ...(filters.status ? { status: filters.status } : {}),
        ...(filters.work_mode ? { work_mode: filters.work_mode } : {}),
        ...(filters.company_id ? { company_id: String(filters.company_id) } : {}),
        ...(filters.sort ? { sort: filters.sort } : {}),
        ...(filters.direction ? { direction: filters.direction } : {}),
      },
    });

    return this.http
      .get<PaginatedResponse<JobApplication>>(this.endpoint, { params })
      .pipe(catchError((error) => this.errors.handle(error, 'Failed to load applications.')));
  }

  get(id: number): Observable<JobApplicationDetail> {
    return this.http.get<DataResponse<JobApplicationDetail>>(`${this.endpoint}/${id}`).pipe(
      map((response) => response.data),
      catchError((error) => this.errors.handle(error, 'Failed to load the application.')),
    );
  }

  create(payload: JobApplicationPayload): Observable<JobApplication> {
    return this.http.post<DataResponse<JobApplication>>(this.endpoint, payload).pipe(
      map((response) => response.data),
      catchError((error) => this.errors.handle(error, 'Failed to create the application.')),
    );
  }

  update(id: number, payload: UpdateJobApplicationPayload): Observable<JobApplication> {
    return this.http.patch<DataResponse<JobApplication>>(`${this.endpoint}/${id}`, payload).pipe(
      map((response) => response.data),
      catchError((error) => this.errors.handle(error, 'Failed to update the application.')),
    );
  }

  move(id: number, payload: MoveJobApplicationPayload): Observable<JobApplication> {
    return this.http
      .patch<DataResponse<JobApplication>>(`${this.endpoint}/${id}/move`, payload)
      .pipe(
        map((response) => response.data),
        catchError((error) => this.errors.handle(error, 'Failed to move the application.')),
      );
  }

  remove(id: number): Observable<DeleteResponse> {
    return this.http
      .delete<DeleteResponse>(`${this.endpoint}/${id}`)
      .pipe(catchError((error) => this.errors.handle(error, 'Failed to delete the application.')));
  }
}
