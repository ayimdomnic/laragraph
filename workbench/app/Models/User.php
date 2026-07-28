<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Example model — demonstrates how Laragraph auto-generates types & queries.
 */
class User extends Model
{
    protected $fillable = ['name', 'email', 'age', 'is_admin'];

    protected $casts = [
        'age'      => 'integer',
        'is_admin' => 'boolean',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
