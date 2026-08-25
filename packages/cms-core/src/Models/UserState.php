<?php

namespace Pcteckserv\CmsCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserState extends Model
{
    protected $table = 'cms_user_states';

    protected $fillable = ['state', 'last_login_at'];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
