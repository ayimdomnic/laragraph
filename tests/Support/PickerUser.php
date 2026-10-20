<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Support;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A plain (non-Sanctum) authenticatable user for the GraphiQL picker tests.
 */
class PickerUser extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'users';

    protected $guarded = [];
}
