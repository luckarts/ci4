<?php

namespace App\Modules\Shared\Libraries;

class AuthContext
{
    private static ?string $userId = null;

    public static function setUserId(string $id): void
    {
        self::$userId = $id;
    }

    public static function getUserId(): ?string
    {
        return self::$userId;
    }

    public static function reset(): void
    {
        self::$userId = null;
    }
}
