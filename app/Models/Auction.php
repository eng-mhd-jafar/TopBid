<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $fillable = [
        'category_id',
        'user_id',
        'title',
        'description',
        'image_path',
        'specs',
        'starting_price',
        'current_price',
        'duration_hours',
        'is_active',
        'moderation_status',
        'started_at',
        'expires_at',
        'end_at',

    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    protected $casts = [
        'specs' => 'array',
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('moderation_status', 'approved')
            ->where('end_at', '>', now());
    }
}
