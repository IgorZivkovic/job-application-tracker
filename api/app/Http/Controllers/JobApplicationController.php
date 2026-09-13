<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListJobApplicationsRequest;
use App\Http\Requests\MoveJobApplicationRequest;
use App\Http\Requests\StoreJobApplicationRequest;
use App\Http\Requests\UpdateJobApplicationRequest;
use App\Http\Resources\JobApplicationResource;
use App\Models\AuthUser;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class JobApplicationController extends Controller
{
    /**
     * List owned job applications.
     *
     * Returns Laravel pagination metadata and company summaries. Supports search, filters and whitelisted sorting.
     */
    public function index(ListJobApplicationsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? null;
        $workMode = $filters['work_mode'] ?? null;
        $companyId = $filters['company_id'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        /** @var AuthUser $authUser */
        $authUser = $request->user();

        $applications = JobApplication::query()
            ->with('company:id,name')
            ->ownedBy($authUser)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereLike('position', "%{$search}%")
                        ->orWhereHas(
                            'company',
                            fn (Builder $companyQuery) => $companyQuery->whereLike('name', "%{$search}%"),
                        );
                });
            })
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($workMode !== null, fn (Builder $query) => $query->where('work_mode', $workMode))
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        return JobApplicationResource::collection($applications);
    }

    /**
     * Get an owned job application.
     *
     * Includes the company summary and interviews sorted by their scheduled time.
     */
    public function show(JobApplication $jobApplication): JobApplicationResource
    {
        return new JobApplicationResource($jobApplication->load([
            'company:id,name',
            'interviews' => fn ($query) => $query
                ->orderBy('scheduled_at')
                ->orderBy('id'),
        ]));
    }

    /**
     * Create a job application.
     *
     * The selected company must belong to the authenticated account.
     */
    public function store(StoreJobApplicationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $companyId = $data['company_id'];
        unset($data['company_id']);

        /** @var AuthUser $authUser */
        $authUser = $request->user();
        $application = DB::transaction(function () use ($authUser, $companyId, $data): JobApplication {
            $this->lockBoard($authUser);
            $company = $authUser->companies()->findOrFail($companyId);
            $data['board_order'] = $this->nextBoardOrder($authUser, $data['status']);

            return $company->jobApplications()->create($data);
        });

        return (new JobApplicationResource($application->load('company:id,name')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an owned job application.
     *
     * Only supplied fields are changed. A replacement company must belong to the same account.
     */
    public function update(
        UpdateJobApplicationRequest $request,
        JobApplication $jobApplication,
    ): JobApplicationResource {
        $data = $request->validated();
        /** @var AuthUser $authUser */
        $authUser = $request->user();

        DB::transaction(function () use ($authUser, $data, $jobApplication): void {
            $this->lockBoard($authUser);
            $sourceStatus = $jobApplication->status->value;
            $targetStatus = $data['status'] ?? $sourceStatus;

            if (array_key_exists('company_id', $data)) {
                $company = $authUser->companies()->findOrFail($data['company_id']);
                $jobApplication->company()->associate($company);
                unset($data['company_id']);
            }

            if ($targetStatus !== $sourceStatus) {
                $sourceIds = $this->withoutApplication(
                    $this->orderedBoardIds($authUser, $sourceStatus),
                    $jobApplication,
                );
                $targetIds = $this->orderedBoardIds($authUser, $targetStatus);
                $targetIds[] = $jobApplication->getKey();

                $jobApplication->fill($data);
                $jobApplication->board_order = count($targetIds);
                $jobApplication->save();

                $this->reindexBoard($sourceIds);
                $this->reindexBoard($targetIds);

                return;
            }

            $jobApplication->fill($data)->save();
        });

        return new JobApplicationResource(
            $jobApplication->refresh()->load('company:id,name'),
        );
    }

    /**
     * Move an application to an exact position on the Kanban board.
     */
    public function move(
        MoveJobApplicationRequest $request,
        JobApplication $jobApplication,
    ): JobApplicationResource {
        $data = $request->validated();
        /** @var AuthUser $authUser */
        $authUser = $request->user();

        DB::transaction(function () use ($authUser, $data, $jobApplication): void {
            $this->lockBoard($authUser);
            $sourceStatus = $jobApplication->status->value;
            $targetStatus = $data['status'];
            $sourceIds = $this->withoutApplication(
                $this->orderedBoardIds($authUser, $sourceStatus),
                $jobApplication,
            );

            if ($targetStatus === $sourceStatus) {
                $targetIds = $sourceIds;
            } else {
                $targetIds = $this->orderedBoardIds($authUser, $targetStatus);
            }

            $targetIndex = min((int) $data['target_index'], count($targetIds));
            array_splice($targetIds, $targetIndex, 0, [$jobApplication->getKey()]);

            if ($targetStatus !== $sourceStatus) {
                $jobApplication->status = $targetStatus;
                $jobApplication->board_order = $targetIndex + 1;
                $jobApplication->save();
                $this->reindexBoard($sourceIds);
            }

            $this->reindexBoard($targetIds);
        });

        return new JobApplicationResource(
            $jobApplication->refresh()->load('company:id,name'),
        );
    }

    /**
     * Delete an owned job application.
     *
     * Associated interviews are deleted with the application.
     */
    public function destroy(Request $request, JobApplication $jobApplication): JsonResponse
    {
        /** @var AuthUser $authUser */
        $authUser = $request->user();

        DB::transaction(function () use ($authUser, $jobApplication): void {
            $this->lockBoard($authUser);
            $status = $jobApplication->status->value;
            $jobApplication->delete();
            $this->reindexBoard($this->orderedBoardIds($authUser, $status));
        });

        return response()->json(['deleted' => true]);
    }

    private function lockBoard(AuthUser $authUser): void
    {
        AuthUser::query()->whereKey($authUser->getKey())->lockForUpdate()->firstOrFail();
    }

    private function nextBoardOrder(AuthUser $authUser, string $status): int
    {
        return (int) JobApplication::query()
            ->ownedBy($authUser)
            ->where('status', $status)
            ->max('board_order') + 1;
    }

    /**
     * @return array<int, int>
     */
    private function orderedBoardIds(AuthUser $authUser, string $status): array
    {
        return JobApplication::query()
            ->ownedBy($authUser)
            ->where('status', $status)
            ->orderBy('board_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function withoutApplication(array $ids, JobApplication $jobApplication): array
    {
        return array_values(array_filter(
            $ids,
            static fn (int $id): bool => $id !== $jobApplication->getKey(),
        ));
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function reindexBoard(array $ids): void
    {
        foreach ($ids as $index => $id) {
            JobApplication::query()->whereKey($id)->update(['board_order' => $index + 1]);
        }
    }
}
