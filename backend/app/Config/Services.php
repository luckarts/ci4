<?php

namespace Config;

use App\Modules\Auth\Libraries\OAuthServer;
use App\Modules\Auth\Services\TokenRevocationService;
use App\Modules\Auth\Services\UserRegistrationService;
use App\Modules\Shared\Libraries\AuthContext;
use App\Modules\Shared\Repositories\UserRepository;
use App\Modules\User\Services\DeleteUserService;
use App\Modules\User\Services\UpdateProfileService;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function userRepository(bool $getShared = true): UserRepository
    {
        if ($getShared) {
            return static::getSharedInstance('userRepository');
        }

        return new UserRepository(\Config\Database::connect());
    }

    public static function userRegistrationService(bool $getShared = true): UserRegistrationService
    {
        if ($getShared) {
            return static::getSharedInstance('userRegistrationService');
        }

        return new UserRegistrationService(static::userRepository(false));
    }

    public static function updateProfileService(bool $getShared = true): UpdateProfileService
    {
        if ($getShared) {
            return static::getSharedInstance('updateProfileService');
        }

        return new UpdateProfileService(static::userRepository(false));
    }

    public static function deleteUserService(bool $getShared = true): DeleteUserService
    {
        if ($getShared) {
            return static::getSharedInstance('deleteUserService');
        }

        return new DeleteUserService(static::userRepository(false));
    }

    public static function tokenRevocationService(bool $getShared = true): TokenRevocationService
    {
        if ($getShared) {
            return static::getSharedInstance('tokenRevocationService');
        }

        return new TokenRevocationService(
            \Config\Database::connect(),
            (string) getenv('OAUTH_ENCRYPTION_KEY')
        );
    }

    public static function authContext(bool $getShared = true): AuthContext
    {
        if ($getShared) {
            return static::getSharedInstance('authContext');
        }

        return new AuthContext();
    }

    public static function oAuthServer(bool $getShared = true): OAuthServer
    {
        if ($getShared) {
            return static::getSharedInstance('oAuthServer');
        }

        return new OAuthServer(\Config\Database::connect());
    }
}
