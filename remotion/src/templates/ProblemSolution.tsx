import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const ProblemSolutionScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const pop = spring({ frame, fps, config: { damping: 14, stiffness: 220 } });
  const scale = interpolate(pop, [0, 1], [0.88, 1]);

  const stage = index === 0
    ? { label: 'MASALAH', bg: '#DC2626' }
    : index === 1
      ? { label: 'PENYEBAB / KENAPA', bg: '#D97706' }
      : index === total - 1
        ? { label: 'SOLUSI & CTA', bg: theme.accentColor }
        : { label: 'SOLUSI', bg: '#0EA5E9' };

  return (
    <AbsoluteFill>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} accentColor={stage.bg} categoryLabel={props.category} />
      <AbsoluteFill style={{ background: `linear-gradient(180deg, ${theme.bgPrimary}D8 0%, rgba(12,10,14,0.28) 45%, ${theme.bgPrimary}EC 100%)` }} />

      {/* Bold kinetic stage banner */}
      <div style={{ position: 'absolute', top: 132, left: 56, right: 128, transform: `scale(${scale})`, transformOrigin: 'left top' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
          <div style={{ display: 'inline-block', background: stage.bg, color: '#FFFFFF', padding: '12px 26px', borderRadius: 14, fontSize: 26, fontWeight: 900, letterSpacing: '0.1em', fontFamily: 'Segoe UI, sans-serif', boxShadow: '0 10px 26px rgba(0,0,0,0.45)' }}>
            {stage.label}
          </div>
          <div style={{ fontSize: 20, color: 'rgba(255,255,255,0.8)', fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.category}
          </div>
        </div>
        <div style={{ marginTop: 22, fontSize: 52, lineHeight: 1.15, fontWeight: 900, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif', textShadow: '0 4px 20px rgba(0,0,0,0.7)' }}>
          {scene.overlayText}
        </div>
      </div>

      {index === total - 1 && props.ctaText && (
        <div style={{ position: 'absolute', bottom: 485, left: 56, right: 128 }}>
          <div style={{ background: theme.badgeBg, color: theme.badgeText, borderRadius: 18, padding: '18px 24px', fontSize: 28, fontWeight: 800, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.ctaText}
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};

export const ProblemSolution: React.FC<UniversalVideoProps> = (props) => {
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
            <ProblemSolutionScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
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
