<?php

use App\Actions\Questions\ImportQuestions;
use App\Enums\QuestionSource;
use App\Enums\QuestionStatus;
use App\Models\Category;
use App\Models\Question;
use App\Models\Round;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->category = Category::factory()->create(['slug' => 'geography']);
});

/**
 * The form as the admin sends it.
 *
 * @return array<string, mixed>
 */
function questionForm(array $overrides = []): array
{
    return array_replace_recursive([
        'category_id' => test()->category->id,
        'difficulty' => 'easy',
        'status' => 'published',
        'correct_index' => 1,
        'translations' => [
            'el' => ['text' => 'Ποιο είναι το μεγαλύτερο νησί της Ελλάδας;', 'options' => ['Εύβοια', 'Κρήτη', 'Λέσβος', 'Ρόδος'], 'explanation' => ''],
            'en' => ['text' => 'What is the largest island in Greece?', 'options' => ['Euboea', 'Crete', 'Lesbos', 'Rhodes'], 'explanation' => 'Crete is about 8,300 km².'],
        ],
    ], $overrides);
}

test('only admins can open the question admin', function () {
    $this->get(route('admin.questions.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.questions.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('admin.questions.index'))->assertOk();
});

test('the question admin is gone while accounts are off', function () {
    config(['quizly.accounts_enabled' => false]);

    $this->actingAs($this->admin)->get(route('admin.questions.index'))->assertNotFound();
});

test('the list shows questions with counts per status, and filters them', function () {
    Question::factory()->for($this->category)->count(2)->create();
    Question::factory()->for($this->category)->draft()->create();
    $other = Question::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.questions.index', ['status' => 'draft']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/questions/Index')
            ->has('questions.data', 1)
            ->where('questions.data.0.status', 'draft')
            ->where('statusCounts.all', 4)
            ->where('statusCounts.published', 3)
            ->where('statusCounts.draft', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.questions.index', ['category' => $other->category_id]))
        ->assertInertia(fn (Assert $page) => $page->has('questions.data', 1)->where('questions.data.0.id', $other->id));
});

test('the list filters by type, or by having none', function () {
    $flag = Question::factory()->for($this->category)->create(['kind' => 'flags']);
    $plain = Question::factory()->for($this->category)->create(['kind' => null]);
    Question::factory()->for($this->category)->create(['kind' => 'capitals']);

    $this->actingAs($this->admin)
        ->get(route('admin.questions.index', ['kind' => 'flags']))
        ->assertInertia(fn (Assert $page) => $page->has('questions.data', 1)->where('questions.data.0.id', $flag->id)->where('questions.data.0.kind', 'flags'));

    $this->actingAs($this->admin)
        ->get(route('admin.questions.index', ['kind' => '__none']))
        ->assertInertia(fn (Assert $page) => $page->has('questions.data', 1)->where('questions.data.0.id', $plain->id));
});

test('the list searches the wording, the answers and the id', function () {
    $island = Question::factory()->for($this->category)->create();
    $island->translations()->where('locale', 'en')->update(['text' => 'Which island is the biggest?', 'options' => json_encode(['Euboea', 'Crete', 'Lesbos', 'Rhodes'])]);
    Question::factory()->for($this->category)->create();

    foreach (['ISLAND', 'crete', (string) $island->id] as $search) {
        $this->actingAs($this->admin)
            ->get(route('admin.questions.index', ['search' => $search]))
            ->assertInertia(fn (Assert $page) => $page->has('questions.data', 1)->where('questions.data.0.id', $island->id));
    }
});

test('an admin can write a new question', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.questions.store'), questionForm())
        ->assertSessionHasNoErrors();

    $question = Question::sole();
    expect($question->source)->toBe(QuestionSource::Manual)
        ->and($question->status)->toBe(QuestionStatus::Published)
        ->and($question->correct_index)->toBe(1)
        ->and($question->translation('en')->options)->toBe(['Euboea', 'Crete', 'Lesbos', 'Rhodes'])
        ->and($question->translation('el')->explanation)->toBeNull()
        ->and($question->translation('en')->reviewed_by)->toBe($this->admin->id);
});

test('an admin can give a question a type, written one way', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.questions.store'), questionForm(['kind' => '  Greek Flags ']))
        ->assertSessionHasNoErrors();

    $question = Question::sole();
    expect($question->kind)->toBe('greek-flags');

    $this->actingAs($this->admin)
        ->get(route('admin.questions.edit', $question))
        ->assertInertia(fn (Assert $page) => $page->where('question.kind', 'greek-flags')->where('kinds', ['greek-flags']));

    $this->actingAs($this->admin)->put(route('admin.questions.update', $question), questionForm(['kind' => '']))->assertSessionHasNoErrors();
    expect($question->fresh()->kind)->toBeNull();
});

