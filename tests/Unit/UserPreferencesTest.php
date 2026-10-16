<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserPreferencesTest extends TestCase
{
    public function test_new_users_default_to_dark_theme_and_seven_day_alerts(): void
    {
        $usuario = new User;

        $this->assertSame('dark', $usuario->theme);
        $this->assertSame(7, $usuario->notification_days_before);
    }

    public function test_alert_preference_is_cast_to_integer(): void
    {
        $usuario = new User(['notification_days_before' => '15']);

        $this->assertSame(15, $usuario->notification_days_before);
    }

    public function test_credentials_are_hidden_from_serialization(): void
    {
        $usuario = new User;
        $usuario->setRawAttributes(['password' => 'hash', 'remember_token' => 'token']);

        $this->assertArrayNotHasKey('password', $usuario->toArray());
        $this->assertArrayNotHasKey('remember_token', $usuario->toArray());
    }
}
