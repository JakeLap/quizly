<?php

namespace App\Game;

class ScoreCalculator
{
    /**
     * Points for an answer: the faster a correct answer, the more points.
     */
    public function points(bool $correct, int $responseMs, int $limitMs): int
    {
        if (! $correct) {
            return 0;
        }

        $max = (int) config('quizly.scoring.max_points');
        $min = (int) config('quizly.scoring.min_points');
        $ratio = $limitMs > 0 ? min(max($responseMs, 0), $limitMs) / $limitMs : 0;

        return (int) round($max - ($max - $min) * $ratio);
    }
}
