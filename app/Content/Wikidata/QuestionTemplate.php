<?php

namespace App\Content\Wikidata;

/**
 * A kind of fact that becomes one question per subject, e.g. "the capital
 * of {country}".
 *
 * The SPARQL query must select ?s (the subject's entity), ?sen and ?sel (its
 * English and Greek labels), ?a, ?aen and ?ael (the answer's id and labels;
 * for numbers, the number in all three) and ?sl (the subject's number of
 * Wikipedia articles, used to rank how well known it is).
 *
 * Greek questions put the subject before a colon ("Γαλλία: ποια είναι η
 * πρωτεύουσα;") or in quotes, so names never need to be declined. A
 * "{του|της}" in the text becomes "της" for female subjects (from Wikidata's
 * sex or gender, ?gender) and "του" otherwise.
 */
final readonly class QuestionTemplate
{
    /**
     * @param  array{en: string, el: string}  $question  With a {subject} placeholder.
     * @param  'pool'|'year'|'number'  $distractors  Wrong answers: other answers from the
     *                                               same query, or nearby years/numbers.
     * @param  int  $limit  At most this many questions, the best-known subjects first.
     * @param  int|null  $maxPerAnswer  At most this many questions with the same answer,
     *                                  so e.g. "football" doesn't dominate.
     */
    public function __construct(
        public string $id,
        public string $category,
        public string $sparql,
        public array $question,
        public string $distractors = 'pool',
        public int $limit = 1000,
        public ?int $maxPerAnswer = null,
    ) {}
}
