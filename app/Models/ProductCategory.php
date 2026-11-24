<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'theme_preset',
        'accent_color',
        'description',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Resolve visual theme token config for Remotion & Editor stage based on category theme_preset.
     * Never forces botanical/plant visuals on non-plant categories.
     *
     * @return array{preset: string, accentColor: string, bgPrimary: string, bgSecondary: string, badgeBg: string, badgeText: string}
     */
    public function visualThemeTokens(): array
    {
        return self::themeTokensForPreset($this->theme_preset ?: 'neutral_studio', $this->accent_color);
    }

    /**
     * @return array{preset: string, accentColor: string, bgPrimary: string, bgSecondary: string, badgeBg: string, badgeText: string}
     */
    public static function themeTokensForPreset(string $preset, ?string $customAccent = null): array
    {
        $map = [
            'tech_dark' => [
                'preset' => 'tech_dark',
                'accentColor' => '#38BDF8',
                'bgPrimary' => '#090D16',
                'bgSecondary' => '#111827',
                'badgeBg' => '#0EA5E9',
                'badgeText' => '#FFFFFF',
            ],
            'fashion_editorial' => [
                'preset' => 'fashion_editorial',
                'accentColor' => '#E2B49A',
                'bgPrimary' => '#141214',
                'bgSecondary' => '#262124',
                'badgeBg' => '#F5EFEA',
                'badgeText' => '#1F1B1D',
            ],
            'beauty_soft' => [
                'preset' => 'beauty_soft',
                'accentColor' => '#F472B6',
                'bgPrimary' => '#1B1118',
                'bgSecondary' => '#2E1A27',
                'badgeBg' => '#FDF2F8',
                'badgeText' => '#831843',
            ],
            'health_clean' => [
                'preset' => 'health_clean',
                'accentColor' => '#2DD4BF',
                'bgPrimary' => '#0B161A',
                'bgSecondary' => '#12262D',
                'badgeBg' => '#CCFBF1',
                'badgeText' => '#115E59',
            ],
            'home_warm' => [
                'preset' => 'home_warm',
                'accentColor' => '#F59E0B',
                'bgPrimary' => '#181410',
                'bgSecondary' => '#292119',
                'badgeBg' => '#FEF3C7',
                'badgeText' => '#78350F',
            ],
            'auto_carbon' => [
                'preset' => 'auto_carbon',
                'accentColor' => '#EF4444',
                'bgPrimary' => '#0D0E12',
                'bgSecondary' => '#1C1E26',
                'badgeBg' => '#DC2626',
                'badgeText' => '#FFFFFF',
            ],
            'sports_energy' => [
                'preset' => 'sports_energy',
                'accentColor' => '#F97316',
                'bgPrimary' => '#0E1217',
                'bgSecondary' => '#1A232E',
                'badgeBg' => '#EA580C',
                'badgeText' => '#FFFFFF',
            ],
            'food_terracotta' => [
                'preset' => 'food_terracotta',
                'accentColor' => '#FB923C',
                'bgPrimary' => '#1C120D',
                'bgSecondary' => '#2E1D15',
                'badgeBg' => '#FFEDD5',
                'badgeText' => '#9A3412',
            ],
            'botanical_green' => [
                'preset' => 'botanical_green',
                'accentColor' => '#34D399',
                'bgPrimary' => '#07130E',
                'bgSecondary' => '#0E231A',
                'badgeBg' => '#065F46',
                'badgeText' => '#ECFDF5',
            ],
            'neutral_studio' => [
                'preset' => 'neutral_studio',
                'accentColor' => '#6366F1',
                'bgPrimary' => '#0F1117',
                'bgSecondary' => '#1A1E29',
                'badgeBg' => '#EEF2FF',
                'badgeText' => '#312E81',
            ],
        ];

        $tokens = $map[$preset] ?? $map['neutral_studio'];
        if ($customAccent && preg_match('/^#[0-9A-Fa-f]{6}$/', $customAccent)) {
            $tokens['accentColor'] = $customAccent;
        }

        return $tokens;
    }

    /**
     * Resolve theme preset intelligently from category name/slug even for legacy string categories.
     */
    public static function resolvePresetFromName(?string $categoryName): string
    {
        $lower = strtolower(trim((string) $categoryName));
        if ($lower === '') {
            return 'neutral_studio';
        }

        return match (true) {
            str_contains($lower, 'electronic'), str_contains($lower, 'gadget'), str_contains($lower, 'tech'), str_contains($lower, 'audio'), str_contains($lower, 'aksesoris hp') => 'tech_dark',
            str_contains($lower, 'fashion'), str_contains($lower, 'apparel'), str_contains($lower, 'pakaian'), str_contains($lower, 'sepatu'), str_contains($lower, 'tas') => 'fashion_editorial',
            str_contains($lower, 'beauty'), str_contains($lower, 'skincare'), str_contains($lower, 'makeup'), str_contains($lower, 'cosmetic') => 'beauty_soft',
            str_contains($lower, 'health'), str_contains($lower, 'personal care'), str_contains($lower, 'kesehatan') => 'health_clean',
            str_contains($lower, 'home'), str_contains($lower, 'living'), str_contains($lower, 'kitchen'), str_contains($lower, 'household'), str_contains($lower, 'rumah'), str_contains($lower, 'dapur') => 'home_warm',
            str_contains($lower, 'automotive'), str_contains($lower, 'otomotif'), str_contains($lower, 'motor'), str_contains($lower, 'mobil') => 'auto_carbon',
            str_contains($lower, 'sport'), str_contains($lower, 'outdoor'), str_contains($lower, 'olahraga') => 'sports_energy',
            str_contains($lower, 'food'), str_contains($lower, 'beverage'), str_contains($lower, 'makanan'), str_contains($lower, 'minuman') => 'food_terracotta',
            str_contains($lower, 'plant'), str_contains($lower, 'garden'), str_contains($lower, 'tanaman'), str_contains($lower, 'aroid'), str_contains($lower, 'anthurium') => 'botanical_green',
            default => 'neutral_studio',
        };
    }
}
