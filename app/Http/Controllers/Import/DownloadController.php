<?php
/**
 * DownloadController.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III YNAB importer
 * (https://github.com/firefly-iii/ynab-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);
/**
 * DownloadController.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III YNAB importer
 * (https://github.com/firefly-iii/ynab-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Http\Controllers\Import;

use App\Exceptions\ImportException;
use App\Http\Controllers\Controller;
use App\Services\Configuration\Configuration;
use App\Services\Session\Constants;
use App\Ynab\Download\JobStatus\JobStatus;
use App\Ynab\Download\JobStatus\JobStatusManager;
use App\Ynab\Download\RoutineManager;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Class DownloadController
 */
class DownloadController extends Controller
{
    /**
     * @return Factory|View
     */
    public function index()
    {
        $mainTitle = 'Downloading transactions...';
        $subTitle  = 'Connecting to YNAB and downloading your data...';

        // job ID may be in session:
        $downloadIdentifier = session()->get(Constants::DOWNLOAD_JOB_IDENTIFIER);
        if (null !== $downloadIdentifier) {
            // create a new import job:
            new RoutineManager($downloadIdentifier);
        }
        if (null === $downloadIdentifier) {
            // create a new import job:
            $routine            = new RoutineManager();
            $downloadIdentifier = $routine->getDownloadIdentifier();
        }

        app('log')->debug(sprintf('Download routine manager identifier is "%s"', $downloadIdentifier));

        // store identifier in session so the status can get it.
        session()->put(Constants::DOWNLOAD_JOB_IDENTIFIER, $downloadIdentifier);
        app('log')->debug(sprintf('Stored "%s" under "%s"', $downloadIdentifier, Constants::DOWNLOAD_JOB_IDENTIFIER));

        return view('import.download.index', compact('mainTitle', 'subTitle', 'downloadIdentifier'));
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function start(Request $request): JsonResponse
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $downloadIdentifier = $request->get('downloadIdentifier');
        $routine            = new RoutineManager($downloadIdentifier);

        // store identifier in session so the status can get it.
        session()->put(Constants::DOWNLOAD_JOB_IDENTIFIER, $downloadIdentifier);

        $downloadJobStatus = JobStatusManager::startOrFindJob($downloadIdentifier);
        if (JobStatus::JOB_DONE === $downloadJobStatus->status) {
            // TODO DISABLED DURING DEVELOPMENT:
            //app('log')->debug('Job already done!');
            //return response()->json($downloadJobStatus->toArray());
        }
        JobStatusManager::setJobStatus(JobStatus::JOB_RUNNING);

        try {
            $config = session()->get(Constants::CONFIGURATION) ?? [];
            $routine->setConfiguration(Configuration::fromArray($config));
            $routine->start();
        } catch (ImportException $e) {
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
        $downloadIdentifier = $request->get('downloadIdentifier');
        //app('log')->debug(sprintf('Now at %s(%s)', __METHOD__, $downloadIdentifier));
        if (null === $downloadIdentifier) {
            app('log')->warning('Download Identifier is NULL.');
            // no status is known yet because no identifier is in the session.
            // As a fallback, return empty status
            $fakeStatus = new JobStatus();

            return response()->json($fakeStatus->toArray());
        }
        $importJobStatus = JobStatusManager::startOrFindJob($downloadIdentifier);

        return response()->json($importJobStatus->toArray());
    }

}
