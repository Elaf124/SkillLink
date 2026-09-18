<?php

namespace App\Http\Controllers\Api\ServiceProvider;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories — public, no auth needed.
     * Used for browsing/dropdowns on the frontend.
     */
    public function index()
    {
        $categories = Category::whereNull('parent_category_id')
            ->with('children')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * View a single category with its services.
     */
    public function show(int $id)
    {
        $category = Category::with(['children', 'services' => function ($query) {
            $query->where('service_status', 'active');
        }])->find($id);

        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        return response()->json(['data' => $category]);
    }
}