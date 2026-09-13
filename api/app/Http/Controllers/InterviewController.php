<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInterviewRequest;
use App\Http\Requests\UpdateInterviewRequest;
use App\Http\Resources\InterviewResource;
use App\Models\AuthUser;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Services\ApplicationActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InterviewController extends Controller
{
    public function __construct(private readonly ApplicationActivityRecorder $activityRecorder) {}

    /**
     * List interviews for an owned job application.
     *
     * Results are sorted chronologically by scheduled time.
     */
    public function index(JobApplication $jobApplication): AnonymousResourceCollection
    {
        $interviews = $jobApplication->interviews()
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        return InterviewResource::collection($interviews);
    }

    /**
     * Schedule an interview.
     *
     * The parent job application must belong to the authenticated account.
     */
    public function store(
        StoreInterviewRequest $request,
        JobApplication $jobApplication,
    ): JsonResponse {
        /** @var AuthUser $authUser */
        $authUser = $request->user();
        $interview = DB::transaction(function () use ($authUser, $jobApplication, $request): Interview {
            $interview = $jobApplication->interviews()->create($request->validated());
            $this->activityRecorder->recordInterviewScheduled($jobApplication, $interview, $authUser);

            return $interview;
        });

        return (new InterviewResource($interview))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update an interview.
     *
     * The interview must belong to both the authenticated account and the application in the URL.
     */
    public function update(
        UpdateInterviewRequest $request,
        JobApplication $jobApplication,
        Interview $interview,
    ): InterviewResource {
        /** @var AuthUser $authUser */
        $authUser = $request->user();

        DB::transaction(function () use ($authUser, $interview, $jobApplication, $request): void {
            $before = $this->activityRecorder->interviewSnapshot($interview);
            $interview->update($request->validated());
            $this->activityRecorder->recordInterviewChanges(
                $jobApplication,
                $interview->refresh(),
                $authUser,
                $before,
            );
        });

        return new InterviewResource($interview->refresh());
    }

    /**
     * Delete an interview.
     *
     * The interview must belong to the application in the URL.
     */
    public function destroy(
        Request $request,
        JobApplication $jobApplication,
        Interview $interview,
    ): JsonResponse {
        /** @var AuthUser $authUser */
        $authUser = $request->user();

        DB::transaction(function () use ($authUser, $interview, $jobApplication): void {
            $this->activityRecorder->recordInterviewDeleted($jobApplication, $interview, $authUser);
            $interview->delete();
        });

        return response()->json(['deleted' => true]);
    }
}
