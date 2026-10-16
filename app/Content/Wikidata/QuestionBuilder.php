<?php

namespace App\Content\Wikidata;

use App\Enums\Difficulty;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Turns the rows of a template's query into importable questions.
 */
class QuestionBuilder
{
    /** Wikidata's "female" (sex or gender). */
    private const string FEMALE = 'Q6581072';

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    public function build(QuestionTemplate $template, array $rows): array
    {
        $subjects = $this->subjects($rows);
        $pool = $this->answerPool($subjects);
        $perAnswer = [];
        $questions = [];

        foreach (array_values($subjects) as $subject) {
            if (count($questions) >= $template->limit) {
                break;
            }

            $answerId = $subject['answer']['id'];

            // "Whose album is «Metallica»?" gives the answer away.
            if ($template->distractors === 'pool' && $this->givesAway($subject['en'], $subject['answer']['en'])) {
                continue;
            }

            if ($template->distractors === 'year') {
                $answerId = $subject['answer']['id'] = (string) $this->historicalYear((int) $answerId);
                $subject['answer']['en'] = $this->year((int) $answerId, 'en');
                $subject['answer']['el'] = $this->year((int) $answerId, 'el');
            }

            if ($template->maxPerAnswer !== null && ($perAnswer[$answerId] ?? 0) >= $template->maxPerAnswer) {
                continue;
            }

            // Same subject, same template: the same question every time it's generated.
            $random = new Randomizer(new Mt19937(crc32("{$template->id}:{$subject['id']}")));
            $wrong = $this->distractors($template, $subject, $pool, $random);

            if (count($wrong) < 3) {
                continue;
            }

            $correct = $random->getInt(0, 3);
            $options = $wrong;
            array_splice($options, $correct, 0, [$subject['answer']]);

            $questions[] = [
                'ref' => "{$template->id}:{$subject['id']}",
                'category' => $template->category,
                'difficulty' => Difficulty::Medium->value, // set below, once the questions are chosen
                'correct' => $correct,
                'source' => 'wikidata',
                'license' => 'CC0 (Wikidata)',
                'status' => 'published',
                'translations' => [
                    'el' => [
                        'text' => $this->sentence($this->gendered(str_replace('{subject}', $subject['el'], $template->question['el']), $subject['female'])),
                        'options' => array_map(fn (array $option) => $this->sentence($option['el']), $options),
                    ],
                    'en' => [
                        'text' => $this->sentence(str_replace('{subject}', $subject['en'], $template->question['en'])),
                        'options' => array_map(fn (array $option) => $this->sentence($option['en']), $options),
                    ],
                ],
            ];
            $perAnswer[$answerId] = ($perAnswer[$answerId] ?? 0) + 1;
        }

        // The best-known third of the chosen subjects are easy, the next medium, the rest hard.
        foreach ($questions as $rank => &$question) {
            $question['difficulty'] = $this->difficulty($rank, count($questions))->value;
        }
        unset($question);

        return $questions;
    }

    /**
     * Whether one contains the other as a whole word ("Mexico City" → "Mexico"),
     * ignoring short strings like chemical symbols ("Carbon" → "C").
     */
    private function givesAway(string $subject, string $answer): bool
    {
        $contains = fn (string $haystack, string $needle) => mb_strlen($needle) >= 3
            && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/iu', $haystack) === 1;

        return $contains($subject, $answer) || $contains($answer, $subject);
    }

    /**
     * Subjects with exactly one answer, best known first. A subject with
     * several answers (a country with two capitals, a book with two authors)
     * would make a question with more than one right option, so it's skipped.
     *
     * @param  list<array<string, string>>  $rows
     * @return array<string, array{id: string, en: string, el: string, sitelinks: int, female: bool, answer: array{id: string, en: string, el: string}}>
     */
    private function subjects(array $rows): array
    {
        $subjects = [];
        $answers = [];

        foreach ($rows as $row) {
            $id = $this->entityId($row['s'] ?? '');
            $en = $this->label($row['sen'] ?? '');
            $el = $this->label($row['sel'] ?? '');
            $answer = [
                'id' => $this->entityId($row['a'] ?? ''),
                'en' => $this->label($row['aen'] ?? ''),
                'el' => $this->label($row['ael'] ?? ''),
            ];

            if ($id === '' || $en === null || $el === null || $answer['id'] === '' || $answer['en'] === null || $answer['el'] === null) {
                continue;
            }

            $answers[$id][$answer['id']] = true;
            $subjects[$id] ??= [
                'id' => $id,
                'en' => $en,
                'el' => $el,
                'sitelinks' => (int) ($row['sl'] ?? 0),
                'female' => $this->entityId($row['gender'] ?? '') === self::FEMALE,
                'answer' => $answer,
            ];
        }

        $subjects = array_filter($subjects, fn (array $subject) => count($answers[$subject['id']]) === 1);
        uasort($subjects, fn (array $a, array $b) => [$b['sitelinks'], $a['en']] <=> [$a['sitelinks'], $b['en']]);

        return $subjects;
    }

