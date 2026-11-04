/**
 * The JSX namespace for the custom `h` factory. This file has no imports or
 * exports on purpose: as a script, its declarations are global.
 */
declare namespace JSX {
    type Element = import('./ui/jsx.js').Element;

    interface ElementChildrenAttribute {
        children: unknown;
    }

    interface IntrinsicElements {
        [elementName: string]: Record<string, unknown>;
    }
}
