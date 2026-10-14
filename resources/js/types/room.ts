export type RoomStatus =
    | 'lobby'
    | 'intro'
    | 'question'
    | 'reveal'
    | 'scoreboard'
    | 'finished'
    | 'abandoned';

export type RoomSettings = {
    rounds: number;
    seconds_per_round: number;
    max_players: number;
};

export type RoomCategory = {
    id: number;
    slug: string;
    name: string;
    icon: string | null;
    color: string | null;
};

export type RoomPlayer = {
    id: number;
    nickname: string;
    color: string;
    isVip: boolean;
    score: number;
};

export type RoundResult = {
    playerId: number;
    /** The answer button the player chose, or null if they didn't answer. */
    slot: number | null;
    correct: boolean;
    points: number;
    responseMs: number | null;
};

/** Only present once the round is over. */
export type RoundReveal = {
    correctSlot: number;
    explanation: string | null;
    /** How many players chose each answer button. */
    counts: number[];
    results: RoundResult[];
};

export type RoomRound = {
    number: number;
    total: number;
    category: RoomCategory;
    question: string;
    /** Answer texts in display order (one per button). */
    options: string[];
    /** Server timestamps in ms. */
    shownAt: number;
    opensAt: number;
    endsAt: number;
    /** Players who have answered (not what they answered). */
    answered: number[];
    reveal: RoundReveal | null;
};

export type Standing = {
    playerId: number;
    rank: number;
    score: number;
    correct: number;
    correctMs: number;
};

/** The public room snapshot sent by the server (App\Support\RoomState). */
export type RoomState = {
    code: string;
    status: RoomStatus;
    locale: string;
    version: number;
    settings: RoomSettings;
    categories: RoomCategory[];
    players: RoomPlayer[];
    round: RoomRound | null;
    standings: Standing[] | null;
    /**
     * When the current phase ends (server ms), if it ends on a timer. While
     * paused: when the room closes unless the screen comes back.
     */
    nextAt: number | null;
    /** The host screen went away; the game is frozen until it's back. */
    paused: boolean;
    serverNow: number;
};

/** A category the host can pick, with names and question counts per language. */
export type CategoryOption = {
    id: number;
    slug: string;
    icon: string | null;
    color: string | null;
    names: Record<string, string>;
    questionCounts: Record<string, number>;
};

export type Range = { min: number; max: number; default: number };

/** The info each member shares on the room's presence channel. */
export type RoomMember = {
    id: string;
    role: 'host' | 'player';
    playerId?: number;
};
