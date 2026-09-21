<?php

namespace App\Http\Controllers;

use App\Services\DeploymentPackageBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeploymentPackageController extends Controller
{
    public function __invoke(Request $request, DeploymentPackageBuilder $builder): Response
    {
        abort_unless((bool) config('deploy.enabled'), 404);

        $token = config('deploy.token');

        if (is_string($token) && $token !== '') {
            abort_unless(hash_equals($token, (string) $request->query('token')), 403);
        } else {
            abort_unless(app()->environment('local'), 403);
        }

        $package = $builder->build();

        return response()->view('deployment.compiled', [
            'package' => $package,
        ]);
    }
}
