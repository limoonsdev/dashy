/**
 * A tiny JSX runtime. There is no React and no third-party renderer: `h`
 * produces a plain element tree that `render.ts` turns into terminal lines.
 * That keeps `dashy` a single compiled file with no runtime dependencies.
 */

export type Props = Record<string, unknown> & { children?: Renderable };

export type Element = {
    readonly kind: 'element';
    readonly type: string | Component;
    readonly props: Props;
};

export type Component = (props: Props) => Renderable;

export type Renderable =
    | Element
    | string
    | number
    | boolean
    | null
    | undefined
    | Renderable[];

export const Fragment = 'fragment';

export function h(
    type: string | Component,
    props: Props | null,
    ...children: Renderable[]
): Element {
    const merged: Props = { ...(props ?? {}) };

    if (children.length > 0) {
        merged.children = children.length === 1 ? children[0] : children;
    }

    return { kind: 'element', type, props: merged };
}

export const isElement = (value: unknown): value is Element =>
    typeof value === 'object' &&
    value !== null &&
    (value as Element).kind === 'element';

export function childrenOf(props: Props): Renderable {
    return props.children as Renderable;
}
