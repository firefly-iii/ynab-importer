<?php

declare(strict_types=1);

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Http\Middleware\UploadedFiles;
use App\Services\Configuration\ConfigFileProcessor;
use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
