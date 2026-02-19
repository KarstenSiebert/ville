<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InputOutput extends Model
{
    use SoftDeletes;

    /**
     * Summary of fillable
     * @var array
     */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'inputs',
        'outputs',
        'change'
    ];

    /**
     * Summary of hidden
     * @var array
     */
    protected $hidden = [
        'user_id',
        'wallet_id',        
        'inputs',
        'outputs',
        'change'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
