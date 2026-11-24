import React from 'react';
import { Composition, CalculateMetadataFunction } from 'remotion';
import { DealHighlights } from './templates/DealHighlights';
import { EducationalExplainer } from './templates/EducationalExplainer';
import { PlantEducation } from './templates/PlantEducation';
import { PlantShowcase } from './templates/PlantShowcase';
import { ProblemSolution } from './templates/ProblemSolution';
import { ProductComparison } from './templates/ProductComparison';
import { ProductFeatures } from './templates/ProductFeatures';
import { ProductReview } from './templates/ProductReview';
import { ProductShowcase } from './templates/ProductShowcase';
import { Storytelling } from './templates/Storytelling';
import { TopRecommendations } from './templates/TopRecommendations';
import { UnboxingStyle } from './templates/UnboxingStyle';
import { DEFAULT_VIDEO_PROPS, UniversalVideoProps } from './types';

const calculateDynamicMetadata: CalculateMetadataFunction<UniversalVideoProps> = ({ props }) => {
  const fps = props.fps || 30;
  const sceneSum = (props.scenes || []).reduce((acc, s) => acc + Math.max(0.5, Number(s.durationSeconds || 0)), 0);
  const totalSec = Math.max(3, sceneSum || props.totalDurationSeconds || 15);
  return {
    durationInFrames: Math.max(90, Math.round(totalSec * fps)),
    fps,
    width: 1080,
    height: 1920,
  };
};

const COMPOSITIONS: Array<{ id: string; slug: string; component: React.FC<UniversalVideoProps> }> = [
  // 10 Universal Templates (Phase 3.1)
  { id: 'ProductShowcase', slug: 'product_showcase', component: ProductShowcase },
  { id: 'ProductFeatures', slug: 'product_features', component: ProductFeatures },
  { id: 'ProductComparison', slug: 'product_comparison', component: ProductComparison },
  { id: 'TopRecommendations', slug: 'top_recommendations', component: TopRecommendations },
  { id: 'ProblemSolution', slug: 'problem_solution', component: ProblemSolution },
  { id: 'ProductReview', slug: 'product_review', component: ProductReview },
  { id: 'EducationalExplainer', slug: 'educational_explainer', component: EducationalExplainer },
  { id: 'UnboxingStyle', slug: 'unboxing_style', component: UnboxingStyle },
  { id: 'DealHighlights', slug: 'deal_highlights', component: DealHighlights },
  { id: 'Storytelling', slug: 'storytelling', component: Storytelling },
  // 2 Legacy Phase 3 Compositions (preserved for backward compatibility)
  { id: 'PlantEducation', slug: 'plant_education', component: PlantEducation },
  { id: 'PlantShowcase', slug: 'plant_showcase', component: PlantShowcase },
];

export const RemotionRoot: React.FC = () => {
  return (
    <>
      {COMPOSITIONS.map((item) => (
        <Composition
          key={item.id}
          id={item.id}
          component={item.component}
          width={1080}
          height={1920}
          fps={30}
          durationInFrames={450}
          defaultProps={{ ...DEFAULT_VIDEO_PROPS, templateSlug: item.slug }}
          calculateMetadata={calculateDynamicMetadata}
        />
      ))}
    </>
  );
};
