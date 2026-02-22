<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'category_id',
        'description',
        'image_path',
        'specs',
    ];

    public function auction()
    {
        return $this->hasOne(Auction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
