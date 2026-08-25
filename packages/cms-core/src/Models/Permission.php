<?php

namespace Pcteckserv\CmsCore\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'cms_permissions';

    protected $fillable = ['key', 'label', 'group', 'description'];
}
