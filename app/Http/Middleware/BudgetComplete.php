<?php

/**
 * BudgetComplete.php
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

/**
 * BudgetComplete.php

 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Session\Constants;
use Closure;
use Illuminate\Http\Request;

/**
 * Class BudgetComplete
 */
class BudgetComplete
{
    /**
     * Check if the user has already uploaded files in this session. If so, continue to configuration.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (session()->has(Constants::BUDGET_COMPLETE_INDICATOR) && true === session()->get(Constants::BUDGET_COMPLETE_INDICATOR)) {
            return redirect()->route('import.configure.index');
        }

        return $next($request);
    }
}
