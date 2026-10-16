<?php

use App\Content\Wikidata\QuestionBuilder;
use App\Content\Wikidata\QuestionTemplate;
use App\Content\Wikidata\WikidataClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function wikidataTemplate(array $overrides = []): QuestionTemplate
{
    return new QuestionTemplate(...array_merge([
        'id' => 'country-capital',
        'category' => 'geography',
        'sparql' => 'SELECT …',
        'question' => ['en' => 'What is the capital of {subject}?', 'el' => '{subject}: ποια είναι η πρωτεύουσα;'],
    ], $overrides));
}

function row(string $subject, string $en, string $el, string $answer, string $answerEn, string $answerEl, int $sitelinks = 100, ?string $gender = null): array
{
    return array_filter([
        's' => "http://www.wikidata.org/entity/{$subject}", 'sen' => $en, 'sel' => $el,
        'a' => "http://www.wikidata.org/entity/{$answer}", 'aen' => $answerEn, 'ael' => $answerEl,
        'sl' => (string) $sitelinks, 'gender' => $gender ? "http://www.wikidata.org/entity/{$gender}" : null,
    ]);
}

function capitals(): array
{
    return [
        row('Q142', 'France', 'Γαλλία', 'Q90', 'Paris', 'Παρίσι', 300),
        row('Q183', 'Germany', 'Γερμανία', 'Q64', 'Berlin', 'Βερολίνο', 290),
        row('Q38', 'Italy', 'Ιταλία', 'Q220', 'Rome', 'Ρώμη', 280),
        row('Q29', 'Spain', 'Ισπανία', 'Q2807', 'Madrid', 'Μαδρίτη', 270),
        row('Q41', 'Greece', 'Ελλάδα', 'Q1524', 'Athens', 'Αθήνα', 260),
        row('Q258', 'South Africa', 'Νότια Αφρική', 'Q3926', 'Pretoria', 'Πρετόρια', 250),
        row('Q258', 'South Africa', 'Νότια Αφρική', 'Q5465', 'Cape Town', 'Κέιπ Τάουν', 250),
    ];
}

test('each subject becomes a bilingual question with three wrong options of the same kind', function () {
    $questions = (new QuestionBuilder)->build(wikidataTemplate(), capitals());
    $france = collect($questions)->firstWhere('ref', 'country-capital:Q142');

    expect($france['translations']['el']['text'])->toBe('Γαλλία: ποια είναι η πρωτεύουσα;')
        ->and($france['translations']['en']['text'])->toBe('What is the capital of France?')
        ->and($france['translations']['en']['options'][$france['correct']])->toBe('Paris')
        ->and($france['translations']['el']['options'][$france['correct']])->toBe('Παρίσι')
        ->and($france['translations']['en']['options'])->toHaveCount(4)
        ->and(array_unique($france['translations']['en']['options']))->toHaveCount(4)
        ->and($france['source'])->toBe('wikidata')
        ->and($france['status'])->toBe('published');

    // Options line up across the two languages.
    foreach ($france['translations']['en']['options'] as $index => $option) {
        $capital = collect(capitals())->firstWhere('aen', $option);
        expect($france['translations']['el']['options'][$index])->toBe($capital['ael']);
    }
});

test('subjects with more than one answer are left out', function () {
    $refs = array_column((new QuestionBuilder)->build(wikidataTemplate(), capitals()), 'ref');

    expect($refs)->not->toContain('country-capital:Q258')->toHaveCount(5);
});

test('the same input always gives the same questions', function () {
    $builder = new QuestionBuilder;

    expect($builder->build(wikidataTemplate(), capitals()))->toBe($builder->build(wikidataTemplate(), array_reverse(capitals())));
});

test('the best-known subjects are the easiest', function () {
    $questions = collect((new QuestionBuilder)->build(wikidataTemplate(), capitals()))->keyBy('ref');

    expect($questions['country-capital:Q142']['difficulty'])->toBe('easy')
        ->and($questions['country-capital:Q41']['difficulty'])->toBe('hard');
});

