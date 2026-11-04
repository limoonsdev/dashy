import { h, type Element, type Props } from './jsx.js';

export const Box = (props: Props): Element => h('box', props);
export const Text = (props: Props): Element => h('text', props);
export const Divider = (props: Props): Element => h('divider', props);
export const Spacer = (props: Props): Element => h('spacer', props);
export const Break = (props: Props): Element => h('break', props);
