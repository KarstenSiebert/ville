<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use App\Helpers\CardanoCliWrapper;
use App\Http\Services\VaultService;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles, HasApiTokens, LaravelPermissionToVueJS, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'parent_user_id',
        'owner_user_id',
        'publisher_id',
        'external_user_id',
        'email',
        'password',
        'profile_photo_path',
        'payout',
        'type',
        'is_system',
        'public_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [        
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
        'two_factor_confirmed_at',
        'public_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_system' => 'boolean'
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'avatar',
    ];

     /**
     * Get the URL of the user's profile photo.
     *
     * @return string|null
     */
    public function getAvatarAttribute(): ?string
    {
        if ($this->profile_photo_path) {
           return Storage::url($this->profile_photo_path);
        }

        return null;
    }

    public function publisher()
    {
        return $this->belongsTo(Publisher::class);
    }

    public function administeredPublishers()
    {
        return $this->belongsToMany(Publisher::class)
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function scopeOfPublisher($query, $publisherId)
    {
        return $query->where('publisher_id', $publisherId);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }
       
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function avaWallet()
    {
        return $this->hasOne(Wallet::class)->where('type', 'available');        
    }
    
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function input_outputs()
    {
        return $this->hasMany(InputOutput::class);
    }
    
    /**
     * Create a custody wallet for the user.
     * 
     * * @return bool
     */
    public function createWallet(): bool
    {
        if (empty(Wallet::where('user_id', $this->id)->where('type', 'deposit')->first())) {

            $path = '/tmp/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';

            if (CardanoCliWrapper::make_dir($path)) {

                if ($this->generateAddress($this->id, $path)) {

                    $wallet = new Wallet;

                    $wallet->user_id = $this->id;

                    $wallet->address = trim(file_get_contents($path.'user.address'));

                    $wallet->type = 'deposit';
                                        
                    if (!empty($wallet->address) && $wallet->save()) {
                                              
                        $avWallet = new Wallet;

                        $avWallet->user_id = $this->id;

                        $avWallet->parent_wallet_id = $wallet->id;

                        $avWallet->address = 'ava'.$wallet->address;                        

                        $avWallet->type = 'available';
                                        
                        if ($avWallet->save()) {
                            $reWallet = new Wallet;

                            $reWallet->user_id = $this->id;

                            $reWallet->parent_wallet_id = $wallet->id;

                            $reWallet->address = 'res'.$wallet->address;                            

                            $reWallet->type = 'reserved';
                    
                            if ($reWallet->save()) {                                       
                                return (CardanoCliWrapper::remove_dir($path));
                            }
                        }
                    }
                }                
                
                CardanoCliWrapper::remove_dir($path);
            }
        }
        
        return false;
    }

    private function generateAddress($index, $path) : bool
    {                
        file_put_contents($path.'root.xsk', VaultService::getRootKey());
        
        $cmd = '/usr/local/bin/genaddr.sh '.$path.'root.xsk '.$index.' '.$path;

        $ret = 0;

        $output = [];

        if (exec($cmd, $output, $ret) !== false) {
            
            if (file_exists($path.'user.address')) {
                return true;
            }
        }

        return false;
    }

    public function createPolicy($path): ?string
    {
        if ($this->generateVerificationKey($this->id, $path)) {
            $verificationKeyFile = $path.'payment.vkey';
            
            $cmd = 'cardano-cli address key-hash --payment-verification-key-file '.$path.'payment.vkey';

            $ret = 0;

            $output = [];
            
            if (exec($cmd, $output, $ret) !== false) {
            
                if (!empty($output) && !empty($output[0])) {
                    file_put_contents($path.'policy.script', '{'.PHP_EOL);
                    file_put_contents($path.'policy.script', '    "type": "sig",'.PHP_EOL, FILE_APPEND);
                    file_put_contents($path.'policy.script', '    "keyHash": "'.$output[0].'"'.PHP_EOL, FILE_APPEND);
                    file_put_contents($path.'policy.script', '}', FILE_APPEND);  

                    $cmd = 'cardano-cli conway transaction policyid --script-file '.$path.'policy.script';
                                
                    $ret = 0;

                    $output = [];

                    if (exec($cmd, $output, $ret) !== false) {

                        if (!empty($output) && !empty($output[0])) {
                            return $output[0];
                        }
                    }
                }
            }
        }
        
        return null;
    }

    private function generateVerificationKey($index, $path) : bool
    {        
        file_put_contents($path.'root.xsk', VaultService::getRootKey());

        $cmd = '/usr/local/bin/genvkey.sh '.$path.'root.xsk '.$index.' '.$path;

        $ret = 0;

        $output = [];

        if (exec($cmd, $output, $ret) !== false) {
            
            if (file_exists($path.'payment.vkey')) {
                return true;
            }
        }
        
        return false;
    }    

    public function jsPermissions(): array
    {
        return [
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()
                ->pluck('name')
                ->values(),
        ];
    }

}
