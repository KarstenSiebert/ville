<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublisherUser extends Model
{
    use SoftDeletes;

    protected $table = 'publish_user';
   
    protected $fillable = [
        'publisher_id',
        'user_id',
        'role'
    ];

    protected $hidden = [
        'publisher_id',
        'user_id',
        'role'        
    ];
           
}
