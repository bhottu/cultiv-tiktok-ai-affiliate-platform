import React from 'react';
import { AbsoluteFill, Img, OffthreadVideo, interpolate, staticFile, useCurrentFrame } from 'remotion';
import { CategoryThemeTokens, DEFAULT_CATEGORY_THEME, VideoSceneInput } from '../types';

interface SceneMediaProps {
  scene: VideoSceneInput;
  durationFrames: number;
  accentColor?: string;
  theme?: CategoryThemeTokens;
  categoryLabel?: string;
  blurBackdrop?: boolean;
  frameInset?: boolean;
}

export const SceneMedia: React.FC<SceneMediaProps> = ({
  scene,
  durationFrames,
  accentColor,
  theme = DEFAULT_CATEGORY_THEME,
  categoryLabel,
  blurBackdrop = true,
  frameInset = false,
}) => {
  const frame = useCurrentFrame();
  const safeDuration = Math.max(1, durationFrames);
  const accent = accentColor || theme.accentColor;

  let scale = 1;
  let translateX = 0;
  let translateY = 0;

  if (scene.motionEffect === 'zoom_in') {
    scale = interpolate(frame, [0, safeDuration], [1.0, 1.12], { extrapolateRight: 'clamp' });
  } else if (scene.motionEffect === 'zoom_out') {
    scale = interpolate(frame, [0, safeDuration], [1.12, 1.0], { extrapolateRight: 'clamp' });
  } else if (scene.motionEffect === 'pan_left') {
    scale = 1.1;
    translateX = interpolate(frame, [0, safeDuration], [32, -32], { extrapolateRight: 'clamp' });
  } else if (scene.motionEffect === 'pan_right') {
    scale = 1.1;
    translateX = interpolate(frame, [0, safeDuration], [-32, 32], { extrapolateRight: 'clamp' });
  }

  const mediaSrc = scene.mediaPath ? staticFile(scene.mediaPath) : null;

  if (!mediaSrc || scene.mediaType === 'none') {
    // Clean category-adaptive studio backdrop (never forces plant/botanical visuals on non-plant products)
    const drift = interpolate(frame, [0, safeDuration], [0, 28], { extrapolateRight: 'clamp' });
    return (
      <AbsoluteFill
        style={{
          background: `radial-gradient(circle at ${22 + drift * 0.4}% 24%, ${accent}38 0%, transparent 54%), radial-gradient(circle at ${80 - drift * 0.3}% 76%, ${theme.bgSecondary}CC 0%, transparent 58%), linear-gradient(160deg, ${theme.bgPrimary} 0%, ${theme.bgSecondary} 52%, ${theme.bgPrimary} 100%)`,
        }}
      >
        <div
          style={{
            position: 'absolute',
            inset: 0,
            opacity: 0.12,
            backgroundImage: 'radial-gradient(rgba(255,255,255,0.45) 1px, transparent 1px)',
            backgroundSize: '38px 38px',
            transform: `translateY(${-drift}px)`,
          }}
        />
        {frameInset && (
          <div
            style={{
              position: 'absolute',
              top: 290,
              left: 72,
              right: 72,
              bottom: 500,
              borderRadius: 32,
              border: `2px dashed ${accent}66`,
              background: 'rgba(255,255,255,0.04)',
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              justifyContent: 'center',
              gap: 14,
              padding: 40,
            }}
          >
            <div style={{ fontSize: 22, letterSpacing: '0.12em', color: accent, fontWeight: 700, fontFamily: 'Segoe UI, sans-serif' }}>
              {(categoryLabel || 'VISUAL PRODUK').toUpperCase()}
            </div>
            <div style={{ fontSize: 30, color: 'rgba(255,255,255,0.68)', textAlign: 'center', fontFamily: 'Segoe UI, sans-serif', fontWeight: 600 }}>
              {scene.title || `Scene ${scene.order}`}
            </div>
          </div>
        )}
      </AbsoluteFill>
    );
  }

  const mediaStyle: React.CSSProperties = {
    width: '100%',
    height: '100%',
    objectFit: frameInset ? 'contain' : 'cover',
    transform: `scale(${scale}) translate(${translateX}px, ${translateY}px)`,
  };

  return (
    <AbsoluteFill style={{ backgroundColor: theme.bgPrimary, overflow: 'hidden' }}>
      {blurBackdrop && scene.mediaType === 'image' && (
        <AbsoluteFill style={{ filter: 'blur(28px) brightness(0.42)', transform: 'scale(1.18)' }}>
          <Img src={mediaSrc} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
        </AbsoluteFill>
      )}
      <AbsoluteFill style={frameInset ? { padding: '250px 64px 450px 64px' } : undefined}>
        <div
          style={{
            width: '100%',
            height: '100%',
            overflow: 'hidden',
            borderRadius: frameInset ? 32 : 0,
            boxShadow: frameInset ? '0 30px 70px rgba(0,0,0,0.55)' : 'none',
            border: frameInset ? `2px solid ${accent}44` : 'none',
          }}
        >
          {scene.mediaType === 'video' ? (
            <OffthreadVideo src={mediaSrc} muted style={mediaStyle} />
          ) : (
            <Img src={mediaSrc} style={mediaStyle} />
          )}
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
