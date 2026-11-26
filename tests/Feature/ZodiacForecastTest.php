<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Database\Seeders\CreateDefaultZodiacSignsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZodiacForecastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.stores.file.driver' => 'array',
        ]);
        Cache::forgetDriver('file');
        (require database_path('migrations/' . basename(glob(database_path('migrations/*create_zodiac_signs_table.php'))[0])))->up();
        (new CreateDefaultZodiacSignsSeeder())->run();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_all_signs_filters_details_and_calendar_boundaries(): void
    {
        Http::fake(function (Request $request) {
            $planets = [];
            foreach (['Moon', 'Mars', 'Mercury', 'Venus', 'Saturn', 'Sun', 'Jupiter'] as $index => $name) {
                $planets[] = ['planet' => $name, 'sign_number' => $index + 1];
            }
            return Http::response(['gochar' => ['date' => $request['date'], 'planets' => $planets]]);
        });
        CarbonImmutable::setTestNow('2026-12-31T18:29:59Z');
        $all = $this->getJson('/api/horoscopes')->assertOk()->assertJsonPath('count', 84)->json('data');
        self::assertCount(84, array_unique(array_column($all, 'id')));
        foreach ($all as $reading) {
            self::assertGreaterThanOrEqual(2, count(array_unique($reading['lucky']['numbers'])));
            self::assertGreaterThanOrEqual(3, substr_count($reading['warning'], '.'));
            foreach ($reading['sections'] as $section) {
                self::assertGreaterThanOrEqual(4, substr_count($section['text'], '.'));
            }
        }
        foreach (array_unique(array_column(array_column($all, 'zodiac'), 'slug')) as $slug) {
            $periods = array_filter($all, fn ($row) => $row['zodiac']['slug'] === $slug && $row['type'] !== 'daily');
            foreach (array_keys($all[0]['sections']) as $section) {
                $texts = array_map(fn ($row) => $row['sections'][$section]['text'], $periods);
                self::assertCount(6, array_unique($texts), "{$slug} {$section}");
            }
        }

        $get = fn ($type) => $this->getJson('/api/horoscopes?zodiac_id=1&type=' . $type)
            ->assertOk()->assertJsonPath('count', 1)->json('data.0');
        $today = $get('today');
        self::assertStringStartsWith('Dec 31, 2026.', $today['overview']);
        self::assertSame($today['sections'], $get('daily')['sections']);
        $this->getJson('/api/horoscopes/' . $today['id'])->assertOk()->assertJsonPath('data.sections', $today['sections']);
        $week = $get('weekly');
        CarbonImmutable::setTestNow('2026-12-31T18:30:00Z');
        self::assertSame($today['overview'], $get('yesterday')['overview']);
        self::assertStringStartsWith('Jan 1, 2027.', $get('today')['overview']);
        self::assertStringStartsWith('Jan 2, 2027.', $get('tomorrow')['overview']);
        self::assertStringStartsWith('Jan 1, 2027 to Jan 31, 2027.', $get('monthly')['overview']);
        self::assertStringStartsWith('Jan 1, 2027 to Dec 31, 2027.', $get('yearly')['overview']);
        self::assertSame($week, $get('weekly'));
        CarbonImmutable::setTestNow('2027-01-03T18:30:00Z');
        self::assertStringStartsWith('Jan 4, 2027 to Jan 10, 2027.', $get('weekly')['overview']);
    }

    public function test_incomplete_transit_response_returns_json_error(): void
    {
        Http::fake(fn () => Http::response(['gochar' => []]));
        $this->getJson('/api/horoscopes?type=today')->assertStatus(503)->assertJsonPath('status', false);
    }
}
