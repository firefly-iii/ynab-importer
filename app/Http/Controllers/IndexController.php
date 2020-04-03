<?php
/**
 * IndexController.php
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

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Artisan;

/**
 * Class IndexController
 */
class IndexController extends Controller
{
    /**
     * @return RedirectResponse|Redirector
     */
    public function flush()
    {
        app('log')->debug(sprintf('Now at %s', __METHOD__));
        session()->flush();
        Artisan::call('cache:clear');
        Artisan::call('config:clear');

        return redirect(route('index'));
    }

    /**
     *
     */
    public function index()
    {
        return view('index');
    }
}