test('a question needs at least one complete language with different answers', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->post(route('admin.questions.store'), questionForm($overrides))
        ->assertSessionHasErrors($field);

    expect(Question::query()->count())->toBe(0);
})->with([
    'no language at all' => [['translations' => [
        'el' => ['text' => '', 'options' => ['', '', '', ''], 'explanation' => ''],
        'en' => ['text' => ' ', 'options' => ['', '', '', ''], 'explanation' => ''],
    ]], 'translations'],
    'an answer missing' => [['translations' => ['en' => ['options' => ['Euboea', '', 'Lesbos', 'Rhodes']]]], 'translations.en.options.1'],
    'the same answer twice' => [['translations' => ['el' => ['options' => ['Κρήτη', 'κρήτη ', 'Λέσβος', 'Ρόδος']]]], 'translations.el.options'],
    'no question text' => [['translations' => ['en' => ['text' => '']]], 'translations.en.text'],
    'an unknown category' => [['category_id' => 999], 'category_id'],
    'a fifth answer marked correct' => [['correct_index' => 4], 'correct_index'],
]);

test('an empty language means the question isn\'t translated', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.questions.store'), questionForm(['translations' => ['en' => ['text' => '', 'options' => ['', '', '', ''], 'explanation' => '']]]))
        ->assertSessionHasNoErrors();

    expect(Question::sole()->translations->pluck('locale')->all())->toBe(['el']);
});

test('editing the wording marks the question, so imports keep the edit', function () {
    $question = Question::factory()->for($this->category)->create(['source' => QuestionSource::Wikidata, 'source_ref' => 'capital-Q1']);

    $this->actingAs($this->admin)
        ->put(route('admin.questions.update', $question), questionForm())
        ->assertSessionHasNoErrors();

    expect($question->fresh()->edited_at)->not->toBeNull();

    $result = app(ImportQuestions::class)->handle([[
        'ref' => 'capital-Q1',
        'source' => 'wikidata',
        'category' => 'geography',
        'correct' => 0,
        'translations' => ['en' => ['text' => 'Something else?', 'options' => ['A', 'B', 'C', 'D']]],
    ]]);

    expect($result->kept)->toBe(1)
        ->and($question->fresh()->correct_index)->toBe(1)
        ->and($question->fresh()->translation('en')->text)->toBe('What is the largest island in Greece?');
});

test('changing only the status doesn\'t mark the question as edited', function () {
    $question = Question::factory()->for($this->category)->draft()->create(['correct_index' => 1]);
    $form = questionForm(['status' => 'published']);
    $form['translations'] = $question->translations->mapWithKeys(fn ($t) => [$t->locale => ['text' => $t->text, 'options' => $t->options, 'explanation' => $t->explanation]])->all();
    $form['difficulty'] = $question->difficulty->value;

    $this->actingAs($this->admin)->put(route('admin.questions.update', $question), $form)->assertSessionHasNoErrors();

    expect($question->fresh())->status->toBe(QuestionStatus::Published)->edited_at->toBeNull();

    $this->actingAs($this->admin)->patch(route('admin.questions.status', $question), ['status' => 'rejected']);
    expect($question->fresh())->status->toBe(QuestionStatus::Rejected)->edited_at->toBeNull();
});

test('emptying a language removes its translation', function () {
    $question = Question::factory()->for($this->category)->create();

    $this->actingAs($this->admin)
        ->put(route('admin.questions.update', $question), questionForm(['translations' => ['en' => ['text' => '', 'options' => ['', '', '', ''], 'explanation' => '']]]))
        ->assertSessionHasNoErrors();

    expect($question->fresh()->translations->pluck('locale')->all())->toBe(['el']);
});

test('only questions written here and never played can be deleted', function () {
    $fresh = Question::factory()->for($this->category)->create();
    $fromFile = Question::factory()->for($this->category)->create(['source_ref' => 'seed-1']);
    $played = Question::factory()->for($this->category)->create();
    Round::factory()->create(['question_id' => $played->id, 'category_id' => $played->category_id]);

    $this->actingAs($this->admin)->get(route('admin.questions.edit', $fresh))
        ->assertInertia(fn (Assert $page) => $page->component('admin/questions/Edit')->where('question.can_delete', true));
    $this->actingAs($this->admin)->get(route('admin.questions.edit', $fromFile))
        ->assertInertia(fn (Assert $page) => $page->where('question.can_delete', false));

    $this->actingAs($this->admin)->delete(route('admin.questions.destroy', $fresh))->assertRedirect(route('admin.questions.index'));
    $this->actingAs($this->admin)->delete(route('admin.questions.destroy', $fromFile));
    $this->actingAs($this->admin)->delete(route('admin.questions.destroy', $played));

    expect(Question::find($fresh->id))->toBeNull()
        ->and(Question::find($fromFile->id))->not->toBeNull()
        ->and(Question::find($played->id))->not->toBeNull();
});

test('signing up stays off when accounts are on', function () {
    config(['quizly.registration_enabled' => false]);

    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'Someone', 'email' => 'someone@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('canRegister', false));

    expect(User::query()->where('email', 'someone@example.com')->exists())->toBeFalse();
});

test('admins are created, or promoted, from the command line', function () {
    $this->artisan('quizly:make-admin', ['email' => 'Maria@Example.com', '--name' => 'Maria'])
        ->expectsQuestion('Password', 'a-long-enough-password')
        ->assertSuccessful();

    $maria = User::query()->where('email', 'maria@example.com')->sole();
    expect($maria->is_admin)->toBeTrue()->and($maria->email_verified_at)->not->toBeNull();

    $someone = User::factory()->create();
    $this->artisan('quizly:make-admin', ['email' => $someone->email])->assertSuccessful();
    expect($someone->fresh()->is_admin)->toBeTrue();
});
