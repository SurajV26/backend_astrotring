<?php

namespace App\Services;

use App\Models\ZodiacSign;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ZodiacForecast
{
    public const TYPES = ['today', 'yesterday', 'tomorrow', 'daily', 'weekly', 'monthly', 'yearly'];
    public const SIGNS = ['aries', 'taurus', 'gemini', 'cancer', 'leo', 'virgo', 'libra', 'scorpio', 'sagittarius', 'capricorn', 'aquarius', 'pisces'];
    public const ID_BASE = 1000000000;

    private const SECTIONS = [
        'career' => 'Saturn',
        'finance' => 'Jupiter',
        'love' => 'Venus',
        'health' => 'Mars',
        'family' => 'Moon',
        'students' => 'Mercury',
    ];

    private const THEMES = [
        1 => 'personal priorities', 'resources and values', 'communication', 'home life',
        'creativity', 'daily routines', 'partnerships', 'shared responsibilities',
        'learning', 'public responsibilities', 'community', 'rest and reflection',
    ];

    // Combine the section planet with the opening Moon house to select advice.
    private const GUIDANCE = [
        'career' => [
            'Pick a decision you can move forward yourself. A small working example gives your team something concrete to discuss.',
            'Check what the next handover needs. A clear owner and a realistic deadline can save a colleague a second round of work.',
            'Ask a colleague how they understood the brief. Comparing notes early can uncover two different ideas of what a finished job looks like.',
            'Give unfinished work a quiet review. A detail buried in an old message may help you frame a better question for your next meeting.',
        ],
        'finance' => [
            'Put a price on one idea you would like to try. Seeing the full cost on paper can help you decide how much room to give it.',
            'Look through the payments that repeat each month. A charge you barely notice is worth comparing with what you actually use.',
            'If an expense involves someone else, agree on each share in advance. Write down the amount so neither person has to rely on memory.',
            'Give an impulse purchase a little breathing room. Coming back to it with your spending plan in hand can clarify whether it still appeals.',
        ],
        'love' => [
            'Suggest something specific you would enjoy doing together. Even a short walk leaves room for a conversation that feels less rehearsed.',
            'Notice the small ways someone shows up for you. Naming one particular gesture makes your appreciation feel personal.',
            'Ask a question you have been trying to answer by guessing. Leave enough space for the other person to surprise you.',
            'A familiar subject may carry more feeling than its words suggest. Reflect back what you heard before offering your own view.',
        ],
        'health' => [
            'Choose a pace that leaves some energy for the rest of your plans. A short stretch or walk can fit between two busy parts of the day.',
            'Look for one routine that feels easy to repeat. Keeping time free for a meal or a break gives the day a steadier rhythm.',
            'Notice where back-to-back conversations leave you little time to recharge. Give yourself a brief pause before the next demand on your attention.',
            'Pay attention to the point when an ordinary task starts to feel tiring. A quieter evening or a shorter to-do list may suit your energy.',
        ],
        'family' => [
            'Take the lead on one manageable household task. Be clear about what you can handle so everyone knows where a hand is still needed.',
            'Talk through a practical detail such as a visit or a shared errand. Agreeing on a time can take pressure off the person coordinating it.',
            'Invite the quieter person into a family conversation. Their account of a shared situation may add a detail the others missed.',
            'If a familiar disagreement comes up, ask what felt difficult the last time. An unhurried answer can make it easier to agree on a small change.',
        ],
        'students' => [
            'Start with one practice question before looking at your notes. The first step that slows you down shows you where to spend your next study session.',
            'Divide a large topic into a few small skills. Work through an example of each and mark the one you still need to practise.',
            'Compare your reasoning with a study partner. Explaining why you chose an answer can uncover a gap that a correct guess would hide.',
            'Return to a problem that felt confusing on the first pass. Sketch it in your own words before opening the worked solution.',
        ],
    ];

    private const PERIOD_ACTIONS = [
        'yesterday' => [
            'career' => 'Review a task that ran over and note where the delay began.',
            'finance' => 'Check a recent receipt against what you expected to spend.',
            'love' => 'Think back to a moment when you felt heard and what made it possible.',
            'health' => 'Recall when your energy dipped and consider what your schedule looked like then.',
            'family' => 'Think of a household task someone handled and thank them for that specific effort.',
            'students' => 'Try recalling the main idea from your last study session before reopening the chapter.',
        ],
        'today' => [
            'career' => 'Choose one deliverable you can finish today and tell anyone waiting when to expect it.',
            'finance' => 'Set a spending limit for the next purchase on your list.',
            'love' => 'Make room for one uninterrupted conversation and ask how the other person is doing.',
            'health' => 'Put a short break between your two busiest commitments today.',
            'family' => 'Ask which household task would make the biggest difference if you took it on today.',
            'students' => 'Give your hardest topic one focused session and end with a question you can revisit.',
        ],
        'tomorrow' => [
            'career' => 'Prepare the material for your first commitment tomorrow so you can start with a decision.',
            'finance' => 'Before tomorrow, check what payments are due and what you need to set aside.',
            'love' => 'Suggest a time tomorrow when a conversation can happen without rushing.',
            'health' => 'Leave a little room in tomorrow’s schedule for an unhurried meal or gentle movement.',
            'family' => 'Confirm tomorrow’s plans with anyone whose schedule they affect.',
            'students' => 'Set out one practice problem tonight so the next study session has a clear starting point.',
        ],
        'weekly' => [
            'career' => 'Set an early-week milestone and leave time before Sunday to review what still needs a decision.',
            'finance' => 'Compare this week’s planned expenses with actual spending before making weekend purchases.',
            'love' => 'Plan one time to reconnect this week and ask what would make it enjoyable for both of you.',
            'health' => 'Spread demanding activities across the week and keep a quieter slot for recovery.',
            'family' => 'Agree on this week’s shared errands and choose who will handle each one.',
            'students' => 'Alternate revision with practice this week and use a short quiz to check your progress.',
        ],
        'monthly' => [
            'career' => 'Choose a result to work toward this month and review it halfway through with someone who can give feedback.',
            'finance' => 'Map the month’s fixed bills before setting a target for savings or optional spending.',
            'love' => 'Use a quiet moment this month to discuss a recurring expectation and agree on one change you can try.',
            'health' => 'Choose one routine for the month and check after two weeks whether it fits your actual schedule.',
            'family' => 'Look ahead at this month’s visits and responsibilities so busy dates can be shared more evenly.',
            'students' => 'Pick a skill to strengthen this month and compare a fresh practice attempt with your first one.',
        ],
        'yearly' => [
            'career' => 'Identify a skill that would expand your options this year and pick a project where you can practise it.',
            'finance' => 'List the year’s larger expenses and review how your savings plan is keeping up each quarter.',
            'love' => 'Talk about what you want to make more time for this year and put one shared plan on the calendar.',
            'health' => 'Build a routine that can survive busy seasons and revisit it when your responsibilities shift.',
            'family' => 'Discuss any longer-term care or household commitments early and check who can realistically contribute.',
            'students' => 'Work back from the year’s major exams or projects and set a checkpoint for each stage of preparation.',
        ],
    ];

    private const CAUTIONS = [
        'A quick yes can leave you responsible for more than you intended. Ask what is involved before promising a result.',
        'Small obligations are easy to overlook when several things are due together. Check the dates and leave a little space between commitments.',
        'A short message can sound firmer than its sender intended. Confirm the detail directly before acting on an assumption.',
        'An unresolved conversation may colour how a new request feels. Give yourself time to separate the immediate question from the older concern.',
    ];

    public function generate(ZodiacSign $sign, string $type, CarbonImmutable $today, bool $detail = false): array
    {
        [$start, $end] = match ($type) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'tomorrow' => [$today->addDay(), $today->addDay()],
            'weekly' => [$today->startOfWeek(CarbonImmutable::MONDAY), $today->endOfWeek(CarbonImmutable::SUNDAY)],
            'monthly' => [$today->startOfMonth(), $today->endOfMonth()],
            'yearly' => [$today->startOfYear(), $today->endOfYear()],
            default => [$today, $today],
        };

        $opening = $this->positions($start);
        $singleDay = $start->isSameDay($end);
        $closing = $singleDay ? $opening : $this->positions($end);
        $period = $start->format('M j, Y') . ($singleDay ? '' : ' to ' . $end->format('M j, Y'));
        $signNumber = array_search($sign->slug, self::SIGNS, true) + 1;
        $house = static fn (int $position) => ($position - $signNumber + 12) % 12 + 1;
        $dates = array_values(array_unique([$start->day, $end->day]));
        $moon = $house($opening['Moon']);
        $actions = self::PERIOD_ACTIONS[$type === 'daily' ? 'today' : $type];
        $sections = [];
        foreach (self::SECTIONS as $section => $planet) {
            $first = $house($opening[$planet]);
            $last = $house($closing[$planet]);
            $theme = self::THEMES[$first];
            $planetSign = ucfirst(self::SIGNS[$opening[$planet] - 1]);
            $text = "{$planet} in {$planetSign} highlights {$theme} for {$sign->name}";
            $text .= $singleDay ? '. ' : ' at the start of the period. ';
            if (!$singleDay && $first !== $last) {
                $text .= 'By ' . $end->format('M j') . ', the focus turns to ' . self::THEMES[$last] . '. ';
            }
            $text .= $actions[$section];
            $text .= ' ' . self::GUIDANCE[$section][($last + $moon - 2) % 4];
            $sections[$section] = ['text' => $text, 'dates' => $dates];
        }

        $sun = $house($opening['Sun']);
        $saturn = $house($closing['Saturn']);
        $palette = ['Red', 'Cream', 'Green', 'Silver', 'Gold', 'Olive', 'Pink', 'Maroon', 'Yellow', 'Brown', 'Blue', 'Lavender'];
        $author = $detail ? null : ['id' => null, 'name' => null, 'email' => null];
        $row = [
            'id' => self::ID_BASE + $sign->id * 10 + array_search($type, self::TYPES, true),
            'type' => $type,
            'title' => $sign->name . ' ' . ucfirst($type) . ' Horoscope',
            'overview' => "{$period}. The Moon in " . ucfirst(self::SIGNS[$opening['Moon'] - 1])
                . " draws {$sign->name}'s attention to " . self::THEMES[$moon] . ($singleDay ? '.' : ' as the period opens.'),
            'zodiac' => $detail ? $sign : $sign->only(['id', 'name', 'slug', 'icon']),
            'sections' => $sections,
            'warning' => 'Allow extra time for matters involving ' . self::THEMES[$saturn] . '. ' . self::CAUTIONS[($saturn - 1) % 4],
            'lucky' => [
                'numbers' => array_values(array_unique([$moon, $sun, $moon + $sun])),
                'colors' => array_values(array_unique([$palette[$moon - 1], $palette[$sun - 1]])),
            ],
            'created_by' => $author,
            'modified_by' => $author,
            'created_at' => $start->startOfDay(),
            'updated_at' => $start->startOfDay(),
        ];
        if (!$detail) {
            $row['status'] = true;
        }
        return $row;
    }

    private function positions(CarbonImmutable $day): array
    {
        $date = $day->toDateString();
        $base = rtrim(config('services.jhora.base_url'), '/');
        $key = 'jhora.gochar.delhi.v1.' . md5($base) . '.' . $date;
        return Cache::store('file')->remember($key, 21600, function () use ($date, $base) {
            try {
                $response = Http::acceptJson()->connectTimeout(4)->timeout(12)->post($base . '/gochar', [
                    'date' => $date, 'latitude' => 28.6139, 'longitude' => 77.2090, 'timezone' => 5.5,
                ]);
            } catch (ConnectionException $exception) {
                report($exception);
                $this->unavailable();
            }
            $planets = $response->json('gochar.planets');
            if (!$response->successful() || $response->json('gochar.date') !== $date || !is_array($planets)) {
                $this->unavailable();
            }
            $positions = [];
            foreach ($planets as $planet) {
                $number = $planet['sign_number'] ?? null;
                if (is_string($planet['planet'] ?? null) && is_int($number) && $number >= 1 && $number <= 12) {
                    $positions[$planet['planet']] = $number;
                }
            }
            if (array_diff(['Sun', ...array_values(self::SECTIONS)], array_keys($positions))) {
                $this->unavailable();
            }
            return $positions;
        });
    }

    private function unavailable(): never
    {
        throw new HttpResponseException(response()->json([
            'status' => false, 'message' => 'Transit data is temporarily unavailable.',
        ], 503));
    }
}
