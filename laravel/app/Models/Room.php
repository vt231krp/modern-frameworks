<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $primaryKey = 'room_id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'capacity',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'room_id', 'room_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(GymClass::class, 'room_id', 'room_id');
    }
}
