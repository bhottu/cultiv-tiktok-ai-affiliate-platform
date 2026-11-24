import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const ShowcaseScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = spring({ frame, fps, config: { damping: 20, stiffness: 150 } });
  const fade = interpolate(frame, [0, 12], [0, 1], { extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ opacity: fade }}>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} />
      <AbsoluteFill
        style={{
          background: `linear-gradient(180deg, ${theme.bgPrimary}CC 0%, rgba(0,0,0,0.12) 38%, rgba(0,0,0,0.22) 62%, ${theme.bgPrimary}EE 100%)`,
        }}
      />

      {/* Top Editorial Category Bar */}
      <div style={{ position: 'absolute', top: 108, left: 58, right: 120, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div
          style={{
            background: theme.badgeBg,
            color: theme.badgeText,
            padding: '10px 22px',
            borderRadius: 999,
            fontSize: 22,
            fontWeight: 800,
            letterSpacing: '0.06em',
            fontFamily: 'Segoe UI, sans-serif',
          }}
        >
          {props.category.toUpperCase()}
        </div>
        <div style={{ color: 'rgba(255,255,255,0.85)', fontSize: 22, fontWeight: 600, fontFamily: 'Segoe UI, sans-serif' }}>
          {props.brandName}
        </div>
      </div>

      {/* Product Headline & Scene Overlay */}
      <div
        style={{
          position: 'absolute',
          bottom: 475,
          left: 58,
          right: 132,
          transform: `translateY(${interpolate(enter, [0, 1], [32, 0])}px)`,
        }}
      >
        <div style={{ fontSize: 20, color: theme.accentColor, fontWeight: 800, letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: 8, fontFamily: 'Segoe UI, sans-serif' }}>
          {props.productName ? props.productName : `SOROTAN ${index + 1}/${total}`}
        </div>
        <div style={{ fontSize: 48, lineHeight: 1.16, fontWeight: 800, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif', textShadow: '0 4px 20px rgba(0,0,0,0.65)' }}>
          {index === total - 1 && props.ctaText ? props.ctaText : scene.overlayText}
        </div>
        {props.disclosureText && index === total - 1 && (
          <div style={{ marginTop: 12, fontSize: 20, color: 'rgba(255,255,255,0.72)', fontFamily: 'Segoe UI, sans-serif' }}>
            {props.disclosureText}
          </div>
        )}
      </div>
    </AbsoluteFill>
  );
};

export const ProductShowcase: React.FC<UniversalVideoProps> = (props) => {
  const { fps } = useVideoConfig();
  const theme = resolveTheme(props);
  let cursor = 0;

  return (
    <AbsoluteFill style={{ backgroundColor: theme.bgPrimary }}>
      {props.scenes.map((scene, idx) => {
        const durFrames = Math.max(15, Math.round(scene.durationSeconds * fps));
        const from = cursor;
        cursor += durFrames;
        return (
          <Sequence key={scene.id} from={from} durationInFrames={durFrames}>
            <ShowcaseScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack voiceoverPath={props.voiceoverPath} voiceoverVolume={props.voiceoverVolume} bgMusicPath={props.bgMusicPath} bgMusicVolume={props.bgMusicVolume} />
    </AbsoluteFill>
  );
};
