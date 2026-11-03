export type QuestionStatus = 'draft' | 'review' | 'published' | 'rejected';

export type Difficulty = 'easy' | 'medium' | 'hard';

/** A page of a Laravel paginator, as Inertia receives it. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type AdminCategory = {
    id: number;
    name: string;
    color: string | null;
    /** Inactive categories aren't offered in games. */
    active: boolean;
};

/** One row of the question list. */
export type QuestionSummary = {
    id: number;
    text: string | null;
    category: string;
    category_color: string | null;
    /** The question's type, e.g. its Wikidata template; null when it has none. */
    kind: string | null;
    difficulty: Difficulty;
    status: QuestionStatus;
    source: string;
    missing_locales: string[];
    times_asked: number;
    correct_rate: number | null;
    /** Changed in the admin, so imports leave it alone. */
    edited: boolean;
};

export type QuestionWording = {
    text: string;
    options: string[];
    explanation: string | null;
    reviewed_by?: string | null;
    reviewed_at?: string | null;
};

export type QuestionDetail = {
    id: number;
    category_id: number;
    kind: string | null;
    difficulty: Difficulty;
    status: QuestionStatus;
    correct_index: number;
    source: string;
    source_ref: string | null;
    license: string | null;
    times_asked: number;
    correct_rate: number | null;
    edited_at: string | null;
    updated_at: string | null;
    can_delete: boolean;
    translations: Record<string, QuestionWording>;
};

/** What the filters and the question form can choose from. */
export type QuestionOptions = {
    categories: AdminCategory[];
    /** Question types in use. */
    kinds: string[];
    difficulties: Difficulty[];
    statuses: QuestionStatus[];
    sources: string[];
    locales: string[];
    optionCount: number;
};
