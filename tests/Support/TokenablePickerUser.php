<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Support;

/**
 * A user model that *looks* Sanctum-capable without the package installed,
 * so the minted "token" is a fixed fixture string.
 */
class TokenablePickerUser extends PickerUser
{
    public function createToken(string $name, array $abilities = ['*']): object
    {
        return new class {
            public string $plainTextToken = 'dev-fixture-token';
        };
    }
}
