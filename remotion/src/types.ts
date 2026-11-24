export interface VideoSceneInput {
  id: number;
  order: number;
  title: string;
  overlayText: string;
  narrationSegment: string;
  durationSeconds: number;
  mediaPath: string | null;
  mediaType: 'image' | 'video' | 'none';
  transitionType: 'fade' | 'slide' | 'zoom' | 'cut';
  motionEffect: 'zoom_in' | 'zoom_out' | 'pan_left' | 'pan_right' | 'static';
  highlightKeywords: string[];
}

export interface SubtitleSegmentInput {
  id: number;
  order: number;
  startSeconds: number;
  endSeconds: number;
  text: string;
  highlightWords: string[];
}

export interface SubtitleStyleConfig {
  enabled: boolean;
  position: 'bottom' | 'center' | 'top';
  fontSize: number;
  highlightColor: string;
}

export interface CategoryThemeTokens {
  preset: string;
  accentColor: string;
  bgPrimary: string;
  bgSecondary: string;
  badgeBg: string;
  badgeText: string;
}

export const DEFAULT_CATEGORY_THEME: CategoryThemeTokens = {
  preset: 'neutral_studio',
  accentColor: '#6366F1',
  bgPrimary: '#0F1117',
  bgSecondary: '#1A1E29',
  badgeBg: '#EEF2FF',
  badgeText: '#312E81',
};

export type UniversalVideoProps = {
  [key: string]: unknown;
  title: string;
  category: string;
  subcategory?: string;
  productName?: string;
  priceText?: string;
  contentFormat?: string;
  experienceMode?: string;
  disclosureText?: string;
  brandName: string;
  brandTagline: string;
  ctaText: string;
  templateSlug: string;
  categoryTheme?: CategoryThemeTokens;
  fps: number;
  totalDurationSeconds: number;
  voiceoverPath: string | null;
  voiceoverVolume: number;
  bgMusicPath: string | null;
  bgMusicVolume: number;
  scenes: VideoSceneInput[];
  subtitles: SubtitleSegmentInput[];
  subtitleStyle: SubtitleStyleConfig;
};

// Backward-compatible type alias for legacy Phase 3 templates
export type PlantVideoProps = UniversalVideoProps;

export const resolveTheme = (props: UniversalVideoProps): CategoryThemeTokens => ({
  ...DEFAULT_CATEGORY_THEME,
  ...(props.categoryTheme || {}),
});

export const DEFAULT_VIDEO_PROPS: UniversalVideoProps = {
  title: 'Review Objektif Produk Pilihan',
  category: 'Electronics & Gadgets',
  subcategory: 'Audio & TWS',
  productName: '',
  priceText: '',
  contentFormat: 'product_showcase',
  experienceMode: 'research_based',
  disclosureText: '',
  brandName: '@plant.coaction',
  brandTagline: 'Universal Affiliate Studio',
  ctaText: 'Cek detail produk dan bandingkan sesuai kebutuhanmu.',
  templateSlug: 'product_showcase',
  categoryTheme: {
    preset: 'tech_dark',
    accentColor: '#38BDF8',
    bgPrimary: '#090D16',
    bgSecondary: '#111827',
    badgeBg: '#0EA5E9',
    badgeText: '#FFFFFF',
  },
  fps: 30,
  totalDurationSeconds: 15,
  voiceoverPath: null,
  voiceoverVolume: 1.0,
  bgMusicPath: null,
  bgMusicVolume: 0.15,
  scenes: [
    {
      id: 1,
      order: 1,
      title: 'Hook',
      overlayText: 'Hal penting yang wajib dicek sebelum memilih produk ini',
      narrationSegment: 'Hal penting yang wajib dicek sebelum memilih produk ini.',
      durationSeconds: 5,
      mediaPath: null,
      mediaType: 'none',
      transitionType: 'fade',
      motionEffect: 'zoom_in',
      highlightKeywords: ['produk', 'penting'],
    },
    {
      id: 2,
      order: 2,
      title: 'Fitur Utama',
      overlayText: 'Fokus pada fungsi praktis dan spesifikasi yang benar-benar terpakai',
      narrationSegment: 'Fokus pada fungsi praktis dan spesifikasi yang benar-benar terpakai.',
      durationSeconds: 5,
      mediaPath: null,
      mediaType: 'none',
      transitionType: 'slide',
      motionEffect: 'pan_right',
      highlightKeywords: ['fungsi', 'spesifikasi'],
    },
    {
      id: 3,
      order: 3,
      title: 'Kesimpulan',
      overlayText: 'Sesuaikan dengan kebutuhan harian dan anggaran sebelum membeli',
      narrationSegment: 'Sesuaikan dengan kebutuhan harian dan anggaran sebelum membeli.',
      durationSeconds: 5,
      mediaPath: null,
      mediaType: 'none',
      transitionType: 'fade',
      motionEffect: 'zoom_out',
      highlightKeywords: ['kebutuhan', 'membeli'],
    },
  ],
  subtitles: [
    { id: 1, order: 1, startSeconds: 0, endSeconds: 5, text: 'Hal penting sebelum memilih produk ini.', highlightWords: ['produk'] },
    { id: 2, order: 2, startSeconds: 5, endSeconds: 10, text: 'Fokus pada fungsi praktis dan spesifikasi nyata.', highlightWords: ['fungsi', 'spesifikasi'] },
    { id: 3, order: 3, startSeconds: 10, endSeconds: 15, text: 'Sesuaikan dengan kebutuhan harian sebelum membeli.', highlightWords: ['kebutuhan'] },
  ],
  subtitleStyle: {
    enabled: true,
    position: 'bottom',
    fontSize: 44,
    highlightColor: '#38BDF8',
  },
};
