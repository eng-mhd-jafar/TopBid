<?php

namespace App\Repositories;

use App\Models\Category;

class CategoryRepository
{
    public function store(array $data): bool
    {
        $now = now();

        return Category::insert(array_map(fn (array $row) => [
            'name' => $row['name'],
            'slug' => $row['slug'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $data));
    }

    public function index()
    {
        return Category::all();
    }
}
