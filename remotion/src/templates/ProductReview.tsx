import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const ReviewScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const slideIn = spring({ frame, fps, config: { damping: 20, stiffness: 170 } });
  const xOffset = interpolate(slideIn, [0, 1], [-60, 0]);
  const modeLabel = props.experienceMode === 'first_hand' ? 'REVIEW PENGALAMAN' : 'ULASAN BERBASIS DATA';

  return (
    <AbsoluteFill>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} blurBackdrop frameInset />

      {/* Top Review Header */}
      <div style={{ position: 'absolute', top: 108, left: 60, right: 120, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ background: theme.badgeBg, color: theme.badgeText, padding: '10px 22px', borderRadius: 12, fontSize: 22, fontWeight: 800, letterSpacing: '0.04em', fontFamily: 'Segoe UI, sans-serif', boxShadow: '0 8px 20px rgba(0,0,0,0.35)' }}>
          {modeLabel} · {props.category.toUpperCase()}
        </div>
        <div style={{ color: theme.accentColor, fontSize: 22, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>{props.brandName}</div>
      </div>

      {/* Kinetic callout band below product frame */}
      <div style={{ position: 'absolute', bottom: 470, left: 60, right: 128, transform: `translateX(${xOffset}px)`, opacity: slideIn }}>
        <div style={{ background: `${theme.bgPrimary}EB`, border: `1px solid ${theme.accentColor}77`, borderRadius: 20, padding: '22px 28px' }}>
          <div style={{ fontSize: 20, color: theme.accentColor, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 6, fontFamily: 'Segoe UI, sans-serif' }}>
            {index === total - 1 ? 'REKOMENDASI & CATATAN' : `DETAIL ${index + 1}: ${scene.title}`}
          </div>
          <div style={{ fontSize: 38, lineHeight: 1.24, fontWeight: 700, color: '#F8FAFC', fontFamily: 'Segoe UI, sans-serif' }}>
            {index === total - 1 && props.ctaText ? props.ctaText : scene.overlayText}
          </div>
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const ProductReview: React.FC<UniversalVideoProps> = (props) => {
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
            <ReviewScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack
        voiceoverPath={props.voiceoverPath}
        voiceoverVolume={props.voiceoverVolume}
        bgMusicPath={props.bgMusicPath}
        bgMusicVolume={props.bgMusicVolume}
      />
    </AbsoluteFill>
  );
};
