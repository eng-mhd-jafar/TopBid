<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    private $categoryRepository;


    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function storeCategories($data)
    {
        return $this->categoryRepository->store($data);
    }

    public function getAllCategories()
    {
        return $this->categoryRepository->index();
    }

    public function createCategory(array $data): Category
    {
        return $this->categoryRepository->createSingle($data);
    }

    public function updateCategory(int $id, array $data): Category
    {
        $category = $this->categoryRepository->findOrFail($id);

        return $this->categoryRepository->updateSingle($category, $data);
    }

    public function deleteCategory(int $id): void
    {
        $category = $this->categoryRepository->findOrFail($id);

        if ($this->categoryRepository->hasAuctions($category)) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has auctions.',
            ]);
        }

        $this->categoryRepository->delete($category);
    }
}
