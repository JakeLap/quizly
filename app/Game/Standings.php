<?php

namespace App\Game;

use App\Enums\RoundStatus;
use App\Models\Answer;
use App\Models\Game;
use App\Models\Player;

class Standings
{
    /**
     * The players ranked by score. Equal scores are separated by speed: the
     * total time taken over all correct answers, lowest first.
     *
     * @return list<array{playerId: int, rank: int, score: int, correct: int, correctMs: int}>
     */
    public static function for(Game $game): array
    {
        $stats = Answer::query()
            ->join('rounds', 'rounds.id', '=', 'answers.round_id')
            ->where('rounds.game_id', $game->id)
            ->where('rounds.status', RoundStatus::Revealed)
            ->where('answers.is_correct', true)
            ->groupBy('answers.player_id')
            ->selectRaw('answers.player_id, count(*) as correct, sum(answers.response_ms) as correct_ms')
            ->get()
            ->keyBy('player_id');

        $rows = $game->participants()->get()
            ->map(fn (Player $player) => [
                'playerId' => $player->id,
                'nickname' => $player->nickname,
                'score' => $player->score,
                'correct' => (int) ($stats->get($player->id)?->getAttribute('correct') ?? 0),
                'correctMs' => (int) ($stats->get($player->id)?->getAttribute('correct_ms') ?? 0),
            ])
            ->sort(fn (array $a, array $b) => [$b['score'], $a['correctMs'], $a['nickname']] <=> [$a['score'], $b['correctMs'], $b['nickname']])
            ->values();

        $standings = [];
        $previous = null;

        foreach ($rows as $position => $row) {
            $tied = $previous && $previous['score'] === $row['score'] && $previous['correctMs'] === $row['correctMs'];
            $rank = $tied ? $standings[$position - 1]['rank'] : $position + 1;

            $standings[] = [
                'playerId' => $row['playerId'],
                'rank' => $rank,
                'score' => $row['score'],
                'correct' => $row['correct'],
                'correctMs' => $row['correctMs'],
            ];
            $previous = $row;
        }

        return $standings;
    }
}
