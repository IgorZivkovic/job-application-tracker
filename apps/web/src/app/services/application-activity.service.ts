import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { catchError, map, Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { DataResponse, PaginatedResponse } from '../models/api.model';
import { ApplicationActivity } from '../models/job-tracker.model';
import { ApiErrorService } from './api-error.service';

@Injectable({ providedIn: 'root' })
export class ApplicationActivityService {
  private readonly http = inject(HttpClient);
  private readonly errors = inject(ApiErrorService);
  private readonly applicationsEndpoint = `${environment.apiBaseUrl}/job-applications`;

  list(
    applicationId: number,
    page = 1,
    perPage = 10,
  ): Observable<PaginatedResponse<ApplicationActivity>> {
    const params = new HttpParams({
      fromObject: { page: String(page), per_page: String(perPage) },
    });

    return this.http
      .get<PaginatedResponse<ApplicationActivity>>(this.endpoint(applicationId), { params })
      .pipe(catchError((error) => this.errors.handle(error, 'Failed to load activity.')));
  }

  addComment(applicationId: number, comment: string): Observable<ApplicationActivity> {
    return this.http
      .post<DataResponse<ApplicationActivity>>(this.endpoint(applicationId), { comment })
      .pipe(
        map((response) => response.data),
        catchError((error) => this.errors.handle(error, 'Failed to add the comment.')),
      );
  }

  private endpoint(applicationId: number): string {
    return `${this.applicationsEndpoint}/${applicationId}/activities`;
  }
}
