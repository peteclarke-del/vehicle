// Type declarations for packages without bundled types

declare module 'react-native-vector-icons/MaterialCommunityIcons' {
  import type {Component} from 'react';
  import type {TextStyle, ViewStyle} from 'react-native';

  interface IconProps {
    name: string;
    size?: number;
    color?: string;
    style?: TextStyle | ViewStyle;
  }

  export default class MaterialCommunityIcons extends Component<IconProps> {}
}
