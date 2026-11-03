<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Info, Save, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTrans } from '@/composables/useTrans';
import {
    difficultyLabels,
    localeNames,
    percent,
    sourceLabels,
    statusClasses,
    statusLabels,
} from '@/lib/questions';
import { destroy, index, store, update } from '@/routes/admin/questions';
import type {
    Difficulty,
    QuestionDetail,
    QuestionOptions,
    QuestionStatus,
} from '@/types/admin';

const props = defineProps<
    QuestionOptions & {
        /** Null when writing a new question. */
        question: QuestionDetail | null;
    }
>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Questions', href: index() }],
    },
});

const { t, locale } = useTrans();

type Wording = { text: string; options: string[]; explanation: string };

function wording(code: string): Wording {
    const existing = props.question?.translations[code];

    return {
        text: existing?.text ?? '',
        options: Array.from(
            { length: props.optionCount },
            (_, i) => existing?.options[i] ?? '',
        ),
        explanation: existing?.explanation ?? '',
    };
}

const form = useForm({
    category_id: props.question?.category_id ?? props.categories[0]?.id ?? null,
    kind: props.question?.kind ?? '',
    difficulty: props.question?.difficulty ?? ('medium' as Difficulty),
    status: props.question?.status ?? ('draft' as QuestionStatus),
    correct_index: props.question?.correct_index ?? 0,
    translations: Object.fromEntries(
        props.locales.map((code) => [code, wording(code)]),
    ) as Record<string, Wording>,
});

// The radio group and the selects work with strings.
const correct = computed({
    get: () => String(form.correct_index),
    set: (value: string) => (form.correct_index = Number(value)),
});

const categoryId = computed({
    get: () => (form.category_id === null ? '' : String(form.category_id)),
    set: (value: string) => (form.category_id = Number(value)),
});

// The language the admin reads the site in comes first.
const orderedLocales = computed(() =>
    [...props.locales].sort((a, b) =>
        a === locale.value ? -1 : b === locale.value ? 1 : 0,
    ),
);

function isEmpty(code: string) {
    const translation = form.translations[code];

    return (
        translation.text.trim() === '' &&
        translation.options.every((option) => option.trim() === '') &&
        translation.explanation.trim() === ''
    );
}

function optionError(code: string, i: number) {
    const errors = form.errors as Record<string, string | undefined>;

    return errors[`translations.${code}.options.${i}`];
}

function fieldError(key: string) {
    return (form.errors as Record<string, string | undefined>)[key];
}

function save() {
    if (props.question) {
        form.put(update.url(props.question.id), { preserveScroll: true });
    } else {
        form.post(store.url());
    }
}

const confirmingDelete = ref(false);

function remove() {
    if (props.question) {
        router.delete(destroy.url(props.question.id), {
            onFinish: () => (confirmingDelete.value = false),
        });
    }
}

const dateFormat = computed(
    () => new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }),
);

function formatDate(value: string | null | undefined) {
    return value ? dateFormat.value.format(new Date(value)) : null;
}

const title = computed(() =>
    props.question
        ? t('Question #:id', { id: props.question.id })
        : t('New question'),
);
</script>

