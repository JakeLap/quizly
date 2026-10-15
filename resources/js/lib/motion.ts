export function prefersReducedMotion(): boolean {
    return (
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
    );
}

/**
 * Animate an element with the Web Animations API, calling `done` when it
 * finishes, or once its time is up even if the browser never ran it.
 *
 * Browsers don't render frames for hidden or backgrounded tabs; animations
 * (and Vue's CSS transitions) that wait for frames would then leave content
 * stuck invisible or half-way. Here, once the time is up the animation is
 * jumped to its end, so the element always ends up in its final state.
 */
export function animate(
    element: Element,
    keyframes: Keyframe[],
    options: KeyframeAnimationOptions & { duration: number },
    done?: () => void,
): void {
    let finished = false;
    const finish = () => {
        if (!finished) {
            finished = true;
            done?.();
        }
    };

    if (prefersReducedMotion() || typeof element.animate !== 'function') {
        finish();

        return;
    }

    const animation = element.animate(keyframes, options);
    animation.finished.then(finish, finish);

    // Jump to the end rather than wait on frames that may never come.
    window.setTimeout(
        () => {
            if (!finished) {
                animation.finish();
                finish();
            }
        },
        options.duration + Number(options.delay ?? 0) + 100,
    );
}
