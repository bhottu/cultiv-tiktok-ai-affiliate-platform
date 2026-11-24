import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const FeaturesScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const pop = spring({ frame, fps, config: { damping: 16, stiffness: 180 } });

  return (
    <AbsoluteFill>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} blurBackdrop frameInset />

      {/* Top Feature Header */}
      <div style={{ position: 'absolute', top: 102, left: 56, right: 120, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <div style={{ width: 54, height: 54, borderRadius: 14, background: theme.accentColor, color: '#090D16', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 26, fontWeight: 900, fontFamily: 'Segoe UI, sans-serif' }}>
            {String(index + 1).padStart(2, '0')}
          </div>
          <div>
            <div style={{ fontSize: 18, color: theme.accentColor, fontWeight: 800, letterSpacing: '0.08em', fontFamily: 'Segoe UI, sans-serif' }}>
              FITUR &amp; MANFAAT · {props.category.toUpperCase()}
            </div>
            <div style={{ fontSize: 26, color: '#FFFFFF', fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
              {scene.title || `Poin ${index + 1}`}
            </div>
          </div>
        </div>
      </div>

      {/* Spec/Feature Card */}
      <div
        style={{
          position: 'absolute',
          bottom: 470,
          left: 56,
          right: 128,
          transform: `scale(${interpolate(pop, [0, 1], [0.92, 1])})`,
        }}
      >
        <div
          style={{
            background: `${theme.bgPrimary}EB`,
            borderLeft: `8px solid ${theme.accentColor}`,
            borderRadius: 20,
            padding: '24px 28px',
            boxShadow: '0 20px 50px rgba(0,0,0,0.45)',
          }}
        >
          <div style={{ fontSize: 38, lineHeight: 1.24, fontWeight: 800, color: '#F8FAFC', fontFamily: 'Segoe UI, sans-serif' }}>
            {index === total - 1 && props.ctaText ? props.ctaText : scene.overlayText}
          </div>
          <div style={{ marginTop: 12, display: 'flex', gap: 10, flexWrap: 'wrap' }}>
            {(scene.highlightKeywords || []).slice(0, 3).map((kw, i) => (
              <span
                key={i}
                style={{
                  padding: '6px 14px',
                  borderRadius: 999,
                  background: `${theme.accentColor}26`,
                  border: `1px solid ${theme.accentColor}88`,
                  color: '#FFFFFF',
                  fontSize: 20,
                  fontWeight: 700,
                  fontFamily: 'Segoe UI, sans-serif',
                }}
              >
                {kw}
              </span>
            ))}
          </div>
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const ProductFeatures: React.FC<UniversalVideoProps> = (props) => {
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
            <FeaturesScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack voiceoverPath={props.voiceoverPath} voiceoverVolume={props.voiceoverVolume} bgMusicPath={props.bgMusicPath} bgMusicVolume={props.bgMusicVolume} />
    </AbsoluteFill>
  );
};
