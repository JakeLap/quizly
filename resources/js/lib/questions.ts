import type { Difficulty, QuestionStatus } from '@/types/admin';

/** UI strings (translated with t()) for the question admin's values. */
export const statusLabels: Record<QuestionStatus, string> = {
    published: 'Published',
    review: 'In review',
    draft: 'Draft',
    rejected: 'Rejected',
};

/** Badge colours per status, readable in light and dark mode. */
export const statusClasses: Record<QuestionStatus, string> = {
    published:
        'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    review: 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    draft: 'border-transparent bg-muted text-muted-foreground',
    rejected:
        'border-transparent bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
};

export const difficultyLabels: Record<Difficulty, string> = {
    easy: 'Easy',
    medium: 'Medium',
    hard: 'Hard',
};

export const sourceLabels: Record<string, string> = {
    manual: 'Written here',
    wikidata: 'Wikidata',
    ai: 'AI',
    opentdb: 'Open Trivia DB',
    community: 'Community',
};

export const localeNames: Record<string, string> = {
    el: 'Ελληνικά',
    en: 'English',
};

/** e.g. 0.583 → "58%". */
export function percent(rate: number | null): string {
    return rate === null ? '–' : `${Math.round(rate * 100)}%`;
}
