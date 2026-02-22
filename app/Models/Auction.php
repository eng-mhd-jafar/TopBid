<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $fillable = [
        'product_id',
        'seller_id',
        'starting_price',
        'current_price',
        'min_increment',
        'start_time',
        'end_time',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}
