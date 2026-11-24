<?php

namespace App\Enums;

enum ContentFormatType: string
{
    case PRODUCT_REVIEW = 'product_review';
    case PRODUCT_INTRODUCTION = 'product_introduction';
    case UNBOXING = 'unboxing';
    case PROBLEM_SOLUTION = 'problem_solution';
    case PRODUCT_COMPARISON = 'product_comparison';
    case TOP_RECOMMENDATIONS = 'top_recommendations';
    case EDUCATIONAL_CONTENT = 'educational_content';
    case PRODUCT_SHOWCASE = 'product_showcase';
    case FEATURES_BENEFITS = 'features_benefits';
    case DEAL_HIGHLIGHTS = 'deal_highlights';
    case BUYING_GUIDE = 'buying_guide';
    case STORYTELLING = 'storytelling';
    case FAQ = 'faq';
    case TUTORIAL = 'tutorial';

    public function label(): string
    {
        return match ($this) {
            self::PRODUCT_REVIEW => 'Product Review',
            self::PRODUCT_INTRODUCTION => 'Product Introduction',
            self::UNBOXING => 'Unboxing',
            self::PROBLEM_SOLUTION => 'Problem & Solution',
            self::PRODUCT_COMPARISON => 'Product Comparison',
            self::TOP_RECOMMENDATIONS => 'Top Recommendations',
            self::EDUCATIONAL_CONTENT => 'Educational Content',
            self::PRODUCT_SHOWCASE => 'Product Showcase',
            self::FEATURES_BENEFITS => 'Features & Benefits',
            self::DEAL_HIGHLIGHTS => 'Best Deal / Product Highlights',
            self::BUYING_GUIDE => 'Buying Guide',
            self::STORYTELLING => 'Storytelling',
            self::FAQ => 'Frequently Asked Questions',
            self::TUTORIAL => 'Tutorial / How to Use',
        };
    }

    public function recommendedTemplate(): VideoTemplateType
    {
        return match ($this) {
            self::PRODUCT_REVIEW => VideoTemplateType::PRODUCT_REVIEW,
            self::UNBOXING => VideoTemplateType::UNBOXING_STYLE,
            self::PROBLEM_SOLUTION => VideoTemplateType::PROBLEM_SOLUTION,
            self::PRODUCT_COMPARISON => VideoTemplateType::PRODUCT_COMPARISON,
            self::TOP_RECOMMENDATIONS => VideoTemplateType::TOP_RECOMMENDATIONS,
            self::EDUCATIONAL_CONTENT, self::BUYING_GUIDE, self::FAQ, self::TUTORIAL => VideoTemplateType::EDUCATIONAL_EXPLAINER,
            self::PRODUCT_SHOWCASE, self::PRODUCT_INTRODUCTION => VideoTemplateType::PRODUCT_SHOWCASE,
            self::FEATURES_BENEFITS => VideoTemplateType::PRODUCT_FEATURES,
            self::DEAL_HIGHLIGHTS => VideoTemplateType::DEAL_HIGHLIGHTS,
            self::STORYTELLING => VideoTemplateType::STORYTELLING,
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
