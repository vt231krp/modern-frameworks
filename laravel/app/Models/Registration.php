<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    use HasFactory;

    protected $table = 'registrations';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'class_id',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'member_id');
    }

    public function gymClass(): BelongsTo
    {
        return $this->belongsTo(GymClass::class, 'class_id', 'class_id');
    }
}
