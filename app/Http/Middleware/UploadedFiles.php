<?php

declare(strict_types=1);
/**
 * UploadedFiles.php
 * Copyright (c) 2020 james@firefly-iii.org.
 *
 * This file is part of the Firefly III bunq importer
 * (https://github.com/firefly-iii/bunq-importer).
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

namespace App\Http\Middleware;

use App\Services\Session\Constants;
use Closure;
use Illuminate\Http\Request;

/**
 * Class UploadedFiles.
 */
class UploadedFiles
{
    /**
     * Check if the user has already uploaded files in this session. If so, continue to budget selection.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (session()->has(Constants::UPLOAD_CONFIG_FILE)) {
            return redirect()->route('import.budgets.index');
        }

        return $next($request);
    }
}
