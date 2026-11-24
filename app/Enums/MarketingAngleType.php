<?php

namespace App\Enums;

enum MarketingAngleType: string
{
    case AUTO = 'auto';
    case PROBLEM_SOLVING = 'problem_solving';
    case FEATURE_FOCUSED = 'feature_focused';
    case PRICE_VALUE = 'price_value';
    case COMPARISON = 'comparison';
    case CONVENIENCE = 'convenience';
    case EDUCATIONAL = 'educational';
    case CURIOSITY = 'curiosity';
    case GIFT_RECOMMENDATION = 'gift_recommendation';
    case LIFESTYLE = 'lifestyle';
    case PRACTICAL_DEMO = 'practical_demonstration';

    public function label(): string
    {
        return match ($this) {
            self::AUTO => 'Otomatis (Dipilih AI sesuai produk)',
            self::PROBLEM_SOLVING => 'Problem-Solving Angle',
            self::FEATURE_FOCUSED => 'Feature-Focused Angle',
            self::PRICE_VALUE => 'Price-Value Angle',
            self::COMPARISON => 'Comparison Angle',
            self::CONVENIENCE => 'Convenience Angle',
            self::EDUCATIONAL => 'Educational Angle',
            self::CURIOSITY => 'Curiosity Angle',
            self::GIFT_RECOMMENDATION => 'Gift Recommendation Angle',
            self::LIFESTYLE => 'Lifestyle Angle',
            self::PRACTICAL_DEMO => 'Practical Demonstration Angle',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
