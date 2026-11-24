import React from 'react';
import { AbsoluteFill, Sequence, interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { AudioTrack } from '../components/AudioTrack';
import { SceneMedia } from '../components/SceneMedia';
import { SubtitleOverlay } from '../components/SubtitleOverlay';
import { PlantVideoProps, VideoSceneInput } from '../types';

const EducationScene: React.FC<{ scene: VideoSceneInput; index: number; total: number; durationFrames: number; brandName: string; ctaText: string }> = ({
  scene, index, total, durationFrames, brandName, ctaText,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = spring({ frame, fps, config: { damping: 18, stiffness: 150 } });
  const cardY = interpolate(enter, [0, 1], [36, 0]);
  const fadeOut = interpolate(frame, [Math.max(1, durationFrames - 8), durationFrames], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const isLast = index === total - 1;

  return (
    <AbsoluteFill style={{ opacity: fadeOut }}>
      <SceneMedia scene={scene} durationFrames={durationFrames} accentColor="#34D399" />
      <AbsoluteFill style={{ background: 'linear-gradient(180deg, rgba(6,20,14,0.72) 0%, rgba(6,20,14,0.18) 38%, rgba(6,20,14,0.82) 100%)' }} />

      {/* Top clean botanical progress bar & pill */}
      <div style={{ position: 'absolute', top: 112, left: 56, right: 120, display: 'flex', flexDirection: 'column', gap: 18 }}>
        <div style={{ display: 'flex', gap: 8 }}>
          {Array.from({ length: total }).map((_, i) => (
            <div key={i} style={{ flex: 1, height: 6, borderRadius: 99, background: 'rgba(255,255,255,0.22)', overflow: 'hidden' }}>
              <div
                style={{
                  height: '100%',
                  background: '#6EE7B7',
                  width: i < index ? '100%' : i === index ? `${Math.min(100, (frame / Math.max(1, durationFrames)) * 100)}%` : '0%',
                }}
              />
            </div>
          ))}
        </div>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: 10, padding: '10px 20px', borderRadius: 999, background: 'rgba(16, 48, 35, 0.85)', border: '1px solid rgba(110,231,183,0.4)', color: '#ECFDF5', fontSize: 24, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
            <span style={{ width: 10, height: 10, borderRadius: 99, background: '#34D399' }} />
            {brandName} · Edukasi
          </div>
          <div style={{ color: '#A7F3D0', fontSize: 22, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
            {String(index + 1).padStart(2, '0')} / {String(total).padStart(2, '0')}
          </div>
        </div>
      </div>

      {/* Editorial info card in upper-middle safe area */}
      <div style={{ position: 'absolute', top: 260, left: 56, right: 128, transform: `translateY(${cardY}px)`, opacity: enter }}>
        <div style={{ background: 'rgba(9, 24, 18, 0.72)', backdropFilter: 'blur(10px)', borderLeft: '6px solid #34D399', borderRadius: 24, padding: '28px 34px', boxShadow: '0 18px 40px rgba(0,0,0,0.35)' }}>
          <div style={{ fontSize: 22, letterSpacing: '0.12em', textTransform: 'uppercase', color: '#6EE7B7', fontWeight: 800, marginBottom: 10, fontFamily: 'Segoe UI, sans-serif' }}>
            {scene.title || `Poin ${index + 1}`}
          </div>
          <div style={{ fontSize: 46, lineHeight: 1.2, fontWeight: 800, color: '#FFFFFF', fontFamily: 'Segoe UI, sans-serif' }}>
            {scene.overlayText}
          </div>
          {scene.highlightKeywords?.length > 0 && (
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, marginTop: 18 }}>
              {scene.highlightKeywords.slice(0, 3).map((kw) => (
                <span key={kw} style={{ padding: '6px 16px', borderRadius: 999, background: 'rgba(52,211,153,0.2)', border: '1px solid rgba(110,231,183,0.45)', color: '#D1FAE5', fontSize: 22, fontWeight: 600, fontFamily: 'Segoe UI, sans-serif' }}>
                  #{kw}
                </span>
              ))}
            </div>
          )}
        </div>
      </div>

      {isLast && ctaText && (
        <div style={{ position: 'absolute', bottom: 485, left: 56, right: 128 }}>
          <div style={{ background: '#065F46', border: '1px solid #34D399', borderRadius: 18, padding: '16px 24px', color: '#ECFDF5', fontSize: 28, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
            {ctaText}
          </div>
        </div>
      )}
    </AbsoluteFill>
  );
};

export const PlantEducation: React.FC<PlantVideoProps> = (props) => {
  const { fps } = useVideoConfig();
  let cursor = 0;

  return (
    <AbsoluteFill style={{ backgroundColor: '#07130E' }}>
      {props.scenes.map((scene, idx) => {
        const durFrames = Math.max(15, Math.round(scene.durationSeconds * fps));
        const from = cursor;
        cursor += durFrames;
        return (
          <Sequence key={scene.id} from={from} durationInFrames={durFrames}>
            <EducationScene
              scene={scene}
              index={idx}
              total={props.scenes.length}
              durationFrames={durFrames}
              brandName={props.brandName}
              ctaText={props.ctaText}
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
