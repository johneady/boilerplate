<?php

namespace Database\Seeders;

use App\Auth\Role;
use App\Models\Perfume;
use App\Models\User;
use App\Perfumes\PerfumeImporter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sillage's catalogue and six months of community history.
 *
 * The catalogue goes in through the real importer, twice, at the dates it
 * happened: the original Lovable export, then an August refresh. The admin's
 * import history is therefore exactly what running those files leaves.
 *
 * Members, follows and daily views are generated from a fixed seed with a
 * growth curve, so the dashboard and the public stats have a believable
 * story and every demo instance looks the same. No factories: this runs in
 * the --no-dev image (.ai/rules/seeders.md).
 */
class PerfumeSeeder extends Seeder
{
    private const int MEMBERS = 260;

    private const int HISTORY_DAYS = 180;

    /**
     * Relative popularity by slug: how often a perfume is followed and
     * viewed compared with an unlisted one (weight 1).
     *
     * @var array<string, int>
     */
    private const array POPULARITY = [
        'maison-francis-kurkdjian-baccarat-rouge-540' => 9,
        'dior-sauvage' => 8,
        'creed-aventus' => 7,
        'le-labo-santal-33' => 7,
        'chanel-n5' => 6,
        'yves-saint-laurent-black-opium' => 6,
        'tom-ford-tobacco-vanille' => 5,
        'chanel-coco-mademoiselle' => 5,
        'parfums-de-marly-layton' => 5,
        'yves-saint-laurent-libre' => 4,
        'tom-ford-oud-wood' => 4,
        'maison-margiela-replica-by-the-fireplace' => 4,
        'lancome-la-vie-est-belle' => 3,
        'guerlain-shalimar' => 3,
        'byredo-gypsy-water' => 3,
        'jo-malone-london-wood-sage-sea-salt' => 3,
    ];

    /**
     * @var list<string>
     */
    private const array FIRST_NAMES = [
        'Amélie', 'Noor', 'Sofia', 'Marcus', 'Yuki', 'Isabel', 'Theo', 'Leila', 'Hannah', 'Rafael', 'Chloé', 'Omar',
        'Ingrid', 'Priya', 'Julien', 'Mei', 'Daniel', 'Aisha', 'Lucas', 'Freya', 'Mateo', 'Elena', 'Sam', 'Nadia',
    ];

    /**
     * @var list<string>
     */
    private const array LAST_NAMES = [
        'Laurent', 'Haddad', 'Rossi', 'Okafor', 'Tanaka', 'García', 'Novak', 'Karimi', 'Schmidt', 'Silva', 'Dubois',
        'Mansour', 'Berg', 'Sharma', 'Moreau', 'Chen', 'Kowalski', 'Bello', 'Martin', 'Lindqvist',
    ];

    public function run(): void
    {
        if (app()->environment('production') || Perfume::query()->exists()) {
            return;
        }

        mt_srand(540);

        $admin = User::query()->where('email', config('first.user.email'))->first();
        $today = now()->toImmutable()->startOfDay();

        try {
            Date::setTestNow($today->subDays(120)->setTime(10, 14));
            app(PerfumeImporter::class)->import(database_path('seeders/data/lovable-export.csv'), 'lovable-export.csv', $admin);

            Date::setTestNow($today->subDays(34)->setTime(9, 2));
            app(PerfumeImporter::class)->import(database_path('seeders/data/refresh-2026-08.csv'), 'refresh-2026-08.csv', $admin);
        } finally {
            Date::setTestNow();
        }

        $perfumes = Perfume::query()->get(['id', 'slug', 'created_at']);
        $weighted = array_values($perfumes->flatMap(fn (Perfume $perfume) => array_fill(0, self::POPULARITY[$perfume->slug] ?? 1, $perfume))->all());

        DB::transaction(function () use ($perfumes, $weighted, $today): void {
            $this->seedMembersAndFollows($weighted, $today);
            $this->seedViews(array_values($perfumes->all()), $today);
            $this->seedDemoMemberFollows();
        });
    }

    /**
     * Members join along a rising curve (more recently, more often), and
     * each follows a handful of perfumes, favouring the popular ones.
     *
     * @param  list<Perfume>  $weighted
     */
    private function seedMembersAndFollows(array $weighted, CarbonImmutable $today): void
    {
        // Hashed once at the minimum cost: nobody signs in as these accounts.
        $password = Hash::make(Str::random(32), ['rounds' => 4]);
        $follows = [];

        for ($i = 0; $i < self::MEMBERS; $i++) {
            // sqrt skews join dates toward the present: a community that is growing.
            $daysAgo = (int) floor((1 - sqrt(mt_rand() / mt_getrandmax())) * self::HISTORY_DAYS);
            $joinedAt = $today->subDays($daysAgo)->addMinutes(mt_rand(420, 1380));
            $first = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)];
            $last = self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];

            $userId = DB::table('users')->insertGetId([
                'name' => "{$first} {$last}",
                'email' => Str::slug("{$first}.{$last}", '.').".{$i}@members.sillage.example",
                'email_verified_at' => $joinedAt,
                'password' => $password,
                'role' => Role::User->value,
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ]);

            $chosen = [];

            for ($f = mt_rand(1, 7); $f > 0; $f--) {
                $perfume = $weighted[mt_rand(0, count($weighted) - 1)];
                $followedAt = CarbonImmutable::parse(max($joinedAt, $perfume->created_at))->addHours(mt_rand(0, 24 * 20));

                if (isset($chosen[$perfume->id]) || $followedAt->isFuture()) {
                    continue;
                }

                $chosen[$perfume->id] = true;
                $follows[] = ['user_id' => $userId, 'perfume_id' => $perfume->id, 'created_at' => $followedAt];
            }
        }

        foreach (array_chunk($follows, 200) as $chunk) {
            DB::table('perfume_follows')->insert($chunk);
        }
    }

    /**
     * Ninety days of daily views per perfume: popularity times a slow upward
     * trend, busier at weekends, with day-to-day noise.
     *
     * @param  list<Perfume>  $perfumes
     */
    private function seedViews(array $perfumes, CarbonImmutable $today): void
    {
        $rows = [];

        for ($day = 89; $day >= 0; $day--) {
            $date = $today->subDays($day);
            $trend = 1 + (89 - $day) / 60;
            $weekend = $date->isWeekend() ? 1.35 : 1.0;

            foreach ($perfumes as $perfume) {
                if ($date->lt(CarbonImmutable::parse($perfume->created_at)->startOfDay())) {
                    continue;
                }

                $popularity = self::POPULARITY[$perfume->slug] ?? 1;
                $views = (int) round((3 + $popularity * 4) * $trend * $weekend * (mt_rand(70, 130) / 100));

                $rows[] = ['perfume_id' => $perfume->id, 'viewed_on' => $date->toDateString(), 'views' => $views];
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('perfume_views')->insert($chunk);
        }
    }

    /**
     * Give the one-click member account a small collection, so its
     * dashboard shows what following looks like.
     */
    private function seedDemoMemberFollows(): void
    {
        $member = User::query()->where('email', 'test@example.com')->first();

        if ($member === null) {
            return;
        }

        $slugs = ['guerlain-shalimar', 'le-labo-santal-33', 'diptyque-philosykos', 'frederic-malle-portrait-of-a-lady', 'tom-ford-tobacco-vanille'];

        foreach (Perfume::query()->whereIn('slug', $slugs)->get() as $index => $perfume) {
            $member->followedPerfumes()->syncWithoutDetaching([$perfume->id => ['created_at' => now()->subDays(40 - $index * 7)]]);
        }
    }
}