test('the subject itself is never offered as an answer', function () {
    $rows = [
        row('Q34201', 'Zeus', 'Δίας', 'Q44204', 'Cronus', 'Κρόνος'),
        row('Q37122', 'Athena', 'Αθηνά', 'Q34201', 'Zeus', 'Δίας'),
        row('Q39018', 'Apollo', 'Απόλλωνας', 'Q34201', 'Zeus', 'Δίας'),
        row('Q41127', 'Ares', 'Άρης', 'Q34201', 'Zeus', 'Δίας'),
        row('Q83101', 'Rhea', 'Ρέα', 'Q44204', 'Cronus', 'Κρόνος'),
        row('Q170497', 'Hyperion', 'Υπερίων', 'Q5196', 'Uranus', 'Ουρανός'),
        row('Q41264', 'Gaia', 'Γαία', 'Q1', 'Chaos', 'Χάος'),
    ];

    foreach ((new QuestionBuilder)->build(wikidataTemplate(['id' => 'deity-father']), $rows) as $question) {
        $subject = collect($rows)->firstWhere('s', 'http://www.wikidata.org/entity/'.explode(':', $question['ref'])[1]);
        expect($question['translations']['en']['options'])->not->toContain($subject['sen']);
    }
});

test('Greek wording follows the subject’s gender', function () {
    $template = wikidataTemplate(['question' => ['en' => 'Who is the mother of {subject}?', 'el' => '{subject}: ποια είναι η μητέρα {του|της};']]);
    $rows = [
        row('Q34201', 'Zeus', 'Δίας', 'Q83101', 'Rhea', 'Ρέα', 300, 'Q6581097'),
        row('Q37122', 'Athena', 'Αθηνά', 'Q190', 'Metis', 'Μήτις', 290, 'Q6581072'),
        row('Q1', 'A', 'Α', 'Q2', 'B', 'Β'), row('Q3', 'C', 'Γ', 'Q4', 'D', 'Δ'), row('Q5', 'E', 'Ε', 'Q6', 'F', 'Ζ'),
    ];
    $questions = collect((new QuestionBuilder)->build($template, $rows))->keyBy('ref');

    expect($questions['country-capital:Q34201']['translations']['el']['text'])->toBe('Δίας: ποια είναι η μητέρα του;')
        ->and($questions['country-capital:Q37122']['translations']['el']['text'])->toBe('Αθηνά: ποια είναι η μητέρα της;');
});

test('labels lose disambiguation suffixes and sentences start with a capital', function () {
    $rows = [
        row('Q1', 'gold', 'χρυσός', 'Q10', 'Gaia (mythology)', 'Γαία (μυθολογία)'),
        row('Q2', 'iron', 'σίδηρος', 'Q20', 'b', 'β'), row('Q3', 'tin', 'κασσίτερος', 'Q30', 'c', 'γ'), row('Q4', 'lead', 'μόλυβδος', 'Q40', 'd', 'δ'),
    ];
    $gold = collect((new QuestionBuilder)->build(wikidataTemplate(), $rows))->firstWhere('ref', 'country-capital:Q1');

    expect($gold['translations']['el']['text'])->toStartWith('Χρυσός:')
        ->and($gold['translations']['el']['options'][$gold['correct']])->toBe('Γαία');
});

test('BC years are shown the way historians count them', function () {
    $template = wikidataTemplate(['id' => 'battle-year', 'distractors' => 'year', 'question' => ['en' => 'When was {subject}?', 'el' => '{subject}: πότε έγινε;']]);
    $row = ['s' => 'http://www.wikidata.org/entity/Q11', 'sen' => 'Battle of Marathon', 'sel' => 'Μάχη του Μαραθώνα', 'a' => '-489', 'aen' => '-489', 'ael' => '-489', 'sl' => '100'];
    $marathon = (new QuestionBuilder)->build($template, [$row])[0];

    expect($marathon['translations']['en']['options'][$marathon['correct']])->toBe('490 BC')
        ->and($marathon['translations']['el']['options'][$marathon['correct']])->toBe('490 π.Χ.')
        ->and(array_unique($marathon['translations']['en']['options']))->toHaveCount(4);
});

test('one answer can be capped so it does not dominate', function () {
    $rows = array_map(fn (int $i) => row("Q{$i}", "Player {$i}", "Παίκτης {$i}", 'Q2736', 'Football', 'Ποδόσφαιρο'), range(1, 10));
    $rows[] = row('Q90', 'Pete', 'Πιτ', 'Q847', 'Tennis', 'Τένις');
    $rows[] = row('Q91', 'Mike', 'Μάικ', 'Q5372', 'Basketball', 'Μπάσκετ');
    $rows[] = row('Q92', 'Lewis', 'Λιούις', 'Q1968', 'Formula One', 'Φόρμουλα 1');

    $questions = (new QuestionBuilder)->build(wikidataTemplate(['maxPerAnswer' => 3]), $rows);
    $football = array_filter($questions, fn (array $question) => $question['translations']['en']['options'][$question['correct']] === 'Football');

    expect($football)->toHaveCount(3);
});

