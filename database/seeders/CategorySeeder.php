<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The starter categories, keyed by slug: [icon, colour, Greek name, English name].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const CATEGORIES = [
        'general' => ['lightbulb', '#f59e0b', 'Γενικές Γνώσεις', 'General Knowledge'],
        'greek-history' => ['landmark', '#b45309', 'Ελληνική Ιστορία', 'Greek History'],
        'mythology' => ['zap', '#7c3aed', 'Μυθολογία', 'Mythology'],
        'geography' => ['globe', '#0284c7', 'Γεωγραφία', 'Geography'],
        'science' => ['flask-conical', '#059669', 'Επιστήμη', 'Science'],
        'sports' => ['trophy', '#dc2626', 'Αθλητισμός', 'Sports'],
        'music' => ['music', '#db2777', 'Μουσική', 'Music'],
        'film-tv' => ['clapperboard', '#4f46e5', 'Σινεμά & Τηλεόραση', 'Film & TV'],
        'food' => ['utensils', '#ea580c', 'Φαγητό & Κουζίνα', 'Food & Cuisine'],
        'literature' => ['book-open', '#0d9488', 'Λογοτεχνία', 'Literature'],
        'language' => ['languages', '#2563eb', 'Ελληνική Γλώσσα', 'Greek Language'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $order = 0;

        foreach (self::CATEGORIES as $slug => [$icon, $color, $el, $en]) {
            $category = Category::query()->updateOrCreate(['slug' => $slug], [
                'icon' => $icon,
                'color' => $color,
                'sort_order' => $order++,
                'is_active' => true,
            ]);

            $category->translations()->updateOrCreate(['locale' => 'el'], ['name' => $el]);
            $category->translations()->updateOrCreate(['locale' => 'en'], ['name' => $en]);
        }
    }
}
