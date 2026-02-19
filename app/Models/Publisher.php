<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publisher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'user_id',
        'owner_user_id',
        'api_key',
        'api_secret',
        'settings'
    ];

    protected $hidden = [
    ];

    protected function casts(): array
    {
        return [  
            'settings' => 'array',          
        ];
    }

    public function getActiveAttribute()
    {
        return $this->settings['active'] ?? null;
    }

    public function getRateLimitAttribute()
    {
        return $this->settings['rate_limit'] ?? null;
    }

    public function getMaxMarketsAttribute()
    {
        return $this->settings['max_markets'] ?? null;
    }

    public function getMaxShadowsAttribute()
    {
        return $this->settings['max_shadows'] ?? null;
    }

    public function getTrackingAttribute()
    {
        return $this->settings['features']['tracking'] ?? null;
    }

    public function getReportsAttribute()
    {
        return $this->settings['features']['reports'] ?? null;
    }
    
    public function admins()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function markets() {
        return $this->hasMany(Market::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class)->whereNull('deleted_at');
    }

    public function readers()
    {
        return $this->hasMany(User::class)->whereNull('deleted_at');
    }

}