<template>
    <Head :title="title" />

    <form
        class="flex flex-col gap-6 p-4 pb-0 md:p-6 md:pb-0"
        @submit.prevent="save"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    as-child
                    class="-ml-2 shrink-0"
                >
                    <Link
                        :href="index()"
                        :aria-label="t('Back to the questions')"
                    >
                        <ChevronLeft />
                    </Link>
                </Button>
                <Heading
                    :title="title"
                    :description="
                        t(
                            'The answers keep the same order in every language, so the correct one is marked once for all.',
                        )
                    "
                />
            </div>
            <Badge
                v-if="question"
                variant="outline"
                :class="statusClasses[question.status]"
            >
                {{ t(statusLabels[question.status]) }}
            </Badge>
        </div>

        <InputError :message="fieldError('translations')" />

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
        >
            <!-- Phones: details, wording, then the rest. Wide screens: wording on the left. -->
            <div class="grid gap-6 lg:col-start-2 lg:row-start-1">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('Details') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="category">{{ t('Category') }}</Label>
                            <Select v-model="categoryId">
                                <SelectTrigger id="category" class="w-full">
                                    <SelectValue
                                        :placeholder="t('Choose a category')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="String(category.id)"
                                    >
                                        {{ category.name }}
                                        <span
                                            v-if="!category.active"
                                            class="text-muted-foreground"
                                            >({{ t('hidden') }})</span
                                        >
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="kind">
                                {{ t('Type') }}
                                <span class="font-normal text-muted-foreground"
                                    >· {{ t('optional') }}</span
                                >
                            </Label>
                            <Input
                                id="kind"
                                v-model="form.kind"
                                list="question-kinds"
                                maxlength="64"
                                autocomplete="off"
                                :placeholder="t('e.g. flags')"
                            />
                            <datalist id="question-kinds">
                                <option
                                    v-for="kind in kinds"
                                    :key="kind"
                                    :value="kind"
                                />
                            </datalist>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'Questions of the same type are spread out in a game, so it doesn’t ask the same kind of thing twice in a row.',
                                    )
                                }}
                            </p>
                            <InputError :message="form.errors.kind" />
                        </div>

                        <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
                            <div class="grid gap-2">
                                <Label for="difficulty">{{
                                    t('Difficulty')
                                }}</Label>
                                <Select v-model="form.difficulty">
                                    <SelectTrigger
                                        id="difficulty"
                                        class="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="difficulty in difficulties"
                                            :key="difficulty"
                                            :value="difficulty"
                                        >
                                            {{
                                                t(difficultyLabels[difficulty])
                                            }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.difficulty" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="status">{{ t('Status') }}</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger id="status" class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="status in statuses"
                                            :key="status"
                                            :value="status"
                                        >
                                            {{ t(statusLabels[status]) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.status" />
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ t('Only published questions are played.') }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- The wording, one card per language. -->
            <div class="grid gap-6 lg:col-start-1 lg:row-span-2 lg:row-start-1">
                <Card v-for="code in orderedLocales" :key="code">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            {{ localeNames[code] ?? code }}
                            <Badge v-if="isEmpty(code)" variant="outline">{{
                                t('Not translated')
                            }}</Badge>
                        </CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'Leave every field empty if the question isn’t available in this language.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-5">
                        <div class="grid gap-2">
                            <Label :for="`text-${code}`">{{
                                t('Question')
                            }}</Label>
                            <Textarea
                                :id="`text-${code}`"
                                v-model="form.translations[code].text"
                                :lang="code"
                                rows="2"
                                maxlength="500"
                            />
                            <InputError
                                :message="
                                    fieldError(`translations.${code}.text`)
                                "
                            />
                        </div>

                        <fieldset class="grid gap-2">
                            <legend class="mb-2 text-sm font-medium">
                                {{ t('Answers') }}
                                <span class="font-normal text-muted-foreground">
                                    · {{ t('select the correct one') }}
                                </span>
                            </legend>
                            <RadioGroup v-model="correct" class="gap-2">
                                <div
                                    v-for="(_, i) in form.translations[code]
                                        .options"
                                    :key="i"
                                    class="grid gap-1"
                                >
                                    <div
                                        class="flex items-center gap-3 rounded-md border p-2 pl-3 transition-colors"
                                        :class="
                                            correct === String(i)
                                                ? 'border-emerald-500/60 bg-emerald-500/5'
                                                : ''
                                        "
                                    >
                                        <RadioGroupItem
                                            :id="`correct-${code}-${i}`"
                                            :value="String(i)"
                                            :aria-label="
                                                t('Answer :n is correct', {
                                                    n: i + 1,
                                                })
                                            "
                                        />
                                        <Input
                                            v-model="
                                                form.translations[code].options[
                                                    i
                                                ]
                                            "
                                            :lang="code"
                                            maxlength="120"
                                            class="h-9"
                                            :placeholder="
                                                t('Answer :n', { n: i + 1 })
                                            "
                                            :aria-label="
                                                t('Answer :n', { n: i + 1 })
                                            "
                                        />
                                    </div>
                                    <InputError
                                        :message="optionError(code, i)"
                                    />
                                </div>
                            </RadioGroup>
                            <InputError
                                :message="
                                    fieldError(`translations.${code}.options`)
                                "
                            />
                        </fieldset>

                        <div class="grid gap-2">
                            <Label :for="`explanation-${code}`">
                                {{ t('Explanation') }}
                                <span class="font-normal text-muted-foreground">
                                    ·
                                    {{ t('optional, shown after the answer') }}
                                </span>
                            </Label>
                            <Textarea
                                :id="`explanation-${code}`"
                                v-model="form.translations[code].explanation"
                                :lang="code"
                                rows="2"
                                maxlength="1000"
                            />
                            <InputError
                                :message="
                                    fieldError(
                                        `translations.${code}.explanation`,
                                    )
                                "
                            />
                        </div>

                        <p
                            v-if="question?.translations[code]?.reviewed_by"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                t('Last saved by :name on :date.', {
                                    name:
                                        question.translations[code]
                                            .reviewed_by ?? '',
                                    date:
                                        formatDate(
                                            question.translations[code]
                                                .reviewed_at,
                                        ) ?? '',
                                })
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>
            <div class="grid gap-6 lg:col-start-2 lg:row-start-2">
                <Card v-if="question">
                    <CardHeader>
                        <CardTitle>{{ t('About this question') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl
                            class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm"
                        >
                            <dt class="text-muted-foreground">
                                {{ t('Source') }}
                            </dt>
                            <dd class="min-w-0 break-words">
                                {{
                                    t(
                                        sourceLabels[question.source] ??
                                            question.source,
                                    )
                                }}
                                <span
                                    v-if="question.source_ref"
                                    class="block font-mono text-xs text-muted-foreground"
                                    >{{ question.source_ref }}</span
                                >
                            </dd>
                            <template v-if="question.license">
                                <dt class="text-muted-foreground">
                                    {{ t('License') }}
                                </dt>
                                <dd>{{ question.license }}</dd>
                            </template>
                            <dt class="text-muted-foreground">
                                {{ t('Played') }}
                            </dt>
                            <dd>
                                <template v-if="question.times_asked">
                                    {{
                                        t(':rate right of :count', {
                                            rate: percent(
                                                question.correct_rate,
                                            ),
                                            count: question.times_asked,
                                        })
                                    }}
                                </template>
                                <template v-else>{{ t('Not yet') }}</template>
                            </dd>
                            <dt class="text-muted-foreground">
                                {{ t('Updated') }}
                            </dt>
                            <dd>{{ formatDate(question.updated_at) }}</dd>
                        </dl>
                        <p
                            v-if="question.edited_at && question.source_ref"
                            class="mt-4 flex gap-2 rounded-md bg-muted p-3 text-xs text-muted-foreground"
                        >
                            <Info class="size-4 shrink-0" />
                            {{
                                t(
                                    'Edited here on :date. Imports of the question files no longer change it.',
                                    {
                                        date:
                                            formatDate(question.edited_at) ??
                                            '',
                                    },
                                )
                            }}
                        </p>
                        <p
                            v-else-if="question.source_ref"
                            class="mt-4 flex gap-2 rounded-md bg-muted p-3 text-xs text-muted-foreground"
                        >
                            <Info class="size-4 shrink-0" />
                            {{
                                t(
                                    'Comes from a question file. Once you change its wording here, imports leave it alone.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Button
                    v-if="question?.can_delete"
                    type="button"
                    variant="outline"
                    class="text-destructive"
                    @click="confirmingDelete = true"
                >
                    <Trash2 />
                    {{ t('Delete question') }}
                </Button>
            </div>
        </div>

        <!-- Stays in view while scrolling, also on phones. -->
        <div
            class="sticky bottom-0 z-20 -mx-4 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <div class="flex items-center justify-end gap-3">
                <span
                    v-if="form.isDirty"
                    class="mr-auto text-sm text-muted-foreground"
                    >{{ t('Unsaved changes') }}</span
                >
                <Button variant="outline" as-child>
                    <Link :href="index()">{{ t('Cancel') }}</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    <Save v-else />
                    {{ question ? t('Save') : t('Add question') }}
                </Button>
            </div>
        </div>
    </form>

    <Dialog v-model:open="confirmingDelete">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('Delete this question?') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t(
                            'It hasn’t been played yet, so nothing else is affected.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="outline">{{ t('Cancel') }}</Button>
                </DialogClose>
                <Button variant="destructive" @click="remove">
                    <Trash2 />
                    {{ t('Delete') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
