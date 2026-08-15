<?php

namespace App\Enums;

enum UserActionType: string
{
    case Login = 'login';
    case Like = 'like';
    case Comment = 'comment';
    case Referral = 'referral';
    case Purchase = 'purchase';

    public function points(): int
    {
        return match ($this) {
            self::Login => 1,
            self::Like => 5,
            self::Comment => 20,
            self::Referral => 50,
            self::Purchase => 100,
        };
    }
}
