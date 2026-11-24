<?php

namespace App\Modules\ProductCatalog;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ProductCategoryService
{
    /**
     * Get all active root categories with their active subcategories.
     *
     * @return Collection<int, ProductCategory>
     */
    public function getTree(): Collection
    {
        return ProductCategory::query()
            ->roots()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->get();
    }

    /**
     * Resolve a category record by ID, slug, or name; falls back cleanly to Other / General if needed.
     */
    public function resolve(?int $categoryId = null, ?string $nameOrSlug = null): ?ProductCategory
    {
        if ($categoryId) {
            $found = ProductCategory::find($categoryId);
            if ($found) {
                return $found;
            }
        }

        if ($nameOrSlug && trim($nameOrSlug) !== '') {
            $needle = trim($nameOrSlug);
            $found = ProductCategory::where('slug', Str::slug($needle))
                ->orWhere('name', 'ilike', $needle)
                ->first();
            if ($found) {
                return $found;
            }
        }

        return ProductCategory::where('slug', 'other-general')->first();
    }

    /**
     * Create a new category or subcategory cleanly without changing code.
     */
    public function createCategory(
        string $name,
        ?int $parentId = null,
        ?string $themePreset = null,
        ?string $accentColor = null,
    ): ProductCategory {
        $parent = $parentId ? ProductCategory::find($parentId) : null;
        $baseSlug = $parent ? ($parent->slug . '-' . Str::slug($name)) : Str::slug($name);
        $slug = $baseSlug;
        $i = 2;
        while (ProductCategory::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        $preset = $themePreset
            ?: $parent?->theme_preset
            ?: ProductCategory::resolvePresetFromName($name);

        $tokens = ProductCategory::themeTokensForPreset($preset, $accentColor);

        return ProductCategory::create([
            'parent_id' => $parent?->id,
            'name' => trim($name),
            'slug' => $slug,
            'theme_preset' => $preset,
            'accent_color' => $tokens['accentColor'],
            'sort_order' => 200,
            'is_active' => true,
        ]);
    }
}
