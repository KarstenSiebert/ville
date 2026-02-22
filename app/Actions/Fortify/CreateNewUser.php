<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Notifications\NewUserRegistered;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'public_key' => ['nullable', 'string', 'max:2048'],
            'password' => $this->passwordRules(),
        ])->validate();

        $parent = DB::transaction(function () use ($input) {
        
            $publicKey = isset($input['public_key']) ? $input['public_key'] : null;

            $parent = User::create([
                'name' => $input['name'],
                'email' => $input['email'],                
                'password' => $input['password'],
                'public_key' => $publicKey
            ]);

            if (!Permission::where('name', 'create_markets')->where('guard_name', 'web')->exists()) {
                Permission::create(['name' => 'create_markets', 'guard_name' => 'web']);
            }
        
            if (!Role::where('name', 'admin')->where('guard_name', 'web')->exists()) {            
                $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

                $role->givePermissionTo('create_markets');
            }
            
            if (!Role::where('name', 'user')->where('guard_name', 'web')->exists()) {
                Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
            }

            if (!Role::where('name', 'shadow')->where('guard_name', 'web')->exists()) {
                Role::firstOrCreate(['name' => 'shadow', 'guard_name' => 'web']);
            }

            if (!Role::where('name', 'trusted')->where('guard_name', 'web')->exists()) {
                $role = Role::firstOrCreate(['name' => 'trusted', 'guard_name' => 'web']);

                $role->givePermissionTo('create_markets');
            }

            if (!Role::where('name', 'market')->where('guard_name', 'web')->exists()) {
                Role::firstOrCreate(['name' => 'market', 'guard_name' => 'web']);
            }

            if (!Role::where('name', 'operator')->where('guard_name', 'web')->exists()) {
                Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

                $role->givePermissionTo('create_markets');
            }                 
                        
            if ($parent->id == 1) {
                $parent->assignRole('admin');
            
            } else {
                $parent->assignRole('user');
            }

            $parent->createWallet();

            return $parent;
        });

        $admin = User::where('id', 1)->first();

        if ($admin && $parent) {
            $admin->notify(new NewUserRegistered($parent));
        }

        return $parent;
    }
}
