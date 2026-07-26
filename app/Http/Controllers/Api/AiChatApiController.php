<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAstrologyChart;
use App\Models\User;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AiAstrologer;
use App\Models\AiAstrologerExpertise;
use App\Models\AiAstrologerExpertiseQuestion;
use App\Models\AiChatTransaction;
use App\Services\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiChatApiController extends Controller
{
    private $openAiService;

    public function __construct(OpenAiService $openAiService)
    {
        $this->openAiService = $openAiService;
    }

    public function sessions(Request $request)
    {
        $sessions = AiChatSession::with(['astrologer', 'expertise'])
            ->withCount('messages')
            ->where('user_id', $request->user()->id)
            ->latest('last_message_at')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $sessions
        ]);
    }

    public function history($sessionId, Request $request)
    {
        $session = AiChatSession::with([
            'astrologer',
            'expertise',
            'messages' => function ($query) {
                $query->orderBy('id', 'asc');
            }
        ])
        ->where('user_id', $request->user()->id)
        ->findOrFail($sessionId);

        return response()->json([
            'status' => true,
            'session_started_at' => $session->started_at,
            'session_closed_at' => $session->closed_at,
            'data' => $session
        ]);
    }

    public function startSession(Request $request)
    {
        $request->validate([
            'astrologer_id' => 'nullable|exists:ai_astrologers,id',
            'astrologer_slug' => 'nullable|exists:ai_astrologers,slug',

            'expertise_id' => 'nullable|exists:ai_astrologer_expertises,id',
            'expertise_slug' => 'nullable|exists:ai_astrologer_expertises,slug',
        ]);

        if (
            !$request->filled('astrologer_id') &&
            !$request->filled('astrologer_slug')
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Astrologer is required.'
            ], 422);
        }

        if (
            !$request->filled('expertise_id') &&
            !$request->filled('expertise_slug')
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Expertise is required.'
            ], 422);
        }

        $user = $request->user();

        $astrologer = AiAstrologer::where('status', true)
            ->when(
                $request->filled('astrologer_id'),
                fn($q) => $q->where('id', $request->astrologer_id),
                fn($q) => $q->where('slug', $request->astrologer_slug)
            )
            ->first();

        if (!$astrologer) {
            return response()->json([
                'status' => false,
                'message' => 'Selected astrologer not found.'
            ], 422);
        }

        $expertise = AiAstrologerExpertise::where('ai_astrologer_id', $astrologer->id)
            ->where('status', true)
            ->when(
                $request->filled('expertise_id'),
                fn($q) => $q->where('id', $request->expertise_id),
                fn($q) => $q->where('slug', $request->expertise_slug)
            )
            ->first();

        if (!$expertise) {
            return response()->json([
                'status' => false,
                'message' => 'Selected expertise not found.'
            ], 422);
        }

        $session = AiChatSession::where('user_id', $user->id)
            ->where('astrologer_id', $astrologer->id)
            ->where('expertise_id', $expertise->id)
            ->first();

        if ($session) {

            $session->update([
                'status' => 'active',
                'closed_at' => null,
                'last_message_at' => now(),
            ]);

            $session->refresh()->load([
                'astrologer',
                'expertise',
                'messages'
            ]);

            $askedQuestionIds = AiChatMessage::where('session_id', $session->id)
                ->whereNotNull('question_id')
                ->pluck('question_id');

            $session->questions = AiAstrologerExpertiseQuestion::where(
                    'expertise_id',
                    $session->expertise_id
                )
                ->whereNotIn('id', $askedQuestionIds)
                ->orderBy('id')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Previous session resumed successfully.',
                'session_id' => $session->id,
                'data' => $session
            ]);
        }

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

        $session->load([
            'astrologer',
            'expertise'
        ]);

        $this->generateInitialConversation(
            $user,
            $session
        );

        $session->refresh()->load([
            'astrologer',
            'expertise',
            'messages'
        ]);

        $questions = AiAstrologerExpertiseQuestion::where(
                'expertise_id',
                $session->expertise_id
            )
            ->select(
                'id',
                'question'
            )
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Chat session started successfully.',
            'session_id' => $session->id,
            'data' => $session,
            'questions' => $questions
        ], 201);
    }

    private function generateInitialConversation(User $user, AiChatSession $session): void
    {
        try {

            $session->loadMissing([
                'astrologer',
                'expertise'
            ]);

            $title = $session->astrologer->gender === 'female'
                ? 'Ms.'
                : 'Mr.';

            $reply = "Hello {$user->name}! I am {$title} {$session->astrologer->name}, your Vedic astrologer. Please select one of the questions below or type your own question to begin.";

            AiChatMessage::create([
                'session_id'      => $session->id,
                'question_id'     => null,
                'sender'          => 'assistant',
                'message'         => $reply,
                'model'           => 'system',
                'charged_amount'  => 0,
                'is_free'         => false,
            ]);

            $session->update([
                'last_message_at' => now(),
            ]);

        } catch (\Throwable $e) {

            \Log::error('AI_INITIAL_GREETING', [
                'session_id' => $session->id,
                'user_id'    => $user->id,
                'error'      => $e->getMessage(),
            ]);

        }
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:ai_chat_sessions,id',
            'question_id' => 'nullable|exists:ai_astrologer_expertise_questions,id',
            'message' => 'nullable|string|max:5000',
        ]);

        if (
            !$request->filled('question_id') &&
            !$request->filled('message')
        ) {

            return response()->json([
                'status' => false,
                'message' => 'Question is required.'
            ],422);

        }

        DB::beginTransaction();

        try {

            $user = $request->user();

            $session = AiChatSession::with([
                'astrologer',
                'expertise',
                'messages'
            ])
            ->where('user_id', $user->id)
            ->findOrFail($request->session_id);

            if ($session->status !== 'active') {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'type' => 'session_closed',
                    'message' => 'This chat session is closed.'
                ], 422);
            }

            $isDatabaseQuestion = $request->filled('question_id');

            $currentQuestion = '';
            $questionId = null;

            if($isDatabaseQuestion){

                $question = AiAstrologerExpertiseQuestion::where(
                    'expertise_id',
                    $session->expertise_id
                )
                ->where('id', $request->question_id)
                ->firstOrFail();

                $questionId = $question->id;

                $currentQuestion = $question->question;

            }else{

                $currentQuestion = trim(
                    $request->message
                );

                if ($currentQuestion === '') {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'Question cannot be empty.'
                    ], 422);
                }

            }

            $freeMessages = (int) config('services.ai_chat.free_messages', 0);

            $isFree = $session->free_messages_used < $freeMessages;

            $chatPrice = $isFree
                ? 0
                : (float) config('services.ai_chat.price');

            $before = 0;
            $after = 0;

            if (!$isFree) {

                $wallet = $user->wallet;

                if (!$wallet || $wallet->balance < $chatPrice) {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'type' => 'insufficient_balance',
                        'message' => 'Insufficient wallet balance. Please recharge your wallet.'
                    ], 422);

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

            if ($isFree) {

                $session->increment('free_messages_used');

            } else {

                $session->increment('paid_messages');

                $session->increment('total_amount', $chatPrice);

            }

            if ($isDatabaseQuestion) {

                $systemPrompt = $this->buildQuestionPrompt(
                    $user,
                    $session
                );

            } else {

                $systemPrompt = $this->buildChatPrompt(
                    $user,
                    $session
                );

            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ]
            ];

            if ($isDatabaseQuestion) {

                $messages[] = [
                    'role' => 'user',
                    'content' => $currentQuestion . "\n\nIMPORTANT: Reply in maximum 2 short bullet points. No paragraphs. Maximum 35 words. Only provide detailed explanation if I explicitly ask for it.",
                ];

            } else {

                $history = $session->messages()
                    ->where('model','!=','system')
                    ->latest()
                    ->take(8)
                    ->get()
                    ->reverse();

                foreach ($history as $chat) {

                    if ($chat->model == 'system') {
                        continue;
                    }

                    $messages[] = [
                        'role' => $chat->sender == 'user'
                            ? 'user'
                            : 'assistant',
                        'content' => $chat->message,
                    ];
                }
            }

            try {

                $reply = $this->openAiService->chat(
                    $messages
                );

                $reply = trim($reply);

                $reply = str_replace(["\r", "\n"], ' ', $reply);

                $reply = preg_replace('/\s+/', ' ', $reply);

            } catch (\Throwable $e) {

                DB::rollBack();

                \Log::error('AI_SERVICE_ERROR', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'status' => false,
                    'type' => 'ai_error',
                    'message' => 'AI service is temporarily unavailable.'
                ], 503);
            }

            AiChatMessage::create([
                'session_id' => $session->id,
                'question_id' => null,
                'sender' => 'assistant',
                'message' => trim($reply),
                'is_free' => $isFree,
                'charged_amount' => 0,
                'model' => 'gpt-4o-mini',
            ]);

            $session->update([
                'last_message_at' => now(),
            ]);

            $askedQuestionIds = AiChatMessage::where('session_id', $session->id)
                ->whereNotNull('question_id')
                ->pluck('question_id');

            $remainingQuestions = AiAstrologerExpertiseQuestion::where(
                    'expertise_id',
                    $session->expertise_id
                )
                ->whereNotIn('id', $askedQuestionIds)
                ->select('id', 'question')
                ->orderBy('id')
                ->get();

            DB::commit();

            return response()->json([
                'status' => true,
                'reply' => trim($reply),
                'remaining_questions' => $remainingQuestions,
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error('AI_CHAT_ERROR', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'type' => 'server_error',
                'message' => 'Something went wrong. Please try again.'
            ], 500);
        }
    }

    public function closeSession($id, Request $request)
    {
        $session = AiChatSession::where(
            'user_id',
            $request->user()->id
        )->findOrFail($id);

        $session->update([
            'status' => 'closed',
            'closed_at' => now()
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Session closed successfully'
        ]);
    }

    private function buildQuestionPrompt(User $user, AiChatSession $session): string
    {
        $astrologyProfile = $this->getAstrologyContext($user, $session);

        return <<<PROMPT
            You are {$session->astrologer->name}, a highly experienced Vedic Astrologer.

            CURRENT EXPERTISE
            {$session->expertise->name}

            ==================================================

            STORED ASTROLOGY PROFILE

            {$astrologyProfile}

            ==================================================

            IMPORTANT RULES

            1. Answer ONLY astrology related questions.

            2. Stay ONLY within this expertise:
            {$session->expertise->name}

            3. Never answer questions outside this expertise.
            Politely ask the user to start another expertise session.

            4. Never regenerate or modify the astrology profile.

            5. Never change Moon Sign, Sun Sign, Lagna, Nakshatra or Pada.

            6. Use ONLY the stored astrology profile.

            7. Use ONLY the relevant charts available in the profile for this expertise.

            8. Never assume any chart which is not available.

            9. Never mention you are AI.

            10. Use Vedic Astrology only.

            11. Give realistic guidance.

            12. Don't exaggerate predictions.

            13. Don't create fake yogas or doshas.

            14. If information is insufficient, clearly say that the available chart data is limited.

            15. Reply in EXACTLY 2 bullet points.

            16. Every bullet must contain only one idea.

            17. Maximum 40 words.

            18. No paragraphs.

            19. No numbering.

            20. No long explanation.

            21. If user asks Explain / Why / How / Detailed Analysis, then provide detailed explanation.

            22. If this is the first interaction, briefly introduce yourself and ask:
            "Which language would you like to continue in?"

            23. Never ask language again after first reply.

        PROMPT;
    }

    private function buildChatPrompt(User $user, AiChatSession $session): string
    {
        $astrologyProfile = $this->getAstrologyContext($user, $session);

        return <<<PROMPT
            You are {$session->astrologer->name}, a professional Vedic Astrologer.

            CURRENT EXPERTISE
            {$session->expertise->name}

            ==================================================

            STORED ASTROLOGY PROFILE

            {$astrologyProfile}

            ==================================================

            IMPORTANT RULES

            1. Continue the existing conversation naturally.

            2. Stay ONLY within this expertise:
            {$session->expertise->name}

            3. Never answer questions outside this expertise.

            4. Politely ask the user to start another expertise session if required.

            5. Use ONLY the stored astrology profile.

            6. Never regenerate horoscope.

            7. Never change Moon Sign, Sun Sign, Lagna, Nakshatra or Pada.

            8. Use ONLY the relevant charts available in the stored profile.

            9. Never assume missing chart information.

            10. Never mention AI.

            11. Follow Vedic Astrology principles only.

            12. Give practical and realistic guidance.

            13. Never repeat previous answers unnecessarily.

            14. Never greet again.

            15. Never ask language again.

            16. Reply in EXACTLY 2 bullet points.

            17. Every bullet should contain one idea.

            18. Maximum 40 words.

            19. No paragraphs.

            20. Keep the answer simple and natural.

            21. Give detailed explanation ONLY if the user explicitly asks:
            Explain, Why, How, Detailed Analysis.

            22. If user asks about AstroTring products, suggest:
            https://astrotring.shop/

        PROMPT;
    }

    private function getAstrologyContext(User $user, AiChatSession $session): string
    {
        $profile = optional($user->astrologyChart)->charts ?? [];

        if (!$profile) {
            return '';
        }

        $requiredCharts = $session->expertise->relevant_chart;

        if (is_string($requiredCharts)) {
            $requiredCharts = json_decode($requiredCharts, true);
        }

        $context = [];

        $context['basic'] = [
            'moon_sign' => $profile['moon_sign'] ?? '',
            'sun_sign' => $profile['sun_sign'] ?? '',
            'lagna' => $profile['lagna'] ?? '',
            'nakshatra' => $profile['nakshatra'] ?? '',
            'pada' => $profile['pada'] ?? ''
        ];

        foreach ($requiredCharts as $chart) {

            $key = strtolower($chart);

            if (isset($profile[$key])) {
                $context[$chart] = $profile[$key];
            }

            switch ($chart) {

                case 'Dasha':
                    $context['Dasha'] = $profile['dasha'] ?? [];
                    break;

                case 'Transit':
                    $context['Transit'] = $profile['transit'] ?? [];
                    break;

                case 'Numerology':
                    $context['Numerology'] = $profile['numerology'] ?? [];
                    break;

                case 'Muhurat':
                    $context['Muhurat'] = $profile['muhurat'] ?? [];
                    break;

                case 'Panchang':
                    $context['Panchang'] = $profile['panchang'] ?? [];
                    break;

                case 'Nakshatra Analysis':
                    $context['Nakshatra Analysis'] = $profile['nakshatra_analysis'] ?? [];
                    break;
            }
        }

        return json_encode($context, JSON_PRETTY_PRINT);
    }
}