<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceOverviewQuery;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(WorkspaceOverviewQuery $overviewQuery): View
    {
        $user = auth()->user();

        return view('welcome', [
            'overview' => $user !== null ? $overviewQuery->forUser($user) : null,
        ]);
    }
}
