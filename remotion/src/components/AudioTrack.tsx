import React from 'react';
import { Audio, interpolate, staticFile, useVideoConfig } from 'remotion';

interface AudioTrackProps {
  voiceoverPath: string | null;
  voiceoverVolume: number;
  bgMusicPath: string | null;
  bgMusicVolume: number;
}

export const AudioTrack: React.FC<AudioTrackProps> = ({
  voiceoverPath,
  voiceoverVolume,
  bgMusicPath,
  bgMusicVolume,
}) => {
  const { durationInFrames, fps } = useVideoConfig();
  const fadeFrames = Math.min(Math.round(fps * 0.4), Math.floor(durationInFrames / 4));

  const duckedMusicVolume = voiceoverPath
    ? Math.min(0.22, Math.max(0.05, bgMusicVolume * 0.55))
    : Math.min(0.5, Math.max(0.05, bgMusicVolume));

  return (
    <>
      {voiceoverPath && (
        <Audio
          src={staticFile(voiceoverPath)}
          volume={(f) => {
            const fadeIn = interpolate(f, [0, Math.max(1, fadeFrames)], [0, 1], { extrapolateRight: 'clamp' });
            const fadeOut = interpolate(
              f,
              [Math.max(1, durationInFrames - fadeFrames), durationInFrames],
              [1, 0],
              { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' },
            );
            return Math.min(1.5, Math.max(0.1, voiceoverVolume)) * Math.min(fadeIn, fadeOut);
          }}
        />
      )}
      {bgMusicPath && (
        <Audio
          src={staticFile(bgMusicPath)}
          loop
          volume={(f) => {
            const fadeIn = interpolate(f, [0, Math.max(1, fadeFrames * 2)], [0, 1], { extrapolateRight: 'clamp' });
            const fadeOut = interpolate(
              f,
              [Math.max(1, durationInFrames - fadeFrames * 2), durationInFrames],
              [1, 0],
              { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' },
            );
            return duckedMusicVolume * Math.min(fadeIn, fadeOut);
          }}
        />
      )}
    </>
  );
};
