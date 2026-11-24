import React from 'react';
import { AbsoluteFill, Sequence, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { PlantVideoProps, VideoSceneInput } from '../types';

const ShowcaseScene: React.FC<{ scene: VideoSceneInput; durationFrames: number; brandName: string; brandTagline: string; isFirst: boolean }> = ({
  scene, durationFrames, brandName, brandTagline, isFirst,
}) => {
  const frame = useCurrentFrame();
  const fadeIn = interpolate(frame, [0, 14], [0, 1], { extrapolateRight: 'clamp' });
  const fadeOut = interpolate(frame, [Math.max(1, durationFrames - 12), durationFrames], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const drift = interpolate(frame, [0, Math.max(1, durationFrames)], [10, -10], { extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ opacity: Math.min(fadeIn, fadeOut) }}>
      <SceneMedia scene={scene} durationFrames={durationFrames} accentColor="#10B981" blurBackdrop={false} />
      {/* Subtle cinematic vignette */}
      <AbsoluteFill style={{ background: 'radial-gradient(circle at center, transparent 42%, rgba(4,12,9,0.72) 100%)' }} />

      {/* Minimal botanical watermark */}
      <div style={{ position: 'absolute', top: 118, left: 60, right: 120, display: 'flex', flexDirection: 'column', gap: 4 }}>
        <div style={{ fontSize: 28, fontWeight: 700, letterSpacing: '0.18em', textTransform: 'uppercase', color: 'rgba(236,253,245,0.92)', fontFamily: 'Georgia, serif' }}>
          {brandName}
        </div>
        <div style={{ fontSize: 18, letterSpacing: '0.12em', color: 'rgba(167,243,208,0.78)', fontFamily: 'Segoe UI, sans-serif' }}>
          {brandTagline}
        </div>
      </div>

      {/* Minimalist floating typography */}
      <div style={{ position: 'absolute', top: isFirst ? 320 : 280, left: 64, right: 132, transform: `translateY(${drift}px)` }}>
        <div style={{ width: 64, height: 3, background: '#6EE7B7', marginBottom: 18 }} />
        <div style={{ fontSize: isFirst ? 56 : 44, lineHeight: 1.18, fontWeight: 600, color: '#FFFFFF', fontFamily: 'Georgia, serif', textShadow: '0 6px 24px rgba(0,0,0,0.65)' }}>
          {scene.overlayText}
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const PlantShowcase: React.FC<PlantVideoProps> = (props) => {
  const { fps } = useVideoConfig();
  let cursor = 0;

  return (
    <AbsoluteFill style={{ backgroundColor: '#050E0A' }}>
      {props.scenes.map((scene, idx) => {
        const durFrames = Math.max(15, Math.round(scene.durationSeconds * fps));
        const from = cursor;
        cursor += durFrames;
        return (
          <Sequence key={scene.id} from={from} durationInFrames={durFrames}>
            <ShowcaseScene
              scene={scene}
              durationFrames={durFrames}
              brandName={props.brandName}
              brandTagline={props.brandTagline}
              isFirst={idx === 0}
            />
          </Sequence>
        );
      })}
      <SubtitleOverlay subtitles={props.subtitles} styleConfig={props.subtitleStyle} />
      <AudioTrack
        voiceoverPath={props.voiceoverPath}
        voiceoverVolume={props.voiceoverVolume}
        bgMusicPath={props.bgMusicPath}
        bgMusicVolume={props.bgMusicVolume}
      />
    </AbsoluteFill>
  );
};
