import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const TopRecScene: React.FC<{
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
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} />
      <AbsoluteFill style={{ background: `linear-gradient(180deg, ${theme.bgPrimary}D8 0%, rgba(0,0,0,0.22) 48%, ${theme.bgPrimary}E8 100%)` }} />

      {/* Ranked Pick Badge */}
      <div style={{ position: 'absolute', top: 118, left: 56, right: 128, transform: `scale(${interpolate(pop, [0, 1], [0.85, 1])})`, transformOrigin: 'left top' }}>
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 14, background: theme.badgeBg, color: theme.badgeText, padding: '12px 24px', borderRadius: 16, fontSize: 26, fontWeight: 900, fontFamily: 'Segoe UI, sans-serif' }}>
          <span>#{String(index + 1).padStart(2, '0')}</span>
          <span>·</span>
          <span>{index === 0 ? 'TOP REKOMENDASI' : scene.title.toUpperCase()}</span>
        </div>
        <div style={{ marginTop: 18, fontSize: 48, lineHeight: 1.16, fontWeight: 900, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif', textShadow: '0 4px 22px rgba(0,0,0,0.7)' }}>
          {scene.overlayText}
        </div>
      </div>

      {index === total - 1 && props.ctaText && (
        <div style={{ position: 'absolute', bottom: 485, left: 56, right: 128 }}>
          <div style={{ background: theme.accentColor, color: '#090D16', borderRadius: 18, padding: '18px 24px', fontSize: 28, fontWeight: 900, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.ctaText}
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};

export const TopRecommendations: React.FC<UniversalVideoProps> = (props) => {
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
            <TopRecScene scene={scene} index={idx} total={props.scenes.length} durationFrames={durFrames} props={props} theme={theme} />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={{ ...props.subtitleStyle, highlightColor: theme.accentColor }} />
      <AudioTrack voiceoverPath={props.voiceoverPath} voiceoverVolume={props.voiceoverVolume} bgMusicPath={props.bgMusicPath} bgMusicVolume={props.bgMusicVolume} />
    </AbsoluteFill>
  );
};
