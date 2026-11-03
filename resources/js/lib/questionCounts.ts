import type { CategoryOption, QuestionDifficulty } from '@/types/room';

/** How many questions a category has in a language, at the given difficulties. */
export function questionCount(
    category: CategoryOption,
    locale: string,
    difficulties: readonly QuestionDifficulty[],
): number {
    const counts = category.questionCounts[locale] ?? {};

    return difficulties.reduce(
        (sum, difficulty) => sum + (counts[difficulty] ?? 0),
        0,
    );
}
