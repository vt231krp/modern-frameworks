<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GymClass extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * Note: "Class" is a reserved PHP keyword, so this model is named GymClass.
     */
    protected $table = 'classes';

    protected $primaryKey = 'class_id';

    public $timestamps = false;

    protected $fillable = [
        'title',
        'coach_id',
        'class_time',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'class_time' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class, 'coach_id', 'coach_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'registrations', 'class_id', 'member_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'class_id', 'class_id');
    }
}
