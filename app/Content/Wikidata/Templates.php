<?php

namespace App\Content\Wikidata;

/**
 * The facts Quizly turns into questions, per category.
 *
 * Every query keeps only subjects and answers that have both an English and
 * a Greek label, and ranks subjects by how many Wikipedias cover them
 * (?sl), so the best-known come first and get the easiest difficulty.
 */
final class Templates
{
    /**
     * @return list<QuestionTemplate>
     */
    public static function all(): array
    {
        return [
            ...self::geography(),
            ...self::science(),
            ...self::literature(),
            ...self::film(),
            ...self::sports(),
            ...self::music(),
            ...self::history(),
            ...self::mythology(),
            ...self::food(),
            ...self::general(),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function geography(): array
    {
        $country = '?s wdt:P31 wd:Q3624078. FILTER NOT EXISTS { ?s wdt:P576 [] }';

        return [
            new QuestionTemplate(
                id: 'country-capital',
                category: 'geography',
                sparql: self::query("{$country} ?s wdt:P36 ?a."),
                question: ['en' => 'What is the capital of {subject}?', 'el' => '{subject}: ποια είναι η πρωτεύουσα;'],
            ),
            new QuestionTemplate(
                id: 'capital-country',
                category: 'geography',
                sparql: self::query('?a wdt:P31 wd:Q3624078; wdt:P36 ?s. FILTER NOT EXISTS { ?a wdt:P576 [] }'),
                question: ['en' => '{subject} is the capital of which country?', 'el' => '{subject}: πρωτεύουσα ποιας χώρας είναι;'],
            ),
            new QuestionTemplate(
                id: 'country-continent',
                category: 'geography',
                // The six inhabited continents, not regions like "Insular Oceania".
                sparql: self::query("{$country} ?s wdt:P30 ?a. VALUES ?a { wd:Q15 wd:Q48 wd:Q46 wd:Q49 wd:Q18 wd:Q55643 }"),
                question: ['en' => 'On which continent is {subject}?', 'el' => '{subject}: σε ποια ήπειρο βρίσκεται;'],
                maxPerAnswer: 25,
            ),
            new QuestionTemplate(
                id: 'country-highest-point',
                category: 'geography',
                sparql: self::query("{$country} ?s wdt:P610 ?a."),
                question: ['en' => 'What is the highest point of {subject}?', 'el' => '{subject}: ποιο είναι το ψηλότερο σημείο της χώρας;'],
                limit: 80,
            ),
            new QuestionTemplate(
                id: 'greek-island-group',
                category: 'geography',
                sparql: self::query('?s wdt:P31 wd:Q23442; wdt:P17 wd:Q41; wdt:P361 ?a. ?a wdt:P31 wd:Q1402592.'),
                question: ['en' => 'Which island group does {subject} belong to?', 'el' => '{subject}: σε ποιο νησιωτικό σύμπλεγμα ανήκει;'],
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function science(): array
    {
        // The 118 known elements, not hypothetical ones like unbiunium.
        $element = '?s wdt:P31 wd:Q11344; wdt:P1086 ?z. FILTER(?z <= 118)';

        return [
            new QuestionTemplate(
                id: 'element-symbol',
                category: 'science',
                sparql: self::literalQuery("{$element} ?s wdt:P246 ?a.", 'STR(?a)'),
                question: ['en' => 'What is the chemical symbol of {subject}?', 'el' => '{subject}: ποιο είναι το χημικό σύμβολο;'],
            ),
            new QuestionTemplate(
                id: 'symbol-element',
                category: 'science',
                sparql: self::reverseLiteralQuery('?e wdt:P31 wd:Q11344; wdt:P1086 ?z; wdt:P246 ?symbol. FILTER(?z <= 118)'),
                question: ['en' => 'Which chemical element has the symbol {subject}?', 'el' => 'Ποιο χημικό στοιχείο έχει το σύμβολο {subject};'],
            ),
            new QuestionTemplate(
                id: 'element-atomic-number',
                category: 'science',
                sparql: self::literalQuery("{$element} BIND(?z AS ?a)", 'STR(?a)'),
                question: ['en' => 'What is the atomic number of {subject}?', 'el' => '{subject}: ποιος είναι ο ατομικός αριθμός;'],
                distractors: 'number',
            ),
            self::nobelYear('nobel-physics-year', 'science', 'Q38104', 'the Nobel Prize in Physics', 'το Νόμπελ Φυσικής'),
            self::nobelYear('nobel-chemistry-year', 'science', 'Q44585', 'the Nobel Prize in Chemistry', 'το Νόμπελ Χημείας'),
            self::nobelYear('nobel-medicine-year', 'science', 'Q80061', 'the Nobel Prize in Physiology or Medicine', 'το Νόμπελ Ιατρικής'),
            new QuestionTemplate(
                id: 'unit-quantity',
                category: 'science',
                sparql: self::query('VALUES ?unit { wd:Q223662 wd:Q208469 } ?s wdt:P31 ?unit; wdt:P111 ?a.'),
                question: ['en' => 'What does the unit "{subject}" measure?', 'el' => 'Τι μετράει η μονάδα «{subject}»;'],
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function literature(): array
    {
        return [
            new QuestionTemplate(
                id: 'book-author',
                category: 'literature',
                sparql: self::query('?s wdt:P31 wd:Q7725634; wdt:P50 ?a. ?a wdt:P31 wd:Q5. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 15)'),
                question: ['en' => 'Who wrote "{subject}"?', 'el' => 'Ποιος έγραψε το έργο «{subject}»;'],
                limit: 800,
                maxPerAnswer: 6,
            ),
            self::nobelYear('nobel-literature-year', 'literature', 'Q37922', 'the Nobel Prize in Literature', 'το Νόμπελ Λογοτεχνίας'),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function film(): array
    {
        return [
            new QuestionTemplate(
                id: 'film-director',
                category: 'film-tv',
                sparql: self::query('?s wdt:P31 wd:Q11424; wdt:P57 ?a. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 25)'),
                question: ['en' => 'Who directed the film "{subject}"?', 'el' => 'Ποιος σκηνοθέτησε την ταινία «{subject}»;'],
                limit: 600,
                maxPerAnswer: 5,
            ),
            new QuestionTemplate(
                id: 'film-year',
                category: 'film-tv',
                sparql: self::firstYearQuery('?s wdt:P31 wd:Q11424; wdt:P577 ?date. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 40)'),
                question: ['en' => 'In which year was the film "{subject}" released?', 'el' => 'Ποια χρονιά κυκλοφόρησε η ταινία «{subject}»;'],
                distractors: 'year',
                limit: 280,
            ),
            new QuestionTemplate(
                id: 'series-country',
                category: 'film-tv',
                sparql: self::query('?s wdt:P31 wd:Q5398426; wdt:P495 ?a. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 25)'),
                question: ['en' => 'Which country does the TV series "{subject}" come from?', 'el' => 'Από ποια χώρα είναι η τηλεοπτική σειρά «{subject}»;'],
                limit: 200,
                maxPerAnswer: 40,
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function sports(): array
    {
        return [
            new QuestionTemplate(
                id: 'athlete-sport',
                category: 'sports',
                // Famous athletes in common sports, narrowed down before labels are
                // looked up (searching every athlete times out).
                sparql: self::query('{ SELECT DISTINCT ?s WHERE { VALUES ?job { wd:Q937857 wd:Q3665646 wd:Q10833314 wd:Q11513337 wd:Q10843402 wd:Q10841764 wd:Q11338576 wd:Q2309784 wd:Q11303721 wd:Q11774891 wd:Q15117302 wd:Q10871364 wd:Q12299841 wd:Q13381376 wd:Q11607585 wd:Q13382576 } ?s wdt:P106 ?job; wikibase:sitelinks ?minSl. FILTER(?minSl >= 60) } } FILTER NOT EXISTS { ?s wdt:P106 wd:Q82955 } ?s wdt:P641 ?a.'),
                question: ['en' => 'In which sport did {subject} make their name?', 'el' => '{subject}: σε ποιο άθλημα διακρίθηκε;'],
                limit: 400,
                maxPerAnswer: 50,
            ),
            new QuestionTemplate(
                id: 'olympics-host',
                category: 'sports',
                // Olympic editions are instances of "Summer/Winter Olympic Games edition".
                // Some editions list a stadium as their location: keep only cities.
                sparql: self::query('VALUES ?edition { wd:Q135976384 wd:Q137592217 } ?s wdt:P31 ?edition; wdt:P276 ?a. ?a wdt:P31/wdt:P279* wd:Q515.'),
                question: ['en' => 'Which city hosted the {subject}?', 'el' => '{subject}: ποια πόλη τους φιλοξένησε;'],
            ),
            new QuestionTemplate(
                id: 'club-city',
                category: 'sports',
                sparql: self::query('?s wdt:P31 wd:Q476028; wdt:P159 ?a. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 30)'),
                question: ['en' => 'In which city is the football club {subject} based?', 'el' => '{subject}: σε ποια πόλη έχει την έδρα της αυτή η ομάδα;'],
                limit: 400,
                maxPerAnswer: 6,
            ),
            new QuestionTemplate(
                id: 'world-cup-winner',
                category: 'sports',
                sparql: self::query('?s wdt:P3450 wd:Q19317; wdt:P1346 ?a.'),
                question: ['en' => 'Which national team won the {subject}?', 'el' => '{subject}: ποια εθνική ομάδα το κατέκτησε;'],
            ),
            new QuestionTemplate(
                id: 'club-country',
                category: 'sports',
                sparql: self::query('?s wdt:P31 wd:Q476028; wdt:P17 ?a. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 30)'),
                question: ['en' => 'In which country is the football club {subject} based?', 'el' => '{subject}: από ποια χώρα είναι αυτή η ποδοσφαιρική ομάδα;'],
                limit: 250,
                maxPerAnswer: 20,
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function music(): array
    {
        return [
            new QuestionTemplate(
                id: 'song-performer',
                category: 'music',
                sparql: self::query('?s wdt:P31 wd:Q7366; wdt:P175 ?a. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 10)'),
                question: ['en' => 'Who performs the song "{subject}"?', 'el' => 'Ποιος ερμηνεύει το τραγούδι «{subject}»;'],
            ),
            new QuestionTemplate(
                id: 'album-performer',
                category: 'music',
                sparql: self::query('?s wdt:P31 wd:Q482994; wdt:P175 ?a. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 20)'),
                question: ['en' => 'Whose album is "{subject}"?', 'el' => 'Σε ποιον καλλιτέχνη ανήκει το άλμπουμ «{subject}»;'],
                limit: 300,
                maxPerAnswer: 4,
            ),
            new QuestionTemplate(
                id: 'band-country',
                category: 'music',
                sparql: self::query('?s wdt:P31 wd:Q215380; wdt:P495 ?a. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 35)'),
                question: ['en' => 'Which country does the band {subject} come from?', 'el' => '{subject}: από ποια χώρα προέρχεται αυτό το συγκρότημα;'],
                limit: 200,
                maxPerAnswer: 40,
            ),
            new QuestionTemplate(
                id: 'eurovision-winner',
                category: 'music',
                sparql: self::query('?s wdt:P179 wd:Q276; wdt:P1346 ?a.'),
                question: ['en' => 'Who won the {subject}?', 'el' => '{subject}: ποιος κέρδισε;'],
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function history(): array
    {
        return [
            new QuestionTemplate(
                id: 'greek-battle-year',
                category: 'greek-history',
                sparql: self::firstYearQuery('?s wdt:P31/wdt:P279* wd:Q178561; wdt:P585 ?date. { ?s wdt:P710 wd:Q41 } UNION { ?s wdt:P17 wd:Q41 } UNION { ?s wdt:P710 wd:Q11772 } UNION { ?s wdt:P361 wd:Q1003128 }'),
                question: ['en' => 'In which year did the {subject} take place?', 'el' => '{subject}: ποια χρονιά έγινε;'],
                distractors: 'year',
            ),
            new QuestionTemplate(
                id: 'byzantine-emperor-year',
                category: 'greek-history',
                sparql: self::firstYearQuery('?s p:P39 ?held. ?held ps:P39 wd:Q18577504; pq:P580 ?date.'),
                question: ['en' => 'In which year did {subject} become Byzantine emperor?', 'el' => '{subject}: ποια χρονιά έγινε αυτοκράτορας του Βυζαντίου;'],
                distractors: 'year',
            ),
            new QuestionTemplate(
                id: 'greek-prime-minister-year',
                category: 'greek-history',
                sparql: self::firstYearQuery('?s p:P39 ?held. ?held ps:P39 wd:Q4377230; pq:P580 ?date.'),
                question: ['en' => 'In which year did {subject} first become Prime Minister of Greece?', 'el' => '{subject}: ποια χρονιά έγινε πρώτη φορά πρωθυπουργός της Ελλάδας;'],
                distractors: 'year',
            ),
            new QuestionTemplate(
                id: 'archaeological-site-region',
                category: 'greek-history',
                sparql: self::query('?s wdt:P31 wd:Q839954; wdt:P17 wd:Q41; wdt:P131+ ?a. ?a wdt:P31 wd:Q207299.'),
                question: ['en' => 'In which region of Greece is the archaeological site of {subject}?', 'el' => '{subject}: σε ποια περιφέρεια της Ελλάδας βρίσκεται αυτός ο αρχαιολογικός χώρος;'],
                limit: 300,
                maxPerAnswer: 40,
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function mythology(): array
    {
        $deity = '?s wdt:P31/wdt:P279* wd:Q22989102.';

        return [
            new QuestionTemplate(
                id: 'greek-deity-mother',
                category: 'mythology',
                sparql: self::query("{$deity} ?s wdt:P25 ?a."),
                question: ['en' => 'In Greek mythology, who is the mother of {subject}?', 'el' => '{subject}: ποια είναι η μητέρα {του|της} στην ελληνική μυθολογία;'],
            ),
            new QuestionTemplate(
                id: 'greek-deity-father',
                category: 'mythology',
                sparql: self::query("{$deity} ?s wdt:P22 ?a."),
                question: ['en' => 'In Greek mythology, who is the father of {subject}?', 'el' => '{subject}: ποιος είναι ο πατέρας {του|της} στην ελληνική μυθολογία;'],
                maxPerAnswer: 15,
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function food(): array
    {
        return [
            new QuestionTemplate(
                id: 'dish-origin',
                category: 'food',
                sparql: self::query('?s wdt:P31 wd:Q746549; wdt:P495 ?a. ?a wdt:P31 wd:Q3624078.'),
                question: ['en' => 'Which country does the dish "{subject}" come from?', 'el' => 'Από ποια χώρα προέρχεται το πιάτο «{subject}»;'],
                maxPerAnswer: 20,
            ),
            new QuestionTemplate(
                id: 'cheese-origin',
                category: 'food',
                sparql: self::query('{ ?s wdt:P31 wd:Q10943 } UNION { ?s wdt:P279 wd:Q10943 } ?s wdt:P495 ?a. ?a wdt:P31 wd:Q3624078.'),
                question: ['en' => 'Which country does {subject} cheese come from?', 'el' => 'Από ποια χώρα προέρχεται το τυρί «{subject}»;'],
                maxPerAnswer: 20,
            ),
        ];
    }

    /**
     * @return list<QuestionTemplate>
     */
    private static function general(): array
    {
        return [
            new QuestionTemplate(
                id: 'painting-artist',
                category: 'general',
                sparql: self::query('?s wdt:P31 wd:Q3305213; wdt:P170 ?a. ?a wdt:P31 wd:Q5. ?s wikibase:sitelinks ?minSl. FILTER(?minSl >= 20)'),
                question: ['en' => 'Who painted "{subject}"?', 'el' => 'Ποιος ζωγράφισε το έργο «{subject}»;'],
                maxPerAnswer: 6,
            ),
            self::nobelYear('nobel-peace-year', 'general', 'Q35637', 'the Nobel Peace Prize', 'το Νόμπελ Ειρήνης'),
            new QuestionTemplate(
                id: 'country-calling-code',
                category: 'general',
                sparql: self::literalQuery('?s wdt:P31 wd:Q3624078; wdt:P474 ?a. FILTER NOT EXISTS { ?s wdt:P576 [] }', 'STR(?a)'),
                question: ['en' => 'What is the international calling code of {subject}?', 'el' => '{subject}: ποιος είναι ο διεθνής τηλεφωνικός κωδικός της χώρας;'],
            ),
            new QuestionTemplate(
                id: 'country-internet-domain',
                category: 'general',
                sparql: self::literalQuery('?s wdt:P31 wd:Q3624078; wdt:P78 ?domain. ?domain rdfs:label ?a. FILTER(LANG(?a) = "en") FILTER NOT EXISTS { ?s wdt:P576 [] }', 'STR(?a)'),
                question: ['en' => 'What is the internet country domain of {subject}?', 'el' => '{subject}: ποια είναι η διαδικτυακή κατάληξη (domain) της χώρας;'],
            ),
            new QuestionTemplate(
                id: 'world-heritage-country',
                category: 'general',
                sparql: self::query('{ SELECT ?s WHERE { ?s wdt:P1435 wd:Q9259; wikibase:sitelinks ?minSl. FILTER(?minSl >= 30) } } ?s wdt:P17 ?a. ?a wdt:P31 wd:Q3624078.'),
                question: ['en' => 'In which country is {subject}?', 'el' => '{subject}: σε ποια χώρα βρίσκεται;'],
                limit: 400,
                maxPerAnswer: 25,
            ),
        ];
    }

    /**
     * "In which year did {laureate} win {prize}?"
     */
    private static function nobelYear(string $id, string $category, string $prize, string $prizeEn, string $prizeEl): QuestionTemplate
    {
        return new QuestionTemplate(
            id: $id,
            category: $category,
            sparql: self::literalQuery("?s p:P166 ?award. ?award ps:P166 wd:{$prize}; pq:P585 ?date. BIND(YEAR(?date) AS ?a)", 'STR(?a)'),
            question: ['en' => "In which year did {subject} win {$prizeEn}?", 'el' => "{subject}: ποια χρονιά τιμήθηκε με {$prizeEl};"],
            distractors: 'year',
        );
    }

    /**
     * A query where both the subject and the answer are entities.
     */
    private static function query(string $where): string
    {
        return <<<SPARQL
            SELECT DISTINCT ?s ?sen ?sel ?a ?aen ?ael ?sl ?gender WHERE {
              {$where}
              ?s wikibase:sitelinks ?sl.
              OPTIONAL { ?s wdt:P21 ?gender. }
              ?s rdfs:label ?sen. FILTER(LANG(?sen) = "en")
              ?s rdfs:label ?sel. FILTER(LANG(?sel) = "el")
              ?a rdfs:label ?aen. FILTER(LANG(?aen) = "en")
              ?a rdfs:label ?ael. FILTER(LANG(?ael) = "el")
            }
            SPARQL;
    }

    /**
     * A query whose answer is a literal (a symbol or a number), the same in
     * both languages.
     */
    private static function literalQuery(string $where, string $value): string
    {
        return <<<SPARQL
            SELECT DISTINCT ?s ?sen ?sel ?a (({$value}) AS ?aen) (({$value}) AS ?ael) ?sl WHERE {
              {$where}
              ?s wikibase:sitelinks ?sl.
              ?s rdfs:label ?sen. FILTER(LANG(?sen) = "en")
              ?s rdfs:label ?sel. FILTER(LANG(?sel) = "el")
            }
            SPARQL;
    }

    /**
     * The subject is a literal (?symbol) and the answer is the entity (?e)
     * that has it, e.g. "which element has the symbol Fe?".
     */
    private static function reverseLiteralQuery(string $where): string
    {
        return <<<SPARQL
            SELECT DISTINCT (?symbol AS ?s) (?symbol AS ?sen) (?symbol AS ?sel) (?e AS ?a) (?label_en AS ?aen) (?label_el AS ?ael) ?sl WHERE {
              {$where}
              ?e wikibase:sitelinks ?sl.
              ?e rdfs:label ?label_en. FILTER(LANG(?label_en) = "en")
              ?e rdfs:label ?label_el. FILTER(LANG(?label_el) = "el")
            }
            SPARQL;
    }

    /**
     * A query whose answer is the earliest year among the subject's ?date values
     * (e.g. a film released in different countries on different days).
     */
    private static function firstYearQuery(string $where): string
    {
        return <<<SPARQL
            SELECT ?s ?sen ?sel (STR(MIN(YEAR(?date))) AS ?a) (STR(MIN(YEAR(?date))) AS ?aen) (STR(MIN(YEAR(?date))) AS ?ael) ?sl WHERE {
              {$where}
              ?s wikibase:sitelinks ?sl.
              ?s rdfs:label ?sen. FILTER(LANG(?sen) = "en")
              ?s rdfs:label ?sel. FILTER(LANG(?sel) = "el")
            }
            GROUP BY ?s ?sen ?sel ?sl
            SPARQL;
    }
}
