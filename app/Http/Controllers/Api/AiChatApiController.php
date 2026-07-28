<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAstrologyChart;
use App\Models\User;
use App\Helpers\AstrologyChartExtractor;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AiAstrologer;
use App\Models\AiAstrologerExpertise;
use App\Models\AiAstrologerExpertiseQuestion;
use App\Models\AiChatTransaction;
use App\Services\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiChatApiController extends Controller
{
    private OpenAiService $openAiService;

    public function __construct(OpenAiService $openAiService)
    {
        $this->openAiService = $openAiService;
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = AiChatSession::with(['astrologer', 'expertise'])
            ->withCount('messages')
            ->where('user_id', $request->user()->id)
            ->latest('last_message_at')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $sessions,
        ]);
    }

    public function history($sessionId, Request $request): JsonResponse
    {
        $session = AiChatSession::with([
                'astrologer',
                'expertise',
                'messages' => fn($query) => $query->orderBy('id', 'asc'),
            ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($sessionId);

        return response()->json([
            'status' => true,
            'session_started_at' => $session->started_at,
            'session_closed_at' => $session->closed_at,
            'data' => $session,
        ]);
    }

    public function startSession(Request $request): JsonResponse
    {
        $request->validate([
            'astrologer_id' => 'nullable|exists:ai_astrologers,id',
            'astrologer_slug' => 'nullable|exists:ai_astrologers,slug',
            'expertise_id' => 'nullable|exists:ai_astrologer_expertises,id',
            'expertise_slug' => 'nullable|exists:ai_astrologer_expertises,slug',
        ]);

        if (!$request->filled('astrologer_id') && !$request->filled('astrologer_slug')) {
            return $this->errorResponse('Astrologer is required.', 422);
        }

        if (!$request->filled('expertise_id') && !$request->filled('expertise_slug')) {
            return $this->errorResponse('Expertise is required.', 422);
        }

        $user = $request->user();

        $astrologer = $this->resolveAstrologer($request);

        if (!$astrologer) {
            return $this->errorResponse('Selected astrologer not found.', 422);
        }

        $expertise = $this->resolveExpertise($request, $astrologer->id);

        if (!$expertise) {
            return $this->errorResponse('Selected expertise not found.', 422);
        }

        $session = AiChatSession::where('user_id', $user->id)
            ->where('astrologer_id', $astrologer->id)
            ->where('expertise_id', $expertise->id)
            ->first();

        if ($session) {
            return $this->resumeSession($session);
        }

        return $this->createNewSession($user, $astrologer, $expertise);
    }

    private function resolveAstrologer(Request $request): ?AiAstrologer
    {
        return AiAstrologer::where('status', true)
            ->when(
                $request->filled('astrologer_id'),
                fn($q) => $q->where('id', $request->astrologer_id),
                fn($q) => $q->where('slug', $request->astrologer_slug)
            )
            ->first();
    }

    private function resolveExpertise(Request $request, int $astrologerId): ?AiAstrologerExpertise
    {
        return AiAstrologerExpertise::where('ai_astrologer_id', $astrologerId)
            ->where('status', true)
            ->when(
                $request->filled('expertise_id'),
                fn($q) => $q->where('id', $request->expertise_id),
                fn($q) => $q->where('slug', $request->expertise_slug)
            )
            ->first();
    }

    private function resumeSession(AiChatSession $session): JsonResponse
    {
        $session->update([
            'status' => 'active',
            'closed_at' => null,
            'last_message_at' => now(),
        ]);

        $session->refresh()->load(['astrologer', 'expertise', 'messages']);
        $session->questions = $this->getRemainingQuestions($session);

        return response()->json([
            'status' => true,
            'message' => 'Previous session resumed successfully.',
            'session_id' => $session->id,
            'data' => $session,
        ]);
    }

    private function createNewSession(User $user, AiAstrologer $astrologer, AiAstrologerExpertise $expertise): JsonResponse
    {
        $session = AiChatSession::create([
            'user_id' => $user->id,
            'astrologer_id' => $astrologer->id,
            'expertise_id' => $expertise->id,
            'paid_messages' => 0,
            'total_amount' => 0,
            'started_at' => now(),
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        $session->load(['astrologer', 'expertise']);

        $this->generateInitialConversation($user, $session);

        $session->refresh()->load(['astrologer', 'expertise', 'messages']);

        $questions = AiAstrologerExpertiseQuestion::where('expertise_id', $session->expertise_id)
            ->select('id', 'question')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Chat session started successfully.',
            'session_id' => $session->id,
            'data' => $session,
            'questions' => $questions,
        ], 201);
    }

    private function generateInitialConversation(User $user, AiChatSession $session): void
    {
        try {
            $session->loadMissing(['astrologer', 'expertise']);

            $title = $session->astrologer->gender === 'female' ? 'Ms.' : 'Mr.';

            $reply = "Hello {$user->name}! I am {$title} {$session->astrologer->name}, your Vedic astrologer. Please select one of the questions below or type your own question to begin.";

            AiChatMessage::create([
                'session_id' => $session->id,
                'question_id' => null,
                'sender' => 'assistant',
                'message' => $reply,
                'model' => 'system',
                'charged_amount' => 0,
                'is_free' => false,
            ]);

            $session->update(['last_message_at' => now()]);

        } catch (\Throwable $e) {
            Log::error('AI_INITIAL_GREETING', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|exists:ai_chat_sessions,id',
            'question_id' => 'nullable|exists:ai_astrologer_expertise_questions,id',
            'message' => 'nullable|string|max:5000',
        ]);

        if (!$request->filled('question_id') && !$request->filled('message')) {
            return $this->errorResponse('Question is required.', 422);
        }

        DB::beginTransaction();

        try {
            $user = $request->user();

            $session = AiChatSession::with(['astrologer', 'expertise', 'messages'])
                ->where('user_id', $user->id)
                ->findOrFail($request->session_id);

            if ($session->status !== 'active') {
                DB::rollBack();
                return $this->errorResponse('This chat session is closed.', 422, 'session_closed');
            }

            $isDatabaseQuestion = $request->filled('question_id');

            [$currentQuestion, $questionId, $failure] = $isDatabaseQuestion
                ? $this->resolveDatabaseQuestion($session, (int) $request->question_id)
                : $this->resolveFreeTextQuestion($request->message);

            if ($failure) {
                DB::rollBack();
                return $this->errorResponse($failure, 422);
            }

            $freeMessages = (int) config('services.ai_chat.free_messages', 0);
            $isFree = $session->free_messages_used < $freeMessages;
            $chatPrice = $isFree ? 0 : (float) config('services.ai_chat.price');

            $wallet = null;

            if (!$isFree) {
                $wallet = $user->wallet;

                if (!$wallet || $wallet->balance < $chatPrice) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Insufficient wallet balance. Please recharge your wallet.',
                        422,
                        'insufficient_balance'
                    );
                }
            }

            $userMessage = AiChatMessage::create([
                'session_id' => $session->id,
                'question_id' => $questionId,
                'sender' => 'user',
                'message' => $currentQuestion,
                'charged_amount' => $chatPrice,
                'is_free' => $isFree,
                'model' => 'gpt-4o-mini',
            ]);

            if (!$isFree) {
                $this->debitWallet($wallet, $user, $session, $userMessage, $chatPrice);
            }

            $isFree
                ? $session->increment('free_messages_used')
                : $session->increment('paid_messages');

            if (!$isFree) {
                $session->increment('total_amount', $chatPrice);
            }

            $systemPrompt = $isDatabaseQuestion
                ? $this->buildQuestionPrompt($session)
                : $this->buildChatPrompt($session);

            $messages = $this->buildAiMessagePayload(
                $systemPrompt,
                $session,
                $currentQuestion,
                $isDatabaseQuestion
            );

            Log::info('AI_CHAT_REQUEST_PAYLOAD', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'expertise' => $session->expertise->slug,
                'is_database_question' => $isDatabaseQuestion,
                'messages' => $messages,
            ]);

            try {
                $reply = $this->openAiService->chat($messages);
                $reply = $this->sanitizeReply($reply);
            } catch (\Throwable $e) {
                DB::rollBack();

                Log::error('AI_SERVICE_ERROR', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);

                return $this->errorResponse(
                    'AI service is temporarily unavailable.',
                    503,
                    'ai_error'
                );
            }

            AiChatMessage::create([
                'session_id' => $session->id,
                'question_id' => null,
                'sender' => 'assistant',
                'message' => $reply,
                'is_free' => $isFree,
                'charged_amount' => 0,
                'model' => 'gpt-4o-mini',
            ]);

            $session->update(['last_message_at' => now()]);

            DB::commit();

            $response = [
                'status' => true,
                'reply' => $reply,
                'remaining_questions' => $this->getRemainingQuestions($session),
            ];

            // Only exposed when APP_DEBUG=true in .env — never in production.
            if (config('app.debug')) {
                $response['debug_payload'] = $messages;
            }

            return response()->json($response);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('AI_CHAT_ERROR', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse(
                'Something went wrong. Please try again.',
                500,
                'server_error'
            );
        }
    }

    /**
     * @return array{0: string, 1: int|null, 2: string|null} [question text, question id, error message]
     */
    private function resolveDatabaseQuestion(AiChatSession $session, int $questionId): array
    {
        $question = AiAstrologerExpertiseQuestion::where('expertise_id', $session->expertise_id)
            ->where('id', $questionId)
            ->first();

        if (!$question) {
            return ['', null, 'Selected question not found.'];
        }

        return [$question->question, $question->id, null];
    }

    /**
     * @return array{0: string, 1: int|null, 2: string|null}
     */
    private function resolveFreeTextQuestion(?string $message): array
    {
        $trimmed = trim((string) $message);

        if ($trimmed === '') {
            return ['', null, 'Question cannot be empty.'];
        }

        return [$trimmed, null, null];
    }

    private function debitWallet($wallet, User $user, AiChatSession $session, AiChatMessage $userMessage, float $chatPrice): void
    {
        $before = $wallet->balance;

        $wallet->update([
            'balance' => $before - $chatPrice,
            'total_spent' => $wallet->total_spent + $chatPrice,
        ]);

        $after = $wallet->fresh()->balance;

        AiChatTransaction::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'message_id' => $userMessage->id,
            'amount' => $chatPrice,
            'balance_before' => $before,
            'balance_after' => $after,
            'type' => 'debit',
            'remark' => 'AI Astrology Question',
        ]);
    }

    private function buildAiMessagePayload(
        string $systemPrompt,
        AiChatSession $session,
        string $currentQuestion,
        bool $isDatabaseQuestion
    ): array {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if ($isDatabaseQuestion) {
            $messages[] = [
                'role' => 'user',
                'content' => $currentQuestion . "\n\nIMPORTANT: Reply in maximum 2 short bullet points. No paragraphs. Maximum 35 words. Only provide detailed explanation if I explicitly ask for it.",
            ];

            return $messages;
        }

        $history = $session->messages()
            ->where('model', '!=', 'system')
            ->latest()
            ->take(8)
            ->get()
            ->reverse();

        foreach ($history as $chat) {
            $messages[] = [
                'role' => $chat->sender === 'user' ? 'user' : 'assistant',
                'content' => $chat->message,
            ];
        }

        return $messages;
    }

    private function sanitizeReply(string $reply): string
    {
        $reply = trim($reply);
        $reply = str_replace(["\r", "\n"], ' ', $reply);

        return trim(preg_replace('/\s+/', ' ', $reply));
    }

    private function getRemainingQuestions(AiChatSession $session)
    {
        $askedQuestionIds = AiChatMessage::where('session_id', $session->id)
            ->whereNotNull('question_id')
            ->pluck('question_id');

        return AiAstrologerExpertiseQuestion::where('expertise_id', $session->expertise_id)
            ->whereNotIn('id', $askedQuestionIds)
            ->select('id', 'question')
            ->orderBy('id')
            ->get();
    }

    public function closeSession($id, Request $request): JsonResponse
    {
        $session = AiChatSession::where('user_id', $request->user()->id)->findOrFail($id);

        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Session closed successfully',
        ]);
    }

    /**
     * Common non-negotiable rule set shared by both prompt builders.
     * Keeping this in one place avoids the two prompts drifting apart.
     */
    private function sharedGuardrailRules(): string
    {
        return <<<RULES
            - Answer ONLY astrology related questions.
            - Use ONLY the stored astrology profile provided below. Never invent planets, doshas, yogas, or divisional charts.
            - Do NOT perform any astrological calculation yourself (no degree math, no dasha timing math, no transit math). Only read and interpret the exact values already given to you in the STORED ASTROLOGY PROFILE below — every placement, sign, dosha name, and yoga name you mention must appear verbatim in that data.
            - Never regenerate, modify, or recalculate the horoscope. Never change Moon Sign, Sun Sign, Lagna, Nakshatra, or Pada.
            - Use ONLY the divisional charts explicitly available in the profile below. Never assume a chart that is not present.
            - If chart data needed to answer is missing, clearly say the available chart data is limited for this.
            - Never mention that you are an AI.
            - Follow Vedic Astrology principles only. Give realistic, practical guidance. Do not exaggerate predictions.
            - Reply in EXACTLY 2 bullet points. Each bullet must contain only one idea. Maximum 40 words total. No paragraphs, no numbering.
            - Give a detailed explanation ONLY if the user explicitly asks (e.g. "Explain", "Why", "How", "Detailed analysis").
        RULES;
    }

    private function buildQuestionPrompt(AiChatSession $session): string
    {
        $userProfile = $this->getUserProfileContext($session);
        $astrologyProfile = $this->getAstrologyContext($session);
        $rules = $this->sharedGuardrailRules();

        return <<<PROMPT
            You are {$session->astrologer->name}, a highly experienced Vedic Astrologer.

            CURRENT EXPERTISE
            {$session->expertise->name}

            ==================================================
            USER PROFILE (already on record — never ask for this again)

            {$userProfile}
            ==================================================
            STORED ASTROLOGY PROFILE (already calculated from the birth details above — never ask for birth details again)

            {$astrologyProfile}
            ==================================================

            IMPORTANT RULES

            {$rules}
            - Stay ONLY within this expertise ({$session->expertise->name}). If the question falls outside it, politely ask the user to start a session with the appropriate astrologer/expertise instead.
            - If this is the first interaction, briefly introduce yourself and ask: "Which language would you like to continue in?" Never ask this again after the first reply.

        PROMPT;
    }

    private function buildChatPrompt(AiChatSession $session): string
    {
        $userProfile = $this->getUserProfileContext($session);
        $astrologyProfile = $this->getAstrologyContext($session);
        $rules = $this->sharedGuardrailRules();

        return <<<PROMPT
            You are {$session->astrologer->name}, a professional Vedic Astrologer.

            CURRENT EXPERTISE
            {$session->expertise->name}

            ==================================================
            USER PROFILE (already on record — never ask for this again)

            {$userProfile}
            ==================================================
            STORED ASTROLOGY PROFILE (already calculated from the birth details above — never ask for birth details again)

            {$astrologyProfile}
            ==================================================

            IMPORTANT RULES

            {$rules}
            - Continue the existing conversation naturally. Never greet again. Never ask language again.
            - Stay ONLY within this expertise ({$session->expertise->name}). If the question falls outside it, politely ask the user to start a session with the appropriate astrologer/expertise instead.
            - Never repeat previous answers unnecessarily.
            - If the user asks about AstroTring products, suggest: https://astrotring.shop/

        PROMPT;
    }

    /**
     * Fields from the `users` table that are safe/relevant to give the AI
     * as identity + birth-detail context. Anything sensitive (password,
     * tokens, wallet internals, etc.) is deliberately excluded.
     */
    private const USER_PROFILE_FIELDS = [
        'name', 'gender', 'dob', 'date_of_birth', 'birth_date', 'time_of_birth',
        'birth_time', 'birth_place', 'place_of_birth', 'city', 'state', 'country',
        'phone', 'email',
    ];

    /**
     * Builds a compact "known facts about the user" block from the users
     * table plus the birth-detail fields already stored on the astrology
     * chart record (place, state, lat/long, report_date) — so the AI never
     * has a reason to ask for DOB/time/place again.
     */
    private function getUserProfileContext(AiChatSession $session): string
    {
        $user = $session->user ?? User::find($session->user_id);
        $chart = UserAstrologyChart::where('user_id', $session->user_id)->first();

        $profile = [];

        if ($user) {
            foreach (self::USER_PROFILE_FIELDS as $field) {
                if (isset($user->{$field}) && $user->{$field} !== null && $user->{$field} !== '') {
                    $profile[$field] = $user->{$field};
                }
            }
        }

        if ($chart) {
            foreach (['place', 'state', 'latitude', 'longitude', 'timezone', 'report_date', 'day'] as $field) {
                if (isset($chart->{$field}) && $chart->{$field} !== null && $chart->{$field} !== '') {
                    $profile['birth_' . $field] = $chart->{$field};
                }
            }

            // birth_details (actual DOB/time) lives only inside raw_data,
            // it is never part of relevant_chart, so pull it unconditionally
            // here — otherwise the AI never sees it and keeps asking for DOB.
            $rawData = json_decode((string) $chart->raw_data, true) ?? [];

            if (isset($rawData['birth_details'])) {
                $profile['birth_details'] = $rawData['birth_details'];
            }
        }

        if (empty($profile)) {
            return 'No additional user profile data on record.';
        }

        return json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Chart codes that have a dedicated, pre-parsed JSON column on
     * user_astrology_charts. Always prefer these over parsing raw_data,
     * since they're already clean/typed and guaranteed in sync.
     */
    private const CHART_COLUMN_MAP = [
        'D1'  => 'd1_chart',
        'D2'  => 'd2_chart',
        'D7'  => 'd7_chart',
        'D9'  => 'd9_chart',
        'D10' => 'd10_chart',
        'D12' => 'd12_chart',
        'D20' => 'd20_chart',
        'D24' => 'd24_chart',
        'D60' => 'd60_chart',
    ];

    /**
     * Core fields on user_astrology_charts always included regardless of
     * expertise — these are direct columns, not divisional charts.
     */
    private const CORE_PROFILE_FIELDS = [
        'ascendant',
        'sun_sign',
        'moon_sign',
        'moon_rashi',
        'nakshatra',
        'nakshatra_name',
        'nakshatra_pada',
        'nakshatra_lord',
        'doshas',
        'yogas',
        'planet_strength',
        'shadbala',
        'bhava_bala',
        'chara_karakas',
    ];

    /**
     * Builds the astrology context string using a hybrid strategy:
     *
     *   1. Core identifiers (ascendant, sun_sign, moon_rashi, nakshatra,
     *      doshas, yogas, etc.) come straight from their dedicated columns.
     *   2. For each relevant_chart code, if a dedicated column exists
     *      (D1, D2, D7, D9, D10, D12, D20, D24, D60), use it directly.
     *   3. Only codes WITHOUT a dedicated column (D4, D16, D27, D30, D40,
     *      D45, Dasha, Transit, Panchang, Muhurat, Numerology, Nakshatra
     *      Analysis) fall back to raw_data via AstrologyChartExtractor,
     *      which reads path definitions from config('astrology').
     *
     * This avoids depending on raw_data parsing for data that already
     * exists cleanly in typed columns.
     */
    private function getAstrologyContext(AiChatSession $session): string
    {
        $chart = UserAstrologyChart::where('user_id', $session->user_id)->first();

        if (!$chart) {
            return 'No stored astrology profile found for this user.';
        }

        $relevantCharts = $session->expertise->relevant_chart ?? [];

        if (is_string($relevantCharts)) {
            $relevantCharts = json_decode($relevantCharts, true) ?? [];
        }

        $result = [];

        foreach (self::CORE_PROFILE_FIELDS as $field) {
            if (isset($chart->{$field}) && $chart->{$field} !== null && $chart->{$field} !== '') {
                $result[$field] = $chart->{$field};
            }
        }

        $codesNeedingRawData = [];

        foreach ($relevantCharts as $code) {
            $column = self::CHART_COLUMN_MAP[$code] ?? null;

            if ($column && isset($chart->{$column}) && $chart->{$column} !== null) {
                $result['charts'][$code] = $chart->{$column};
                continue;
            }

            $codesNeedingRawData[] = $code;
        }

        if (!empty($codesNeedingRawData) && !empty($chart->raw_data)) {
            $rawData = json_decode((string) $chart->raw_data, true);

            if (is_array($rawData) && !empty($rawData)) {
                $extracted = AstrologyChartExtractor::extract($rawData, $codesNeedingRawData);

                if (!empty($extracted['charts'])) {
                    $result['charts'] = array_merge($result['charts'] ?? [], $extracted['charts']);
                }
            }
        }

        if (empty($result)) {
            return 'No relevant chart data is available for this expertise.';
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function errorResponse(string $message, int $status, ?string $type = null): JsonResponse
    {
        $payload = [
            'status' => false,
            'message' => $message,
        ];

        if ($type) {
            $payload = ['status' => false, 'type' => $type, 'message' => $message];
        }

        return response()->json($payload, $status);
    }
}