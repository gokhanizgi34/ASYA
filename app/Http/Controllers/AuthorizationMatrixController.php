<?php

namespace App\Http\Controllers;

use App\Services\AuthorizationMatrix;
use App\UserRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuthorizationMatrixController extends Controller
{
    public function __invoke(AuthorizationMatrix $matrix): View
    {
        Gate::authorize('viewAuthorizationMatrix');

        return view('authorization-matrix.index', [
            'rows' => $matrix->rows(),
            'roles' => UserRole::cases(),
        ]);
    }
}
