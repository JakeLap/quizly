import { Circle, Diamond, Square, Triangle } from '@lucide/vue';

/**
 * The four answer buttons. Each has a colour and a shape, so they can be
 * told apart without relying on colour alone.
 */
export const answerSlots = [
    { icon: Triangle, bg: 'bg-rose-500', ring: 'ring-rose-300' },
    { icon: Diamond, bg: 'bg-sky-500', ring: 'ring-sky-300' },
    { icon: Circle, bg: 'bg-amber-500', ring: 'ring-amber-200' },
    { icon: Square, bg: 'bg-emerald-500', ring: 'ring-emerald-300' },
] as const;