test('difficulty is spread over the questions actually chosen', function () {
    $rows = array_map(fn (int $i) => row("Q{$i}", "Film {$i}", "Ταινία {$i}", 'Q'.(1000 + $i), "Director {$i}", "Σκηνοθέτης {$i}", 1000 - $i), range(1, 30));

    $questions = (new QuestionBuilder)->build(wikidataTemplate(['limit' => 9]), $rows);

    expect(array_count_values(array_column($questions, 'difficulty')))->toBe(['easy' => 3, 'medium' => 3, 'hard' => 3]);
});

test('questions whose subject gives the answer away are left out', function () {
    $rows = [
        row('Q1', 'Metallica', 'Metallica', 'Q10', 'Metallica', 'Metallica'),
        row('Q2', 'Nevermind', 'Nevermind', 'Q20', 'Nirvana', 'Nirvana'),
        row('Q3', 'Thriller', 'Thriller', 'Q30', 'Michael Jackson', 'Μάικλ Τζάκσον'),
        row('Q4', 'Abbey Road', 'Abbey Road', 'Q40', 'The Beatles', 'The Beatles'),
        row('Q5', 'Rumours', 'Rumours', 'Q50', 'Fleetwood Mac', 'Fleetwood Mac'),
    ];

    $refs = array_column((new QuestionBuilder)->build(wikidataTemplate(['id' => 'album']), $rows), 'ref');

    expect($refs)->not->toContain('album:Q1')->toHaveCount(4);
});

test('short answers inside longer names do not count as giving the answer away', function () {
    $rows = [
        row('Q623', 'carbon', 'άνθρακας', 'C', 'C', 'C'), row('Q629', 'oxygen', 'οξυγόνο', 'O', 'O', 'O'),
        row('Q556', 'hydrogen', 'υδρογόνο', 'H', 'H', 'H'), row('Q627', 'nitrogen', 'άζωτο', 'N', 'N', 'N'),
    ];

    expect((new QuestionBuilder)->build(wikidataTemplate(['id' => 'element-symbol']), $rows))->toHaveCount(4);
});

test('a cut-off Wikidata response is an error, not an empty result', function () {
    Http::fake(['query.wikidata.org/*' => Http::response('{"head":{"vars":["s"]},"results":{"bindi', 200)]);

    expect(fn () => app(WikidataClient::class)->select('SELECT …'))->toThrow(RuntimeException::class);
});

test('questions with known wrong Wikidata data are left out', function () {
    $directory = sys_get_temp_dir().'/quizly-wikidata-'.uniqid();
    config(['quizly.question_files' => $directory, 'quizly.wikidata.exclude' => ['country-capital:Q142']]);
    $bindings = array_map(fn (array $row) => array_map(fn ($value) => ['value' => $value], $row), capitals());
    Http::fake(['*' => Http::response(['results' => ['bindings' => $bindings]])]);

    $this->artisan('quizly:generate-wikidata-questions', ['--template' => ['country-capital']])->assertSuccessful();

    $refs = array_column(json_decode(file_get_contents("{$directory}/wikidata/geography.json"), true), 'ref');
    expect($refs)->not->toContain('country-capital:Q142')->toHaveCount(4);

    File::deleteDirectory($directory);
});

test('the command writes a file per category and keeps what a failed template had', function () {
    $directory = sys_get_temp_dir().'/quizly-wikidata-'.uniqid();
    config(['quizly.question_files' => $directory]);
    File::ensureDirectoryExists("{$directory}/wikidata");
    file_put_contents("{$directory}/wikidata/geography.json", json_encode([['ref' => 'capital-country:Q90', 'category' => 'geography', 'correct' => 0, 'translations' => []]]));
    $real = database_path('data/questions/wikidata/geography.json');
    $realBefore = is_file($real) ? md5_file($real) : null;

    $bindings = array_map(fn (array $row) => array_map(fn ($value) => ['value' => $value], $row), capitals());
    Http::fake(fn ($request) => str_contains(urldecode($request->body()), 'wdt:P36 ?s')
        ? Http::response(['results' => ['bindings' => []]]) // capital → country: nothing came back
        : Http::response(['results' => ['bindings' => $bindings]]));

    $this->artisan('quizly:generate-wikidata-questions', ['--template' => ['country-capital', 'capital-country']])->assertFailed();

    $refs = array_column(json_decode(file_get_contents("{$directory}/wikidata/geography.json"), true), 'ref');
    expect($refs)->toContain('capital-country:Q90', 'country-capital:Q142')->toHaveCount(6)
        // The real question files are never touched by the test.
        ->and(is_file($real) ? md5_file($real) : null)->toBe($realBefore);

    File::deleteDirectory($directory);
});
