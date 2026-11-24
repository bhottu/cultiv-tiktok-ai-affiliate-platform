import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const ComparisonScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const slide = spring({ frame, fps, config: { damping: 18, stiffness: 170 } });

  const badge = index === 0
    ? { label: 'PERBANDINGAN OBJEKTIF', color: theme.accentColor }
    : index === total - 1
      ? { label: 'PILIH SESUAI KEBUTUHAN', color: '#10B981' }
      : index % 2 === 1
        ? { label: `KELEBIHAN · POIN ${index}`, color: '#38BDF8' }
        : { label: `PERLU DIPERHATIKAN · POIN ${index}`, color: '#F59E0B' };

  return (
    <AbsoluteFill>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} blurBackdrop frameInset />

      <div style={{ position: 'absolute', top: 106, left: 56, right: 120, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ background: badge.color, color: '#090D16', padding: '10px 22px', borderRadius: 12, fontSize: 22, fontWeight: 900, letterSpacing: '0.06em', fontFamily: 'Segoe UI, sans-serif' }}>
          {badge.label}
        </div>
        <div style={{ color: 'rgba(255,255,255,0.78)', fontSize: 20, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
          {props.category}
        </div>
      </div>

      <div
        style={{
          position: 'absolute',
          bottom: 470,
          left: 56,
          right: 128,
          transform: `translateX(${interpolate(slide, [0, 1], [-48, 0])}px)`,
          opacity: slide,
        }}
      >
        <div style={{ background: 'rgba(10,14,24,0.90)', border: `2px solid ${badge.color}88`, borderRadius: 22, padding: '24px 28px' }}>
          <div style={{ fontSize: 20, color: badge.color, fontWeight: 800, marginBottom: 6, fontFamily: 'Segoe UI, sans-serif' }}>
            {scene.title || `Aspek ${index + 1}`}
          </div>
          <div style={{ fontSize: 38, lineHeight: 1.22, fontWeight: 800, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif' }}>
            {index === total - 1 && props.ctaText ? props.ctaText : scene.overlayText}
          </div>
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const ProductComparison: React.FC<UniversalVideoProps> = (props) => {
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
            <ComparisonScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack voiceoverPath={props.voiceoverPath} voiceoverVolume={props.voiceoverVolume} bgMusicPath={props.bgMusicPath} bgMusicVolume={props.bgMusicVolume} />
    </AbsoluteFill>
  );
};
