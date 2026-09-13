<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListApplicationActivitiesRequest;
use App\Http\Requests\StoreApplicationActivityRequest;
use App\Http\Resources\ApplicationActivityResource;
use App\Models\AuthUser;
use App\Models\JobApplication;
use App\Services\ApplicationActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApplicationActivityController extends Controller
{
    public function __construct(private readonly ApplicationActivityRecorder $recorder) {}

    /**
     * List an application's immutable activity timeline, newest first.
     */
    public function index(
        ListApplicationActivitiesRequest $request,
        JobApplication $jobApplication,
    ): AnonymousResourceCollection {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $activities = $jobApplication->activities()
            ->with('actor:id,email')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ApplicationActivityResource::collection($activities);
    }

    /**
     * Add a manual comment to an application's activity timeline.
     */
    public function store(
        StoreApplicationActivityRequest $request,
        JobApplication $jobApplication,
    ): JsonResponse {
        /** @var AuthUser $authUser */
        $authUser = $request->user();

        $activity = DB::transaction(fn () => $this->recorder->recordComment(
            $jobApplication,
            $authUser,
            $request->validated('comment'),
        ));

        return (new ApplicationActivityResource($activity->load('actor:id,email')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
