import React from 'react';
import { AbsoluteFill, Sequence, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { CategoryThemeTokens, UniversalVideoProps, VideoSceneInput, resolveTheme } from '../types';

const StorytellingScene: React.FC<{
  scene: VideoSceneInput;
  index: number;
  durationFrames: number;
  props: UniversalVideoProps;
  theme: CategoryThemeTokens;
}> = ({ scene, index, durationFrames, props, theme }) => {
  const frame = useCurrentFrame();
  const opacity = Math.min(
    interpolate(frame, [0, 15], [0, 1], { extrapolateRight: 'clamp' }),
    interpolate(frame, [Math.max(1, durationFrames - 12), durationFrames], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }),
  );
  const textRise = interpolate(frame, [0, 22], [24, 0], { extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ opacity }}>
      <SceneMedia scene={scene} durationFrames={durationFrames} theme={theme} categoryLabel={props.category} />
      <AbsoluteFill style={{ background: `linear-gradient(180deg, ${theme.bgPrimary}CC 0%, rgba(16,14,18,0.22) 48%, ${theme.bgPrimary}EC 100%)` }} />

      {/* Chapter badge — dynamic category, zero plant hardcoding */}
      <div style={{ position: 'absolute', top: 120, left: 60, right: 120, display: 'flex', alignItems: 'center', gap: 14 }}>
        <div style={{ padding: '6px 16px', borderRadius: 999, border: `1px solid ${theme.accentColor}AA`, color: theme.accentColor, fontSize: 20, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
          BAB {String(index + 1).padStart(2, '0')}
        </div>
        <div style={{ fontSize: 22, color: 'rgba(255,255,255,0.82)', fontFamily: 'Georgia, serif' }}>
          {props.brandName} · {props.category}
        </div>
      </div>

      {/* Narrative headline */}
      <div style={{ position: 'absolute', top: 240, left: 60, right: 130, transform: `translateY(${textRise}px)` }}>
        {index === 0 && (
          <div style={{ fontSize: 24, color: theme.accentColor, fontWeight: 700, marginBottom: 10, fontFamily: 'Segoe UI, sans-serif' }}>
            {props.title}
          </div>
        )}
        <div style={{ fontSize: 48, lineHeight: 1.24, fontStyle: 'italic', color: '#F8FAFC', fontFamily: 'Georgia, serif', textShadow: '0 4px 18px rgba(0,0,0,0.65)' }}>
          “{scene.overlayText}”
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const Storytelling: React.FC<UniversalVideoProps> = (props) => {
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
            <StorytellingScene scene={scene} index={idx} durationFrames={durFrames} props={props} theme={theme} />
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
