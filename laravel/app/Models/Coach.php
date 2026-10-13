<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coach extends Model
{
    use HasFactory;

    protected $primaryKey = 'coach_id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'phone',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(GymClass::class, 'coach_id', 'coach_id');
    }
}
