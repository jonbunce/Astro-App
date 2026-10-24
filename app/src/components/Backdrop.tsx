import { Image, type ImageContentPosition } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { StyleSheet, View, useWindowDimensions } from 'react-native';

import { img, type Src } from '@/images';
import { colors } from '@/theme';

type Props = {
  /** Photo behind the top of the screen (grass, pitch, clubhouse wall, a team crest). */
  photo?: Src;
  height?: number;
  /** Which part of the photo to keep in view. */
  position?: ImageContentPosition;
  /** Crest backdrops sit to the right and fade out towards the title on the left. */
  fadeLeft?: boolean;
  /** Diagonal red wash from the top-left, as on the website's hero. */
  redWash?: boolean;
};

/** Floodlit background: a club photo fading into carbon, with red and gold glows. */
export function Backdrop({ photo, height = 320, position = 'center', fadeLeft, redWash = true }: Props) {
  const { width } = useWindowDimensions();
  return (
    <View pointerEvents="none" style={StyleSheet.absoluteFill}>
      {photo ? (
        <>
          <Image
            source={photo}
            contentFit="cover"
            contentPosition={position}
            style={{ position: 'absolute', top: 0, left: 0, width, height }}
            transition={250}
          />
          <LinearGradient
            colors={
              fadeLeft
                ? ['rgba(11,12,15,0.35)', 'rgba(11,12,15,0.12)', 'rgba(11,12,15,0.78)', colors.carbon]
                : ['rgba(11,12,15,0.58)', 'rgba(11,12,15,0.38)', 'rgba(11,12,15,0.82)', colors.carbon]
            }
            locations={[0, 0.35, 0.72, 1]}
            style={{ position: 'absolute', top: 0, left: 0, width, height: height + 2 }}
          />
          {fadeLeft ? (
            <LinearGradient
              colors={[colors.carbon, 'rgba(11,12,15,0.4)', 'rgba(11,12,15,0)']}
              locations={[0, 0.35, 0.7]}
              start={{ x: 0, y: 0.5 }}
              end={{ x: 1, y: 0.5 }}
              style={{ position: 'absolute', top: 0, left: 0, width, height }}
            />
          ) : null}
          {redWash ? (
            <LinearGradient
              colors={['rgba(200,16,46,0.34)', 'rgba(200,16,46,0)']}
              start={{ x: 0, y: 0 }}
              end={{ x: 0.6, y: 0.55 }}
              style={{ position: 'absolute', top: 0, left: 0, width, height }}
            />
          ) : null}
        </>
      ) : null}
      <Image source={img.glowGold} style={[styles.glow, { right: -200, top: height + 60, opacity: 0.7 }]} />
      <Image source={img.glowRed} style={[styles.glow, { left: -170, bottom: -230 }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  glow: { position: 'absolute', width: 440, height: 440 },
});
