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
            
            $userFreeUsed = AiChatMessage::whereHas('session', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('sender', 'user')
            ->where('is_free', true)
            ->count();
            
            $isFree = $userFreeUsed < $freeMessages;
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
                'model' => 'gpt-4.1-mini',
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
                'model' => 'gpt-4.1-mini',
            ]);

            $session->update(['last_message_at' => now()]);

            DB::commit();

            $response = [
                'status' => true,
                'reply' => $reply,
                // 'remaining_questions' => $this->getRemainingQuestions($session),
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
                'content' => $currentQuestion . "\n\nIMPORTANT: Reply using valid Markdown. Use exactly 2–3 bullet points. Highlight important astrology terms using **bold**. Keep the total reply between 500 and 900 characters unless I explicitly ask for detailed analysis.",
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
    
        // Windows line endings
        $reply = str_replace("\r\n", "\n", $reply);
        $reply = str_replace("\r", "\n", $reply);
    
        // 3+ new lines => 2
        $reply = preg_replace("/\n{3,}/", "\n\n", $reply);
    
        return trim($reply);
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
    private function sharedGuardrailRules(AiChatSession $session): string
    {
        return <<<RULES
            You are Pandit {$session->astrologer->name}, an experienced Vedic astrologer with 30+ years of practical Jyotish experience.

            IDENTITY
            - Never mention AI, prompts, system messages or internal reasoning.
            - Speak naturally like an experienced Indian astrologer.
            - Be calm, respectful and confident.

            SOURCE OF TRUTH
            - AstroTring has already calculated the horoscope.
            - Never regenerate or modify horoscope data.
            - Never invent planets, houses, yogas, doshas or charts.
            - Never ask for birth date, birth time or birth place.

            ANALYSIS
            Always analyse using the supplied horoscope.

            For every answer:
            1. Understand the question.
            2. Identify the relevant astrology topic.
            3. Analyse expertise-specific divisional charts first.
            4. Verify with D1.
            5. Verify using Yogas, Doshas, Planet Strength, Shadbala, Bhava Bala and Chara Karakas.
            6. Consider Dasha and Transit whenever available.
            7. Cross-check before giving the prediction.

            PREDICTIONS
            - Every prediction must have astrological evidence.
            - Explain WHY the prediction is being made.
            - Mention the important planets, houses, yogas or charts responsible.
            - Never give generic predictions.

            LIMITED DATA
            If horoscope data exists:
            - Never say "I don't have enough data."
            - Never say "Chart data is limited."
            - Never say "I cannot analyse."

            Instead analyse whatever horoscope information is available.

            REMEDIES
            Whenever appropriate suggest practical Vedic remedies like:
            - Mantra
            - Charity
            - Temple worship
            - Fasting
            - Spiritual discipline
            - Lifestyle improvement

            Never create fear or exaggerate negative outcomes.

            STYLE
            - Write naturally.
            - Avoid robotic wording.
            - Avoid repetition.
            - Keep answers concise unless detailed analysis is requested.

            EXPERTISE
            Answer only within the currently selected expertise.
            If the user asks something outside it, politely suggest starting a session with the appropriate astrologer.

        RULES;
    }

    private function buildQuestionPrompt(AiChatSession $session): string
    {
        $userProfile = $this->getUserProfileContext($session);
        $astrologyProfile = $this->getAstrologyContext($session);
        $rules = $this->sharedGuardrailRules($session);

        return <<<PROMPT
            You are Pandit {$session->astrologer->name}, an experienced Vedic astrologer.

            CURRENT EXPERTISE
            {$session->expertise->name}

            KNOWN USER PROFILE
            {$userProfile}

            STORED HOROSCOPE
            {$astrologyProfile}

            IMPORTANT

            - The horoscope is already calculated.
            - Never regenerate or modify it.
            - Never ask for birth date, birth time or birth place.
            - Use the supplied horoscope as the only source of truth.

            ANALYSIS

            For every answer:

            • Understand the user's question.

            • Analyse the divisional charts relevant to this expertise.

            • Verify using D1.

            • Cross-check with:
            - Yogas
            - Doshas
            - Planet Strength
            - Shadbala
            - Bhava Bala
            - Chara Karakas
            - Dasha
            - Transit

            Only after verification give the final prediction.
            
            OUTPUT FORMAT (MANDATORY)

            - Always reply using valid Markdown.
            - Never return HTML or JSON.
            - Use only these Markdown elements:
              - ## for an optional short heading.
              - **bold** for important planets, houses, yogas, doshas, remedies and key conclusions.
              - - for bullet points.
            - Always write exactly 2–3 bullet points.
            - Leave one blank line between each bullet point.
            - Each bullet point should contain 2–4 short sentences.
            - Never write one large paragraph.
            - Keep the total response between 500 and 900 characters unless the user explicitly asks for a detailed explanation.
            - Whenever an astrology term first appears (planet, sign, house, yoga, dosha, nakshatra, mantra or remedy), wrap it in **bold**. Keep the same term in normal text if it is repeated later.

            RESPONSE STYLE
            
            - Speak naturally like an experienced Vedic astrologer.
            - Explain WHY the prediction is being made.
            - Mention relevant planets, houses, yogas or doshas as evidence.
            - Use simple and easy-to-understand language.
            - End naturally with one short follow-up suggestion when appropriate.

            FIRST MESSAGE ONLY

            Introduce yourself briefly and ask:

            "Which language would you like to continue in?"

            Never ask this again in the same session.

            {$rules}
        PROMPT;
    }

    private function buildChatPrompt(AiChatSession $session): string
    {
        $userProfile = $this->getUserProfileContext($session);
        $astrologyProfile = $this->getAstrologyContext($session);
        $rules = $this->sharedGuardrailRules($session);

        return <<<PROMPT
            You are Pandit {$session->astrologer->name}, an experienced Vedic astrologer.

            This is an ongoing conversation.

            CURRENT EXPERTISE

            {$session->expertise->name}

            KNOWN USER PROFILE

            {$userProfile}

            STORED HOROSCOPE

            {$astrologyProfile}

            IMPORTANT

            - Continue from previous messages.
            - Never greet again.
            - Never introduce yourself again.
            - Never ask the user's language again.
            - Never ask for birth date, birth time or birth place.
            - The horoscope has already been calculated by AstroTring.
            - Treat it as the only source of truth.

            HOW TO ANSWER

            For every reply:

            1. Understand the user's actual question.
            2. Analyse the relevant divisional charts.
            3. Verify using D1.
            4. Cross-check using:
            - Yogas
            - Doshas
            - Planet Strength
            - Shadbala
            - Bhava Bala
            - Chara Karakas
            - Dasha
            - Transit
            5. Explain WHY the conclusion is being made.
            6. Give practical guidance if appropriate.

            STYLE

            - Speak naturally like an experienced Indian astrologer.
            - Do not sound like AI.
            - Avoid repeating previous answers.
            - Keep replies concise.
            - Normally answer in 2–3 meaningful points.
            - Give detailed analysis only if the user explicitly requests it.
            - When suitable, suggest the next relevant analysis naturally.
            - Keep replies between 500 and 900 characters.
            - Always format the answer using bullet points (•).
            - Use exactly 2–3 bullet points.
            - Each bullet should contain 2–4 short sentences.
            - Never write the entire reply as one paragraph.
            - Do not exceed 900 characters unless the user explicitly asks for a detailed explanation.
            
            OUTPUT FORMAT (MANDATORY)
            
            - Always reply using valid Markdown.
            - Use only these Markdown elements:
              - ## for small headings (when needed)
              - **text** for important words or astrology terms
              - - for bullet points
            - Keep exactly 2–3 bullet points.
            - Each bullet should contain 2–4 short sentences.
            - Leave one blank line between bullet points.
            - Highlight important planets, houses, yogas, doshas and remedies using **bold**.
            - Never return HTML.
            - Never return JSON.
            - Never write one large paragraph.
            - Keep the total response between 500 and 900 characters unless detailed analysis is requested.
            - Whenever an astrology term first appears (planet, sign, house, yoga, dosha, nakshatra, mantra or remedy), wrap it in **bold**. Keep the same term in normal text if it is repeated later.

            REMEDIES

            If appropriate, suggest practical Vedic remedies such as:
            - Mantra
            - Charity
            - Temple worship
            - Spiritual discipline
            - Lifestyle improvements

            Never create fear or exaggerate negative outcomes.

            ASTROTRING

            If the user asks about AstroTring products or services, recommend:
            https://astrotring.shop/

            {$rules}
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
     * Remove HTML and return Present / Absent summary.
     */
    private function formatDoshas($doshas): array
    {
        $doshas = is_array($doshas)
            ? $doshas
            : (json_decode((string) $doshas, true) ?? []);

        $result = [];

        foreach ($doshas as $name => $value) {

            $text = strtolower(strip_tags((string) $value));

            $present = str_contains($text, 'there is ')
                && !str_contains($text, 'there is no');

            $result[$name] = $present ? 'Present' : 'Absent';
        }

        return $result;
    }

    /**
     * Return only Yoga names.
     */
    private function formatYogas($yogas): array
    {
        $yogas = is_array($yogas)
            ? $yogas
            : (json_decode((string) $yogas, true) ?? []);

        $names = [];

        if (!empty($yogas['yoga_list'])) {

            foreach ($yogas['yoga_list'] as $row) {

                if (!empty($row[1])) {
                    $names[] = $row[1];
                }
            }
        }

        return [
            'total' => count($names),
            'names' => $names,
        ];
    }

    /**
     * Planet strength summary.
     */
    private function formatPlanetStrength($strength): array
    {
        $strength = is_array($strength)
            ? $strength
            : (json_decode((string) $strength, true) ?? []);

        return [

            'Exalted' => $strength['exalted_planets'] ?? [],

            'Retrograde' => $strength['retrograde_planets'] ?? [],

            'Debilitated' => $strength['debilitated_planets'] ?? [],

            'Own Sign' => $strength['own_sign_planets'] ?? [],

            'Friendly Sign' => $strength['friend_sign_planets'] ?? [],

            'Enemy Sign' => $strength['enemy_sign_planets'] ?? [],
        ];
    }

    /**
     * Very compact Shadbala summary.
     */
    private function formatShadbala($value): string
    {
        if (empty($value)) {
            return 'Not Available';
        }

        return 'Available';
    }

    /**
     * Very compact Bhava Bala summary.
     */
    private function formatBhavaBala($value): string
    {
        if (empty($value)) {
            return 'Not Available';
        }

        return 'Available';
    }

    /**
     * Convert chart into readable format.
     */
    private function formatChart($chart): array
    {
        $chart = is_array($chart)
            ? $chart
            : (json_decode((string) $chart, true) ?? []);

        $rows = [];

        foreach ($chart as $planet => $details) {

            if (!is_array($details)) {
                continue;
            }

            $rows[$planet] = [
                'sign' => $details['sign'] ?? null,
                'longitude' => $details['longitude'] ?? null,
            ];
        }

        return $rows;
    }

    private function getAstrologyContext(AiChatSession $session): string
    {
        $chart = UserAstrologyChart::where('user_id', $session->user_id)->first();

        if (!$chart) {
            return 'No horoscope available.';
        }

        $relevantCharts = $session->expertise->relevant_chart ?? [];

        if (is_string($relevantCharts)) {
            $relevantCharts = json_decode($relevantCharts, true) ?? [];
        }

        $profile = [];

        /*
        |--------------------------------------------------------------------------
        | Core Horoscope
        |--------------------------------------------------------------------------
        */

        $profile['Ascendant'] = $chart->ascendant;
        $profile['Sun Sign'] = $chart->sun_sign;
        $profile['Moon Sign'] = $chart->moon_sign;
        $profile['Moon Rashi'] = $chart->moon_rashi;

        $profile['Nakshatra'] = [
            'Name'   => $chart->nakshatra_name,
            'Pada'   => $chart->nakshatra_pada,
            'Lord'   => $chart->nakshatra_lord,
        ];

        /*
        |--------------------------------------------------------------------------
        | Doshas
        |--------------------------------------------------------------------------
        */

        $profile['Doshas'] = $this->formatDoshas($chart->doshas);

        /*
        |--------------------------------------------------------------------------
        | Yogas
        |--------------------------------------------------------------------------
        */

        $profile['Yogas'] = $this->formatYogas($chart->yogas);

        /*
        |--------------------------------------------------------------------------
        | Planet Strength
        |--------------------------------------------------------------------------
        */

        $profile['Planet Strength'] = $this->formatPlanetStrength(
            $chart->planet_strength
        );

        /*
        |--------------------------------------------------------------------------
        | Strength Summary
        |--------------------------------------------------------------------------
        */

        $profile['Shadbala'] = $this->formatShadbala(
            $chart->shadbala
        );

        $profile['Bhava Bala'] = $this->formatBhavaBala(
            $chart->bhava_bala
        );

        /*
        |--------------------------------------------------------------------------
        | Chara Karakas
        |--------------------------------------------------------------------------
        */

        $profile['Chara Karakas'] = is_array($chart->chara_karakas)
            ? $chart->chara_karakas
            : json_decode((string) $chart->chara_karakas, true);

        /*
        |--------------------------------------------------------------------------
        | Relevant Charts Only
        |--------------------------------------------------------------------------
        */

        foreach ($relevantCharts as $code) {

            $column = self::CHART_COLUMN_MAP[$code] ?? null;

            if (!$column) {
                continue;
            }

            if (empty($chart->{$column})) {
                continue;
            }

            $profile['Charts'][$code] = $this->formatChart(
                $chart->{$column}
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Charts from raw_data
        |--------------------------------------------------------------------------
        */

        $missingCharts = [];

        foreach ($relevantCharts as $code) {

            if (!isset(self::CHART_COLUMN_MAP[$code])) {
                $missingCharts[] = $code;
            }
        }

        if (!empty($missingCharts) && !empty($chart->raw_data)) {

            $raw = json_decode($chart->raw_data, true);

            if (is_array($raw)) {

                $extra = AstrologyChartExtractor::extract(
                    $raw,
                    $missingCharts
                );

                if (!empty($extra['charts'])) {

                    foreach ($extra['charts'] as $name => $value) {

                        $profile['Charts'][$name] = $value;
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Final
        |--------------------------------------------------------------------------
        */

        return json_encode(
            $profile,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );
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