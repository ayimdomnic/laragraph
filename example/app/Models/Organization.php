<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'address',
        'phone',
        'city',
        'state',
        'country',
        'zip_code',
        'description',
        'status',
        'settings',
        'type',
        'parent_id',
        'owner_id',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return OrganizationFactory::new();
    }

    public function parent()
    {
        return $this->belongsTo(Organization::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Organization::class, 'parent_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function members()
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
