<?php

namespace App\Http\Controllers;

use App\Services\DeploymentPackageBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeploymentPackageController extends Controller
{
    public function __invoke(Request $request, DeploymentPackageBuilder $builder): Response
    {
        abort_unless($request->user()?->isCmsSuperAdmin(), 403);

        $package = $builder->build();

        return response()->download(
            $package['path'],
            basename($package['path']),
        );
    }
}
