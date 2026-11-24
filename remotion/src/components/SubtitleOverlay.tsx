import React from 'react';
import { interpolate, spring, useCurrentFrame, useVideoConfig } from 'remotion';
import { SubtitleSegmentInput, SubtitleStyleConfig } from '../types';

interface SubtitleOverlayProps {
  subtitles: SubtitleSegmentInput[];
  styleConfig: SubtitleStyleConfig;
}

export const SubtitleOverlay: React.FC<SubtitleOverlayProps> = ({ subtitles, styleConfig }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  if (!styleConfig.enabled || !subtitles.length) {
    return null;
  }

  const currentSec = frame / fps;
  const active = subtitles.find((s) => currentSec >= s.startSeconds && currentSec < s.endSeconds);
  if (!active) {
    return null;
  }

  const segStartFrame = Math.floor(active.startSeconds * fps);
  const localFrame = Math.max(0, frame - segStartFrame);
  const pop = spring({ frame: localFrame, fps, config: { damping: 16, stiffness: 200 } });
  const translateY = interpolate(pop, [0, 1], [18, 0]);
  const opacity = interpolate(localFrame, [0, 5], [0, 1], { extrapolateRight: 'clamp' });

  const highlightSet = new Set(
    (active.highlightWords || []).map((w) => w.toLowerCase().replace(/[^a-z0-9]/gi, '')),
  );

  // Respect TikTok Safe Area: keep above caption/action bar (bottom >= 300px) and away from right icons (right >= 120px)
  const verticalStyle: React.CSSProperties =
    styleConfig.position === 'top'
      ? { top: 210 }
      : styleConfig.position === 'center'
        ? { top: '52%', transform: `translateY(calc(-50% + ${translateY}px))` }
        : { bottom: 320, transform: `translateY(${translateY}px)` };

  const words = active.text.split(/\s+/);

  return (
    <div
      style={{
        position: 'absolute',
        left: 56,
        right: 132,
        zIndex: 40,
        display: 'flex',
        justifyContent: 'center',
        opacity,
        ...verticalStyle,
      }}
    >
      <div
        style={{
          background: 'rgba(8, 17, 13, 0.80)',
          backdropFilter: 'blur(8px)',
          border: '1px solid rgba(255,255,255,0.16)',
          borderRadius: 22,
          padding: '18px 28px',
          textAlign: 'center',
          boxShadow: '0 14px 36px rgba(0,0,0,0.45)',
          maxWidth: '100%',
        }}
      >
        <p
          style={{
            margin: 0,
            fontFamily: "'Segoe UI', 'Inter', system-ui, sans-serif",
            fontSize: Math.max(28, Math.min(64, styleConfig.fontSize || 44)),
            lineHeight: 1.3,
            fontWeight: 800,
            color: '#F8FAFC',
            letterSpacing: '-0.01em',
            textShadow: '0 2px 10px rgba(0,0,0,0.65)',
          }}
        >
          {words.map((word, idx) => {
            const clean = word.toLowerCase().replace(/[^a-z0-9]/gi, '');
            const isHighlighted = clean !== '' && highlightSet.has(clean);
            return (
              <span
                key={`${idx}-${word}`}
                style={{
                  color: isHighlighted ? styleConfig.highlightColor || '#6EE7B7' : '#F8FAFC',
                }}
              >
                {word}
                {idx < words.length - 1 ? ' ' : ''}
              </span>
            );
          })}
        </p>
      </div>
    </div>
  );
};
