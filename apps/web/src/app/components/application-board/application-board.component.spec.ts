import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { JobApplication } from '../../models/job-tracker.model';
import { ApplicationBoardComponent } from './application-board.component';

describe('ApplicationBoardComponent', () => {
  let fixture: ComponentFixture<ApplicationBoardComponent>;
  let component: ApplicationBoardComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ApplicationBoardComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(ApplicationBoardComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('applications', [
      application('applied'),
      application('offer', 2),
    ]);
    fixture.componentRef.setInput('totalApplications', 2);
    fixture.detectChanges();
  });

  it('creates all status columns and groups applications', () => {
    expect(component.columns()).toHaveLength(6);
    expect(
      component.columns().find(({ status }) => status === 'applied')?.applications,
    ).toHaveLength(1);
    expect(component.columns().find(({ status }) => status === 'offer')?.applications).toHaveLength(
      1,
    );
    expect(fixture.nativeElement.querySelectorAll('.board-column')).toHaveLength(6);
    expect(fixture.nativeElement.textContent).toContain('Frontend Developer');
  });

  it('reports when the board response is truncated', () => {
    fixture.componentRef.setInput('totalApplications', 120);
    fixture.detectChanges();

    expect(component.isTruncated()).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Showing the first 2 of 120 applications');
  });

  it('emits the exact source and target position after a drop', () => {
    const applied = component.columns().find(({ status }) => status === 'applied')!;
    const offer = component.columns().find(({ status }) => status === 'offer')!;
    const emit = vi.spyOn(component.applicationMove, 'emit');

    component.drop({
      previousContainer: { data: applied },
      container: { data: offer },
      previousIndex: 0,
      currentIndex: 1,
      item: { data: applied.applications[0] },
    } as never);

    expect(emit).toHaveBeenCalledWith({
      application: applied.applications[0],
      previousStatus: 'applied',
      status: 'offer',
      previousIndex: 0,
      targetIndex: 1,
    });
  });
});

function application(status: JobApplication['status'], id = 1): JobApplication {
  return {
    id,
    company_id: 4,
    company: { id: 4, name: 'Northstar Labs' },
    position: id === 1 ? 'Frontend Developer' : 'Product Engineer',
    status,
    board_order: id,
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
