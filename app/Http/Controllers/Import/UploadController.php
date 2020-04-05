<?php
/**
 * UploadController.php
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

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Middleware\UploadedFiles;
use App\Services\Configuration\ConfigFileProcessor;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\MessageBag;

/**
 * Class UploadController.
 */
class UploadController extends Controller
{
    /**
     * UploadController constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware(UploadedFiles::class);
    }

    /**
     * @param Request $request
     *
     * @throws FileNotFoundException
     * @return RedirectResponse|Redirector
     */
    public function upload(Request $request)
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $configFile = $request->file('config_file');
        $errors     = new MessageBag;

        // if present, and no errors, upload the config file and store it in the session.
        if (null !== $configFile) {
            app('log')->debug('Config file is present.');
            $errorNumber = $configFile->getError();
            if (0 !== $errorNumber) {
                $errors->add('config_file', $this->getError((int) $errorNumber));
            }
            // upload the file to a temp directory and use it from there.
            if (0 === $errorNumber) {
                app('log')->debug('Config file uploaded.');
                $configFileName = StorageService::storeContent(file_get_contents($configFile->getPathname()));

                session()->put(Constants::UPLOAD_CONFIG_FILE, $configFileName);

                // process the config file
                $configuration = ConfigFileProcessor::convertConfigFile($configFileName);
                session()->put(Constants::CONFIGURATION, $configuration->toArray());
            }
        }

        if ($errors->count() > 0) {
            return redirect(route('import.start'))->withErrors($errors);
        }

        return redirect(route('import.budgets.index'));
    }

    /**
     * @param int $error
     *
     * @return string
     */
    private function getError(int $error): string
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        $errors = [
            UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success.',
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk. Introduced in PHP 5.1.0.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];

        return $errors[$error] ?? 'Unknown error';
    }
}
