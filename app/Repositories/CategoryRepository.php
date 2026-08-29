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

    public function findOrFail(int $id): Category
    {
        return Category::findOrFail($id);
    }

    public function createSingle(array $data): Category
    {
        return Category::create($data);
    }

    public function updateSingle(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function hasAuctions(Category $category): bool
    {
        return $category->auctions()->exists();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
