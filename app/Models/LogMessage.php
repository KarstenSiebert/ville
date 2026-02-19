<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogMessage extends Model
{
    use SoftDeletes;

    protected $table = 'log_messages';
}
