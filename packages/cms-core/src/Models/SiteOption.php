<?php

namespace Pcteckserv\CmsCore\Models;

use Illuminate\Database\Eloquent\Model;

class SiteOption extends Model
{
    protected $table = 'cms_site_options';

    protected $fillable = [
        'key',
        'value',
    ];
}
