import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const ExplainerScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const pop = spring({ frame, fps, config: { damping: 18, stiffness: 150 } });
  const progress = Math.min(100, ((index + frame / Math.max(1, durationFrames)) / Math.max(1, total)) * 100);

  return (
    <AbsoluteFill>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} />
      <AbsoluteFill style={{ background: `linear-gradient(180deg, ${theme.bgPrimary}D8 0%, rgba(0,0,0,0.22) 45%, ${theme.bgPrimary}EC 100%)` }} />

      {/* Top Reading Progress Bar */}
      <div style={{ position: 'absolute', top: 76, left: 56, right: 56, height: 8, borderRadius: 99, background: 'rgba(255,255,255,0.2)', overflow: 'hidden' }}>
        <div style={{ width: `${progress}%`, height: '100%', background: theme.accentColor, borderRadius: 99 }} />
      </div>

      {/* Category & Step Indicator */}
      <div style={{ position: 'absolute', top: 108, left: 56, right: 120, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ background: theme.badgeBg, color: theme.badgeText, padding: '8px 20px', borderRadius: 999, fontSize: 22, fontWeight: 800, fontFamily: 'Segoe UI, sans-serif' }}>
          EDUKASI · {props.category.toUpperCase()}
        </div>
        <div style={{ color: 'rgba(255,255,255,0.8)', fontSize: 22, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
          {index + 1} / {total}
        </div>
      </div>

      {/* Structured Explainer Card */}
      <div style={{ position: 'absolute', top: 230, left: 56, right: 132, transform: `translateY(${interpolate(pop, [0, 1], [28, 0])}px)`, opacity: pop }}>
        <div style={{ background: `${theme.bgPrimary}CC`, border: `1px solid ${theme.accentColor}66`, borderRadius: 24, padding: '26px 30px' }}>
          <div style={{ fontSize: 22, color: theme.accentColor, fontWeight: 800, letterSpacing: '0.06em', textTransform: 'uppercase', marginBottom: 10, fontFamily: 'Segoe UI, sans-serif' }}>
            {scene.title || `Langkah ${index + 1}`}
          </div>
          <div style={{ fontSize: 44, lineHeight: 1.2, fontWeight: 800, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif' }}>
            {scene.overlayText}
          </div>
        </div>
      </div>

      {index === total - 1 && props.ctaText && (
        <div style={{ position: 'absolute', bottom: 485, left: 56, right: 128 }}>
          <div style={{ background: theme.badgeBg, color: theme.badgeText, borderRadius: 16, padding: '16px 24px', fontSize: 26, fontWeight: 800, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.ctaText}
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};

export const EducationalExplainer: React.FC<UniversalVideoProps> = (props) => {
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
            <ExplainerScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack voiceoverPath={props.voiceoverPath} voiceoverVolume={props.voiceoverVolume} bgMusicPath={props.bgMusicPath} bgMusicVolume={props.bgMusicVolume} />
    </AbsoluteFill>
  );
};
