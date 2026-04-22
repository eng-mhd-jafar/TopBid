<?php

namespace App\Services;

use App\Repositories\CategoryRepository;

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
}
