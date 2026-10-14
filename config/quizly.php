<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Locales
    |--------------------------------------------------------------------------
    |
    | The languages questions and categories can be written in. A room is
    | played in one of these, and only questions translated into the room's
    | locale are eligible for it.
    |
    */

    'locales' => ['el', 'en'],

    'default_locale' => env('QUIZLY_DEFAULT_LOCALE', 'el'),

    /*
    |--------------------------------------------------------------------------
    | Rooms
    |--------------------------------------------------------------------------
    |
    | How long a room (and the host and player cookies) stays valid, and the
    | colours handed out to players in the order they join.
    |
    */

    'room_lifetime_minutes' => 180,

    'results_visible_minutes' => 60,

    /*
    | The host screen (TV) sends a heartbeat every `heartbeat_seconds`. If none
    | arrives for `timeout_seconds` the game pauses, and if the screen hasn't
    | come back after `return_seconds` the room is closed.
    */
    'host' => [
        'heartbeat_seconds' => 5,
        'timeout_seconds' => 10,
        'return_seconds' => 120,
    ],

    // A player whose phone has been gone this long is removed from the lobby
    // (closing the tab isn't leaving, so a quick refresh keeps their place).
    'lobby_disconnect_grace_seconds' => 20,

    // A lobby nobody has joined for this long is closed.
    'empty_lobby_minutes' => 30,

    // Finished and closed rooms (with their players and answers) are deleted
    // after this many days. Question statistics are kept on the questions.
    'prune_after_days' => 7,

    'player_colors' => [
        '#ef4444', '#3b82f6', '#22c55e', '#eab308', '#a855f7', '#f97316',
        '#06b6d4', '#ec4899', '#84cc16', '#6366f1', '#14b8a6', '#f43f5e',
    ],

    'nickname' => ['min' => 2, 'max' => 16],

    /*
    |--------------------------------------------------------------------------
    | Game Settings Limits
    |--------------------------------------------------------------------------
    |
    | The bounds and defaults for the settings a host can choose for a room.
    |
    */

    'rounds' => ['min' => 1, 'max' => 30, 'default' => 10],

    'seconds_per_round' => ['min' => 5, 'max' => 60, 'default' => 20],

    'players' => ['min' => 2, 'max' => 12, 'default' => 8],

    /*
    |--------------------------------------------------------------------------
    | Timing (milliseconds)
    |--------------------------------------------------------------------------
    |
    | How long each phase of a game lasts. A round shows the question for
    | `reading` ms before the answers unlock; answers then stay open for the
    | host's chosen seconds per round, plus `grace` for answers still in flight.
    | `early_tolerance` absorbs clock differences between phones and server.
    |
    */

    'timing' => [
        'intro' => 4000,
        'reading' => 3000,
        'grace' => 300,
        'early_tolerance' => 250,
        'reveal' => 6000,
        'scoreboard' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------------
    |
    | A correct answer earns `max_points` when given instantly, falling
    | linearly to `min_points` at the deadline. Wrong answers earn nothing.
    |
    */

    'scoring' => [
        'max_points' => 1000,
        'min_points' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Questions
    |--------------------------------------------------------------------------
    */

    'options_per_question' => 4,

];
