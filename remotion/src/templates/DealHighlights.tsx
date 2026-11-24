import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const DealScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const pop = spring({ frame, fps, config: { damping: 14, stiffness: 200 } });

  return (
    <AbsoluteFill>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} blurBackdrop frameInset />

      {/* Top Value Banner — Price is ONLY displayed when genuine product priceText is provided */}
      <div style={{ position: 'absolute', top: 102, left: 56, right: 120, display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
        <div style={{ background: theme.accentColor, color: '#090D16', padding: '10px 22px', borderRadius: 12, fontSize: 22, fontWeight: 900, letterSpacing: '0.06em', fontFamily: 'Segoe UI, sans-serif' }}>
          PRODUCT HIGHLIGHTS · {props.category.toUpperCase()}
        </div>
        {props.priceText ? (
          <div style={{ background: '#FFFFFF', color: '#0F172A', padding: '10px 20px', borderRadius: 12, fontSize: 24, fontWeight: 900, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.priceText}
          </div>
        ) : null}
      </div>

      {/* Highlight Card */}
      <div style={{ position: 'absolute', bottom: 472, left: 56, right: 128, transform: `scale(${interpolate(pop, [0, 1], [0.9, 1])})` }}>
        <div style={{ background: `${theme.bgPrimary}F0`, border: `2px solid ${theme.accentColor}`, borderRadius: 22, padding: '24px 28px', boxShadow: '0 22px 50px rgba(0,0,0,0.5)' }}>
          <div style={{ fontSize: 20, color: theme.accentColor, fontWeight: 800, textTransform: 'uppercase', marginBottom: 6, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.productName ? `${props.productName} · ${scene.title}` : scene.title || `Nilai Utama ${index + 1}`}
          </div>
          <div style={{ fontSize: 40, lineHeight: 1.2, fontWeight: 900, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif' }}>
            {index === total - 1 && props.ctaText ? props.ctaText : scene.overlayText}
          </div>
          {props.disclosureText && (
            <div style={{ marginTop: 10, fontSize: 19, color: 'rgba(255,255,255,0.72)', fontFamily: 'Segoe UI, sans-serif' }}>
              {props.disclosureText}
            </div>
          )}
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const DealHighlights: React.FC<UniversalVideoProps> = (props) => {
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
            <DealScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack voiceoverPath={props.voiceoverPath} voiceoverVolume={props.voiceoverVolume} bgMusicPath={props.bgMusicPath} bgMusicVolume={props.bgMusicVolume} />
    </AbsoluteFill>
  );
};
