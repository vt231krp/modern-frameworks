<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $primaryKey = 'member_id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'phone',
        'join_date',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'member_id', 'member_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(GymClass::class, 'registrations', 'member_id', 'class_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'member_id', 'member_id');
    }
}
