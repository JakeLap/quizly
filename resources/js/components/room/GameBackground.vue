<template>
    <div class="relative min-h-dvh bg-indigo-950 text-white">
        <!-- Decoration only. Fixed to the screen, so it doesn't move when the
             page's height changes, and clipped in its own layer, which holds
             nothing focusable, so focusing a button can't scroll sideways. -->
        <div
            class="pointer-events-none fixed inset-0 overflow-clip bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-900"
            aria-hidden="true"
        >
            <div
                class="absolute -top-40 -left-40 size-[32rem] rounded-full bg-fuchsia-500/20 blur-3xl"
            />
            <div
                class="absolute -right-40 -bottom-40 size-[32rem] rounded-full bg-sky-500/20 blur-3xl"
            />
        </div>
        <div class="relative">
            <slot />
        </div>
    </div>
</template>
