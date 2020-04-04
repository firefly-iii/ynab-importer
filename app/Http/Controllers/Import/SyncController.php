<?php

declare(strict_types=1);

namespace App\Http\Controllers\Import;

use App\Exceptions\ImportException;
use App\Http\Controllers\Controller;
use App\Services\Configuration\Configuration;
use App\Services\Session\Constants;
use App\Services\Sync\JobStatus\JobStatus;
use App\Services\Sync\JobStatus\JobStatusManager;
use App\Services\Sync\RoutineManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Class SyncController.
 */
class SyncController extends Controller
{
    /**
     * SyncController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        app('view')->share('pageTitle', 'Send data to Firefly III');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        //app('log')->debug(sprintf('Now at %s', __METHOD__));
        $mainTitle = 'Send data to Firefly III';
        $subTitle  = 'After download, comes import.';

        // get download job ID so we have the data to send to FF3
        $downloadIdentifier = session()->get(Constants::DOWNLOAD_JOB_IDENTIFIER);

        // get sync ID so we have a separate track thing.
        $syncIdentifier = session()->get(Constants::SYNC_JOB_IDENTIFIER);

        if (null !== $syncIdentifier) {
            // create a new import job:
            app('log')->debug('SyncController is creating new routine manager with existing sync identifier');
            new RoutineManager($syncIdentifier);
        }
        if (null === $syncIdentifier) {
            app('log')->debug('SyncController is creating new routine manager with NEW sync identifier');
            // create a new import job:
            $routine        = new RoutineManager(null);
            $syncIdentifier = $routine->getSyncIdentifier();
        }

        app('log')->debug(sprintf('Sync routine manager job identifier is "%s"', $syncIdentifier));

        // store identifier in session so the status can get it.
        session()->put(Constants::SYNC_JOB_IDENTIFIER, $syncIdentifier);
        app('log')->debug(sprintf('Stored "%s" under "%s"', $syncIdentifier, Constants::SYNC_JOB_IDENTIFIER));

        return view('import.sync.index', compact('mainTitle', 'subTitle', 'syncIdentifier', 'downloadIdentifier'));
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function start(Request $request): JsonResponse
    {
        //app('log')->debug(sprintf('Now at %s', __METHOD__));
        // get download job ID so we have the data to send to FF3
        $downloadIdentifier = session()->get(Constants::DOWNLOAD_JOB_IDENTIFIER);

        // get sync ID so we have a separate track thing.
        $syncIdentifier = session()->get(Constants::SYNC_JOB_IDENTIFIER);

        $routine = new RoutineManager($syncIdentifier);
        // store identifier in session so the status can get it (should already be there)
        session()->put(Constants::SYNC_JOB_IDENTIFIER, $syncIdentifier);
        session()->put(Constants::DOWNLOAD_JOB_IDENTIFIER, $downloadIdentifier);

        $downloadJobStatus = JobStatusManager::startOrFindJob($syncIdentifier);
        if (JobStatus::JOB_DONE === $downloadJobStatus->status) {
            // TODO DISABLED DURING DEVELOPMENT:
            //app('log')->debug('Job already done!');
            //return response()->json($downloadJobStatus->toArray());
        }
        JobStatusManager::setJobStatus(JobStatus::JOB_RUNNING);

        try {
            $config = session()->get(Constants::CONFIGURATION) ?? [];
            $routine->setConfiguration(Configuration::fromArray($config));
            $routine->setDownloadIdentifier($downloadIdentifier);
            $routine->setSyncIdentifier($syncIdentifier);
            $routine->start();
        } catch (ImportException $e) {
            // TODO better error handling.
            throw new RuntimeException($e->getMessage());
        }

        // set done:
        JobStatusManager::setJobStatus(JobStatus::JOB_DONE);

        return response()->json($downloadJobStatus->toArray());
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        $syncIdentifier = $request->get('syncIdentifier');
        //app('log')->debug(sprintf('Now at %s(%s)', __METHOD__, $syncIdentifier));
        if (null === $syncIdentifier) {
            app('log')->warning('Identifier is NULL.');
            // no status is known yet because no identifier is in the session.
            // As a fallback, return empty status
            $fakeStatus = new JobStatus();

            return response()->json($fakeStatus->toArray());
        }
        $importJobStatus = JobStatusManager::startOrFindJob($syncIdentifier);

        return response()->json($importJobStatus->toArray());
    }
}
