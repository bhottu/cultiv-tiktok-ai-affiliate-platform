import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const UnboxingScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  total: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, total, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = spring({ frame, fps, config: { damping: 16, stiffness: 180 } });
  const stageLabels = ['KEMASAN & PAKET', 'ISI DALAM KOTAK', 'DETAIL PRODUK', 'KESAN & CATATAN'];
  const stageLabel = stageLabels[Math.min(index, stageLabels.length - 1)];

  return (
    <AbsoluteFill>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} blurBackdrop frameInset />

      {/* Top Unboxing Step Pill */}
      <div style={{ position: 'absolute', top: 104, left: 58, right: 120, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div style={{ background: 'rgba(255,255,255,0.95)', color: '#0F172A', padding: '10px 22px', borderRadius: 12, fontSize: 22, fontWeight: 900, letterSpacing: '0.06em', fontFamily: 'Segoe UI, sans-serif' }}>
          UNBOXING · {stageLabel}
        </div>
        <div style={{ color: theme.accentColor, fontSize: 22, fontWeight: 800, fontFamily: 'Segoe UI, sans-serif' }}>
          {props.category}
        </div>
      </div>

      {/* Bottom Unboxing Note Card */}
      <div style={{ position: 'absolute', bottom: 472, left: 58, right: 128, transform: `translateY(${interpolate(enter, [0, 1], [34, 0])}px)`, opacity: enter }}>
        <div style={{ background: `${theme.bgPrimary}E8`, border: `2px solid ${theme.accentColor}77`, borderRadius: 20, padding: '22px 26px' }}>
          <div style={{ fontSize: 20, color: theme.accentColor, fontWeight: 800, marginBottom: 6, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.productName || scene.title || `Tahap ${index + 1}`}
          </div>
          <div style={{ fontSize: 38, lineHeight: 1.22, fontWeight: 700, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif' }}>
            {index === total - 1 && props.ctaText ? props.ctaText : scene.overlayText}
          </div>
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const UnboxingStyle: React.FC<UniversalVideoProps> = (props) => {
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
            <UnboxingScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack voiceoverPath={props.voiceoverPath} voiceoverVolume={props.voiceoverVolume} bgMusicPath={props.bgMusicPath} bgMusicVolume={props.bgMusicVolume} />
    </AbsoluteFill>
  );
};
