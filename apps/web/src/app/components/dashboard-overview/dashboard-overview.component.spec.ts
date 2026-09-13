import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { DashboardInterview, JobApplication } from '../../models/job-tracker.model';
import { DashboardOverviewComponent } from './dashboard-overview.component';

describe('DashboardOverviewComponent', () => {
  let fixture: ComponentFixture<DashboardOverviewComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DashboardOverviewComponent],
      providers: [provideRouter([])],
    }).compileComponents();
    fixture = TestBed.createComponent(DashboardOverviewComponent);
  });

  it('renders recent applications and upcoming interviews', () => {
    fixture.componentRef.setInput('recentApplications', [application()]);
    fixture.componentRef.setInput('upcomingInterviews', [interview()]);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Angular Developer');
    expect(fixture.nativeElement.textContent).toContain('Northstar Labs');
    expect(fixture.nativeElement.textContent).toContain('Technical');
  });

  it('renders useful empty states', () => {
    fixture.componentRef.setInput('recentApplications', []);
    fixture.componentRef.setInput('upcomingInterviews', []);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('No applications yet');
    expect(fixture.nativeElement.textContent).toContain('No upcoming interviews');
  });
});

function application(): JobApplication {
  return {
    id: 12,
    company_id: 4,
    company: { id: 4, name: 'Northstar Labs' },
    position: 'Angular Developer',
    status: 'applied',
    board_order: 1,
    work_mode: 'remote',
    employment_type: 'full-time',
    source_url: null,
    applied_at: '2026-09-10',
    next_action_at: null,
    salary_min: null,
    salary_max: null,
    currency: null,
    notes: null,
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };
}

function interview(): DashboardInterview {
  return {
    id: 8,
    job_application_id: 12,
    job_application: {
      id: 12,
      position: 'Angular Developer',
      company: { id: 4, name: 'Northstar Labs' },
    },
    type: 'technical',
    scheduled_at: '2099-09-20T08:30:00Z',
    contact_name: null,
    contact_email: null,
    location_or_link: null,
    notes: null,
    outcome: null,
    created_at: '2026-09-12T10:00:00Z',
    updated_at: '2026-09-12T10:00:00Z',
  };
}
