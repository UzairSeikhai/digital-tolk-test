<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepositoryInterface;
use DTApi\Http\Requests\IndexRequest;
use DTApi\Http\Requests\StoreRequest;
use DTApi\Http\Requests\UpdateRequest;
use DTApi\Http\Requests\ImmediateJobEmailRequest;
use DTApi\Http\Requests\DistanceFeedRequest;


/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepositoryInterface $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(IndexRequest $request)
    {
        $userId = $request->get('user_id');
        $authenticatedUser = $request->__authenticatedUser;

        $response = $userId 
            ? $this->repository->getUsersJobs($userId)
            : ($this->isAdmin($authenticatedUser) 
                ? $this->repository->getAll($request)
                : null);

        return response()->json($response);
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return response()->json($job);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRequest $request)
    {
        $data = $request->all();
        $response = $this->repository->store($request->__authenticatedUser, $data);
        return response()->json($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, UpdateRequest $request)
    {
        $data = $request->except(['_token', 'submit']);
        $response = $this->repository->updateJob($id, $data, $request->__authenticatedUser);
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function immediateJobEmail(ImmediateJobEmailRequest $request)
    {
        $response = $this->repository->storeJobEmail($request->all());
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHistory(Request $request)
    {
        $userId = $request->get('user_id');

        if ($userId) {
            $response = $this->repository->getUsersJobsHistory($userId, $request);
            return response()->json($response);
        }

        return response()->json(null);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptJob(Request $request)
    {
        $response = $this->repository->acceptJob($request->all(), $request->__authenticatedUser);
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptJobWithId(Request $request)
    {
        $response = $this->repository->acceptJobWithId($request->get('job_id'), $request->__authenticatedUser);
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelJob(Request $request)
    {
        $response = $this->repository->cancelJobAjax($request->all(), $request->__authenticatedUser);
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function endJob(Request $request)
    {
        $response = $this->repository->endJob($request->all());
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerNotCall(Request $request)
    {
        $response = $this->repository->customerNotCall($request->all());
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPotentialJobs(Request $request)
    {
        $response = $this->repository->getPotentialJobs($request->__authenticatedUser);
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function distanceFeed(DistanceFeedRequest $request)
    {
        $data = $request->all();
        $jobId = $data['jobid'] ?? null;

        if (!$jobId) {
            return response()->json(['error' => 'Job ID is required'], 400);
        }

        $updates = [
            'distance' => $data['distance'] ?? '',
            'time' => $data['time'] ?? '',
        ];
        Distance::where('job_id', $jobId)->update($updates);

        $jobUpdates = [
            'admin_comments' => $data['admincomment'] ?? '',
            'flagged' => $data['flagged'] === 'true' ? 'yes' : 'no',
            'session_time' => $data['session_time'] ?? '',
            'manually_handled' => $data['manually_handled'] === 'true' ? 'yes' : 'no',
            'by_admin' => $data['by_admin'] === 'true' ? 'yes' : 'no',
        ];
        Job::where('id', $jobId)->update($jobUpdates);

        return response()->json(['success' => 'Record updated!']);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reopen(Request $request)
    {
        $response = $this->repository->reopen($request->all());
        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendNotifications(Request $request)
    {
        $job = $this->repository->find($request->input('jobid'));
        $jobData = $this->repository->jobToData($job);

        $this->repository->sendNotificationTranslator($job, $jobData, '*');

        return response()->json(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendSMSNotifications(Request $request)
    {
        $job = $this->repository->find($request->input('jobid'));

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response()->json(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Checks if the authenticated user is an admin.
     * @param $user
     * @return bool
     */
    private function isAdmin($user)
    {
        return in_array($user->user_type, [env('ADMIN_ROLE_ID'), env('SUPERADMIN_ROLE_ID')]);
    }
}
