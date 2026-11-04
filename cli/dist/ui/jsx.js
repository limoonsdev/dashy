/**
 * A tiny JSX runtime. There is no React and no third-party renderer: `h`
 * produces a plain element tree that `render.ts` turns into terminal lines.
 * That keeps `dashy` a single compiled file with no runtime dependencies.
 */
export const Fragment = 'fragment';
export function h(type, props, ...children) {
    const merged = { ...(props ?? {}) };
    if (children.length > 0) {
        merged.children = children.length === 1 ? children[0] : children;
    }
    return { kind: 'element', type, props: merged };
}
export const isElement = (value) => typeof value === 'object' &&
    value !== null &&
    value.kind === 'element';
export function childrenOf(props) {
    return props.children;
}