    /**
     * @param  array<string, array{answer: array{id: string, en: string, el: string}}>  $subjects
     * @return list<array{id: string, en: string, el: string}>
     */
    private function answerPool(array $subjects): array
    {
        $pool = [];

        foreach ($subjects as $subject) {
            $pool[$subject['answer']['id']] = $subject['answer'];
        }

        ksort($pool);

        return array_values($pool);
    }

    /**
     * Three wrong answers that differ from the right one, and from each
     * other, in both languages.
     *
     * @param  array{id: string, en: string, el: string, answer: array{id: string, en: string, el: string}}  $subject
     * @param  list<array{id: string, en: string, el: string}>  $pool
     * @return list<array{id: string, en: string, el: string}>
     */
    private function distractors(QuestionTemplate $template, array $subject, array $pool, Randomizer $random): array
    {
        $answer = $subject['answer'];

        $candidates = match ($template->distractors) {
            'year', 'number' => $this->nearbyNumbers($template->distractors, (int) $answer['id'], $random),
            default => $random->shuffleArray($pool),
        };

        $chosen = [];
        // Neither the right answer nor the subject itself ("Zeus: who is his father? Zeus").
        $seen = [
            mb_strtolower($answer['en']) => true,
            mb_strtolower($answer['el']) => true,
            mb_strtolower($subject['en']) => true,
            mb_strtolower($subject['el']) => true,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate['id'] === $answer['id'] || $candidate['id'] === $subject['id'] || isset($seen[mb_strtolower($candidate['en'])]) || isset($seen[mb_strtolower($candidate['el'])])) {
                continue;
            }

            $chosen[] = $candidate;
            $seen[mb_strtolower($candidate['en'])] = $seen[mb_strtolower($candidate['el'])] = true;

            if (count($chosen) === 3) {
                break;
            }
        }

        return $chosen;
    }

    /**
     * @return list<array{id: string, en: string, el: string}>
     */
    private function nearbyNumbers(string $kind, int $answer, Randomizer $random): array
    {
        $offsets = $random->shuffleArray([-12, -9, -7, -5, -4, -3, -2, -1, 1, 2, 3, 4, 5, 7, 9, 12]);
        $numbers = [];

        foreach ($offsets as $offset) {
            $number = $answer + $offset;

            // Years can't be in the future or year 0; counts start at 1.
            if (($kind === 'year' && ($number === 0 || $number > (int) date('Y'))) || ($kind === 'number' && $number < 1)) {
                continue;
            }

            $numbers[] = [
                'id' => (string) $number,
                'en' => $kind === 'year' ? $this->year($number, 'en') : (string) $number,
                'el' => $kind === 'year' ? $this->year($number, 'el') : (string) $number,
            ];
        }

        return $numbers;
    }

    /**
     * The query service numbers years astronomically (year 0 is 1 BC, -479 is
     * 480 BC); historians skip year 0. Negative results here mean BC.
     */
    private function historicalYear(int $year): int
    {
        return $year > 0 ? $year : $year - 1;
    }

    private function year(int $year, string $locale): string
    {
        if ($year > 0) {
            return (string) $year;
        }

        return abs($year).($locale === 'el' ? ' π.Χ.' : ' BC');
    }

    /**
     * Pick the masculine or feminine form in "{του|της}".
     */
    private function gendered(string $text, bool $female): string
    {
        return (string) preg_replace_callback('/\{([^{}|]+)\|([^{}|]+)\}/u', fn (array $forms) => $female ? $forms[2] : $forms[1], $text);
    }

    private function difficulty(int $rank, int $total): Difficulty
    {
        return match (true) {
            $rank < $total / 3 => Difficulty::Easy,
            $rank < $total * 2 / 3 => Difficulty::Medium,
            default => Difficulty::Hard,
        };
    }

    /**
     * "http://www.wikidata.org/entity/Q90" → "Q90"; literals (numbers,
     * symbols) are their own id.
     */
    private function entityId(string $value): string
    {
        return preg_match('~/entity/(Q\d+)$~', $value, $match) === 1 ? $match[1] : trim($value);
    }

    /**
     * A usable label, or null for missing or placeholder labels (bare ids).
     */
    private function label(string $label): ?string
    {
        // "Γαία (μυθολογία)" → "Γαία": the suffix only tells Wikipedia articles apart.
        $label = trim((string) preg_replace('/\s*\([^()]*\)$/u', '', trim($label)));

        if ($label === '' || mb_strlen($label) > 100 || preg_match('/^Q\d+$/', $label) === 1) {
            return null;
        }

        return $label;
    }

    /**
     * Capitalise the first letter ("χρυσός: …" → "Χρυσός: …").
     */
    private function sentence(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
