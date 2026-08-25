<?php

namespace Pcteckserv\CmsCore\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

class UserModelResolver
{
    /** @return class-string<Authenticatable> */
    public function className(): string
    {
        $userModel = config('cms-core.user_model') ?: config('auth.providers.users.model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            throw new InvalidArgumentException('Configure um model de utilizador válido para o CMS Core.');
        }

        if (! is_subclass_of($userModel, Authenticatable::class)) {
            throw new InvalidArgumentException('O model de utilizador do CMS Core deve implementar Authenticatable.');
        }

        return $userModel;
    }
}
