<?php

namespace App\Repositories;

use App\Models\Category;

class CategoryRepository
{
    public function store($data)
    {
        return Category::insert($data);
    }

    public function index()
    {
        return Category::all();
    }
}
