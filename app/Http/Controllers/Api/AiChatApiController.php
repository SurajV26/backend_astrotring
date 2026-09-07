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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiChatApiController extends Controller
{
    private const FREE_MESSAGES_ALLOWED = 1;
    private const MINIMUM_CHAT_START_BALANCE = 80.00;
    private const CHAT_IDLE_TIMEOUT_SECONDS = 180;

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
        // Keep the permanent session/history intact.
        // Billing starts only through startChat().
        $user = User::find($session->user_id);
        $freeMessagesUsed = $user ? $this->countUserFreeMessages($user) : (int) $session->free_messages_used;
        $freeLimit = $this->getFreeMessageLimit();

        $session->update([
            'status' => 'active',
            'closed_at' => null,
            'last_message_at' => now(),
            'chat_free_used' => $freeMessagesUsed >= $freeLimit,
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
        $freeMessagesUsed = $this->countUserFreeMessages($user);
        $freeLimit = $this->getFreeMessageLimit();

        $session = AiChatSession::create([
            'user_id' => $user->id,
            'astrologer_id' => $astrologer->id,
            'expertise_id' => $expertise->id,
            'paid_messages' => 0,
            'total_amount' => 0,
            'started_at' => now(),
            'last_message_at' => now(),
            'status' => 'active',
            'chat_active_since' => null,
            'chat_last_seen_at' => null,
            'chat_billed_minutes' => 0,
            'chat_free_used' => $freeMessagesUsed >= $freeLimit,
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
            $user = User::where('id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $session = AiChatSession::with(['astrologer', 'expertise', 'messages'])
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($request->session_id);

            if ($session->status !== 'active') {
                DB::rollBack();

                return $this->errorResponse(
                    'Your chat session is closed.',
                    422,
                    'session_closed'
                );
            }

            /*
             * Do not allow a message to sneak in after the 3-minute
             * inactivity deadline when the scheduler has not run yet.
             * Only USER activity resets this timer.
             */
            if ($session->chat_active_since && !$session->chat_last_seen_at) {
                $lastUserMessage = AiChatMessage::where('session_id', $session->id)
                    ->where('sender', 'user')
                    ->latest('id')
                    ->first();

                $lastUserActivityAt = $lastUserMessage?->created_at
                    ? Carbon::parse($lastUserMessage->created_at)
                    : Carbon::parse($session->chat_active_since);

                // Ignore messages from an older billing period.
                $activeSince = Carbon::parse($session->chat_active_since);
                if ($lastUserActivityAt->lt($activeSince)) {
                    $lastUserActivityAt = $activeSince->copy();
                }

                if ($lastUserActivityAt->copy()->addSeconds(180)->lte(now())) {
                    $closedAt = now();

                    $session->update([
                        'status' => 'closed',
                        'closed_at' => $closedAt,
                        'chat_active_since' => null,
                        'chat_last_seen_at' => $closedAt,
                    ]);

                    DB::commit();

                    return response()->json([
                        'status' => false,
                        'chat_started' => true,
                        'chat_active' => false,
                        'chat_stopped' => true,
                        'session_closed' => true,
                        'session_id' => $session->id,
                        'type' => 'session_closed',
                        'message' => 'Your chat session was automatically closed because there was no message from you for 3 minutes.',
                        'chat_active_since' => null,
                        'chat_last_seen_at' => $closedAt,
                        'session_closed_at' => $closedAt,
                    ], 422);
                }
            }

            $isDatabaseQuestion = $request->filled('question_id');

            [$currentQuestion, $questionId, $failure] = $isDatabaseQuestion
                ? $this->resolveDatabaseQuestion($session, (int) $request->question_id)
                : $this->resolveFreeTextQuestion($request->message);

            if ($failure) {
                DB::rollBack();
                return $this->errorResponse($failure, 422);
            }

            $freeLimit = $this->getFreeMessageLimit();

            $freeMessagesUsed = $this->countUserFreeMessages($user);
            $isFree = $freeMessagesUsed < $freeLimit;

            if (!$isFree) {
                $billingError = $this->checkChatBalance($user, $session);

                if ($billingError) {
                    DB::rollBack();
                    return $billingError;
                }
            }

            $userMessage = AiChatMessage::create([
                'session_id' => $session->id,
                'question_id' => $questionId,
                'sender' => 'user',
                'message' => $currentQuestion,
                'charged_amount' => 0,
                'is_free' => $isFree,
                'model' => 'gpt-4.1-mini',
            ]);

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

            $chatStoppedAfterFree = false;
            $billingWarning = null;

            if ($isFree) {
                $freeMessagesUsedAfter = $freeMessagesUsed + 1;

                $updateData = [
                    'free_messages_used' => $freeMessagesUsedAfter,
                    'chat_free_used' => $freeMessagesUsedAfter >= $freeLimit,
                ];

                if (
                    $freeMessagesUsedAfter >= $freeLimit
                    && !$session->chat_active_since
                ) {
                    $pricePerMinute = $this->getChatPricePerMinute($session);

                    if ($pricePerMinute <= 0) {
                        DB::rollBack();

                        return $this->errorResponse(
                            'Chat is temporarily unavailable for this astrologer.',
                            422,
                            'invalid_chat_price'
                        );
                    }

                    $wallet = $user->wallet()
                        ->lockForUpdate()
                        ->first();

                    $walletBalance = $wallet
                        ? round((float) $wallet->balance, 2)
                        : 0.0;

                    if (!$wallet || $walletBalance < $pricePerMinute) {
                        $updateData['chat_active_since'] = null;
                        $updateData['chat_last_seen_at'] = now();
                        $updateData['chat_billed_minutes'] = 0;
                        $chatStoppedAfterFree = true;
                        $billingWarning = 'Your free message has been delivered, but your wallet balance cannot fund the next paid minute. Please recharge your wallet to continue chatting.';
                    } else {
                        // First paid minute is prepaid immediately after the free response succeeds.
                        $session->update([
                            'chat_billed_minutes' => 0,
                        ]);

                        $this->debitChatMinutes(
                            $user,
                            $session,
                            $wallet,
                            1,
                            'AI Astrology Chat'
                        );

                        $updateData['chat_active_since'] = now();
                        $updateData['chat_last_seen_at'] = null;
                        $updateData['chat_billed_minutes'] = 1;
                    }
                }

                $session->update($updateData);
            }

            $session->update(['last_message_at' => now()]);

            DB::commit();

            $session->refresh();

            $response = [
                'status' => true,
                'reply' => $reply,
                'chat_free_used' => (bool) $session->chat_free_used,
                'chat_active_since' => $session->chat_active_since,
                'chat_last_seen_at' => $session->chat_last_seen_at,
                'chat_stopped' => $chatStoppedAfterFree,
                // 'remaining_questions' => $this->getRemainingQuestions($session),
            ];

            if ($billingWarning) {
                $response['billing_message'] = $billingWarning;
                $response['billing_type'] = 'insufficient_balance';
            }

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

    private function getFreeMessageLimit(): int
    {
        return max(
            0,
            (int) config(
                'services.ai_chat.free_messages',
                env('AI_CHAT_FREE_MESSAGES', self::FREE_MESSAGES_ALLOWED)
            )
        );
    }

    private function getChatPricePerMinute(AiChatSession $session): float
    {
        return max(
            0,
            (float) optional($session->astrologer)->chat_price
        );
    }

    private function getWalletBalance(User $user): float
    {
        return round(
            (float) optional($user->wallet)->balance,
            2
        );
    }


    private function debitChatMinutes(
        User $user,
        AiChatSession $session,
        $wallet,
        int $minutes,
        string $remark = 'AI Astrology Chat'
    ): float {
        if ($minutes <= 0) {
            return 0.0;
        }

        $pricePerMinute = $this->getChatPricePerMinute($session);

        if ($pricePerMinute <= 0) {
            throw new \RuntimeException('Invalid astrologer chat price.');
        }

        $minutes = max(0, $minutes);
        $amount = round($minutes * $pricePerMinute, 2);
        $before = round((float) $wallet->balance, 2);

        if ($before < $amount) {
            throw new \RuntimeException('Insufficient wallet balance.');
        }

        $after = round($before - $amount, 2);

        $wallet->update([
            'balance' => $after,
            'total_spent' => (float) $wallet->total_spent + $amount,
        ]);

        AiChatTransaction::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'message_id' => null,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'type' => 'debit',
            'remark' => $remark . " - {$minutes} minute(s)",
        ]);

        $session->update([
            'chat_billed_minutes' => (int) $session->chat_billed_minutes + $minutes,
            'total_amount' => (float) $session->total_amount + $amount,
        ]);

        Log::info('AI_CHAT_BILLING_DEBIT', [
            'session_id' => $session->id,
            'user_id' => $user->id,
            'minutes' => $minutes,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
        ]);

        return $amount;
    }

    private function checkChatBalance(User $user, AiChatSession $session): ?JsonResponse
    {
        if (!$session->chat_active_since || $session->chat_last_seen_at) {
            return $this->errorResponse(
                'Your chat has ended. Please start the chat again to continue.',
                422,
                'chat_not_active'
            );
        }

        $pricePerMinute = $this->getChatPricePerMinute($session);

        if ($pricePerMinute <= 0) {
            return $this->errorResponse(
                'Chat is temporarily unavailable for this astrologer.',
                422,
                'invalid_chat_price'
            );
        }

        /*
        * Check exact paid-time expiry.
        *
        * chat_active_since = actual paid chat start time
        * chat_billed_minutes = already funded minutes
        * wallet balance = additional minutes still affordable
        */
        $activeSince = Carbon::parse($session->chat_active_since);

        $walletBalance = $this->getWalletBalance($user);

        $billedMinutes = (int) $session->chat_billed_minutes;

        $remainingWalletMinutes = (int) floor(
            $walletBalance / $pricePerMinute
        );

        $totalFundedMinutes = $billedMinutes + $remainingWalletMinutes;

        $paidUntil = $activeSince->copy()->addMinutes(
            $totalFundedMinutes
        );

        if (now()->greaterThanOrEqualTo($paidUntil)) {
            $session->update([
                'chat_active_since' => null,
                'chat_last_seen_at' => $paidUntil,
            ]);

            return $this->errorResponse(
                'Your paid chat time has ended. Please recharge your wallet to continue chatting.',
                422,
                'insufficient_balance'
            );
        }

        // Automatic wallet billing is handled only by the Scheduler/cron job.
        return null;
    }

    private function countUserFreeMessages(User $user): int
    {
        return AiChatMessage::whereHas('session', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->where('sender', 'user')
            ->where('is_free', true)
            ->count();
    }


    /**
     * Heartbeat endpoint. Frontend calls this every few seconds while the
     * chat page is open. Billing is performed server-side at minute boundaries.
     */
    public function heartbeat($sessionId, Request $request): JsonResponse
    {
        try {
            $session = AiChatSession::with('astrologer')
                ->where('user_id', $request->user()->id)
                ->findOrFail($sessionId);

            $isActive = $session->status === 'active'
                && $session->chat_active_since
                && !$session->chat_last_seen_at;

            if ($isActive) {
                $activeSince = Carbon::parse($session->chat_active_since);

                $pricePerMinute = $this->getChatPricePerMinute($session);
                $walletBalance = $this->getWalletBalance($request->user());

                $billedMinutes = (int) $session->chat_billed_minutes;

                $remainingWalletMinutes = $pricePerMinute > 0
                    ? (int) floor($walletBalance / $pricePerMinute)
                    : 0;

                $totalFundedMinutes = $billedMinutes + $remainingWalletMinutes;

                $paidUntil = $activeSince->copy()->addMinutes(
                    $totalFundedMinutes
                );

                if (now()->greaterThanOrEqualTo($paidUntil)) {
                    $stoppedAt = $paidUntil;

                    $session->update([
                        'chat_active_since' => null,
                        'chat_last_seen_at' => $stoppedAt,
                    ]);

                    $isActive = false;
                }
            }

            return response()->json([
                'status' => true,
                'chat_active' => (bool) $isActive,
                'chat_stopped' => !$isActive && (bool) $session->chat_last_seen_at,
                'session_id' => $session->id,
                'chat_active_since' => $session->chat_active_since,
                'chat_last_seen_at' => $session->chat_last_seen_at,
                'chat_billed_minutes' => (int) $session->chat_billed_minutes,
                'total_amount' => (float) $session->total_amount,
                'wallet_balance' => $this->getWalletBalance($request->user()),
                'price_per_minute' => $this->getChatPricePerMinute($session),
            ]);
        } catch (Throwable $e) {
            Log::error('AI_CHAT_HEARTBEAT_ERROR', [
                'user_id' => $request->user()->id ?? null,
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Something went wrong. Please try again.', 500, 'server_error');
        }
    }

    /**
     * Laravel Scheduler billing authority.
     * First paid minute is prepaid at start; subsequent minutes are prepaid
     * at minute boundaries. Wallet can never become negative.
     */
    /**
     * Laravel Scheduler billing authority.
     *
     * - Billing is processed every minute.
     * - User inactivity is measured from the last USER message.
     * - If there is no user message for 3 minutes, the chat is automatically closed.
     * - Auto-close settles only the time actually used, up to the 3-minute
     *   inactivity deadline. It never charges beyond that deadline.
     */
    public function processActiveChatBilling(): array
    {
        $stats = [
            'sessions_checked' => 0,
            'sessions_billed' => 0,
            'sessions_stopped' => 0,
            'sessions_auto_closed' => 0,
            'minutes_charged' => 0,
            'amount_charged' => 0.0,
            'errors' => 0,
        ];

        $idleTimeoutSeconds = self::CHAT_IDLE_TIMEOUT_SECONDS;

        Log::info('AI_CHAT_BILLING_RUN_STARTED');

        AiChatSession::query()
            ->where('status', 'active')
            ->whereNotNull('chat_active_since')
            ->whereNull('chat_last_seen_at')
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use (&$stats, $idleTimeoutSeconds) {
                foreach ($candidates as $candidate) {
                    $stats['sessions_checked']++;

                    try {
                        $result = DB::transaction(function () use ($candidate, $idleTimeoutSeconds) {
                            $session = AiChatSession::with('astrologer')
                                ->whereKey($candidate->id)
                                ->where('status', 'active')
                                ->whereNotNull('chat_active_since')
                                ->whereNull('chat_last_seen_at')
                                ->lockForUpdate()
                                ->first();

                            if (!$session) {
                                return null;
                            }

                            $user = User::whereKey($session->user_id)
                                ->lockForUpdate()
                                ->first();

                            $now = now();

                            if (!$user) {
                                $session->update([
                                    'status' => 'closed',
                                    'closed_at' => $now,
                                    'chat_active_since' => null,
                                    'chat_last_seen_at' => $now,
                                ]);

                                return [
                                    'billed' => false,
                                    'stopped' => true,
                                    'auto_closed' => true,
                                    'minutes' => 0,
                                    'amount' => 0.0,
                                ];
                            }

                            $price = $this->getChatPricePerMinute($session);

                            if ($price <= 0) {
                                $session->update([
                                    'status' => 'closed',
                                    'closed_at' => $now,
                                    'chat_active_since' => null,
                                    'chat_last_seen_at' => $now,
                                ]);

                                Log::warning('AI_CHAT_BILLING_INVALID_PRICE', [
                                    'session_id' => $session->id,
                                    'astrologer_id' => $session->astrologer_id,
                                ]);

                                return [
                                    'billed' => false,
                                    'stopped' => true,
                                    'auto_closed' => true,
                                    'minutes' => 0,
                                    'amount' => 0.0,
                                ];
                            }

                            $wallet = $user->wallet()->lockForUpdate()->first();

                            if (!$wallet) {
                                $session->update([
                                    'status' => 'closed',
                                    'closed_at' => $now,
                                    'chat_active_since' => null,
                                    'chat_last_seen_at' => $now,
                                ]);

                                return [
                                    'billed' => false,
                                    'stopped' => true,
                                    'auto_closed' => true,
                                    'minutes' => 0,
                                    'amount' => 0.0,
                                ];
                            }

                            $activeSince = $session->chat_active_since instanceof Carbon
                                ? $session->chat_active_since->copy()
                                : Carbon::parse($session->chat_active_since);

                            /*
                             * IMPORTANT:
                             * Only USER messages reset the 3-minute inactivity timer.
                             * Assistant messages, heartbeat and frontend polling do not.
                             */
                            $lastUserMessage = AiChatMessage::where('session_id', $session->id)
                                ->where('sender', 'user')
                                ->latest('id')
                                ->first();

                            $lastUserActivityAt = $lastUserMessage?->created_at
                                ? Carbon::parse($lastUserMessage->created_at)
                                : $activeSince;

                            // A message from an older billing period must not
                            // make a newly started chat expire immediately.
                            if ($lastUserActivityAt->lt($activeSince)) {
                                $lastUserActivityAt = $activeSince->copy();
                            }

                            $idleDeadline = $lastUserActivityAt->copy()
                                ->addSeconds($idleTimeoutSeconds);

                            $autoCloseDue = $now->greaterThanOrEqualTo($idleDeadline);

                            /*
                             * If auto-close is due, settle only up to the exact
                             * 3-minute inactivity deadline. Never charge time
                             * after that deadline.
                             */
                            $billingUntil = $autoCloseDue
                                ? $idleDeadline
                                : $now;

                            $elapsedSeconds = max(
                                0,
                                $activeSince->diffInSeconds($billingUntil)
                            );

                            if ($autoCloseDue) {
                                // At close time, charge completed minutes only.
                                $requiredMinutes = max(
                                    1,
                                    (int) ceil($elapsedSeconds / 60)
                                );
                            } else {
                                // First minute is prepaid. At each minute
                                // boundary, fund the next minute.
                                $requiredMinutes = (int) floor($elapsedSeconds / 60) + 1;
                            }

                            $billedMinutes = (int) $session->chat_billed_minutes;
                            $minutesDue = max(
                                0,
                                $requiredMinutes - $billedMinutes
                            );

                            $minutesCharged = 0;
                            $amountCharged = 0.0;

                            if ($minutesDue > 0) {
                                $balance = round((float) $wallet->balance, 2);
                                $affordable = (int) floor($balance / $price);

                                $minutesToCharge = min(
                                    $minutesDue,
                                    max(0, $affordable)
                                );

                                if ($minutesToCharge > 0) {
                                    $amountCharged = $this->debitChatMinutes(
                                        $user,
                                        $session,
                                        $wallet,
                                        $minutesToCharge,
                                        'AI Astrology Chat'
                                    );

                                    $minutesCharged = $minutesToCharge;
                                }

                                /*
                                 * Not enough balance to fund all required minutes.
                                 * Stop the billing period immediately.
                                 */
                                if ($minutesToCharge < $minutesDue) {
                                    $session->update([
                                        'chat_active_since' => null,
                                        'chat_last_seen_at' => $now,
                                    ]);

                                    Log::warning('AI_CHAT_BILLING_STOPPED_INSUFFICIENT_BALANCE', [
                                        'session_id' => $session->id,
                                        'user_id' => $user->id,
                                        'required_minutes' => $requiredMinutes,
                                        'billed_minutes_before' => $billedMinutes,
                                        'minutes_due' => $minutesDue,
                                        'minutes_charged' => $minutesToCharge,
                                        'wallet_balance' => round((float) $wallet->fresh()->balance, 2),
                                        'price_per_minute' => $price,
                                    ]);

                                    return [
                                        'billed' => $minutesCharged > 0,
                                        'stopped' => true,
                                        'auto_closed' => false,
                                        'minutes' => $minutesCharged,
                                        'amount' => $amountCharged,
                                    ];
                                }
                            }

                            /*
                             * Three minutes without a USER message:
                             * permanently close this chat session.
                             *
                             * The next status API call will return
                             * "Your chat session is closed." instead of an
                             * insufficient-balance message.
                             */
                            if ($autoCloseDue) {
                                $session->update([
                                    'status' => 'closed',
                                    'closed_at' => $now,
                                    'chat_active_since' => null,
                                    'chat_last_seen_at' => $now,
                                ]);

                                Log::info('AI_CHAT_AUTO_CLOSED_INACTIVE', [
                                    'session_id' => $session->id,
                                    'user_id' => $user->id,
                                    'inactive_for_seconds' => $lastUserActivityAt->diffInSeconds($now),
                                    'idle_timeout_seconds' => $idleTimeoutSeconds,
                                    'last_user_message_at' => $lastUserMessage?->created_at,
                                    'minutes_charged' => $minutesCharged,
                                    'amount_charged' => $amountCharged,
                                ]);

                                return [
                                    'billed' => $minutesCharged > 0,
                                    'stopped' => true,
                                    'auto_closed' => true,
                                    'minutes' => $minutesCharged,
                                    'amount' => $amountCharged,
                                ];
                            }

                            return [
                                'billed' => $minutesCharged > 0,
                                'stopped' => false,
                                'auto_closed' => false,
                                'minutes' => $minutesCharged,
                                'amount' => $amountCharged,
                            ];
                        });

                        if (!$result) {
                            continue;
                        }

                        if ($result['billed']) {
                            $stats['sessions_billed']++;
                            $stats['minutes_charged'] += $result['minutes'];
                            $stats['amount_charged'] += $result['amount'];
                        }

                        if ($result['stopped']) {
                            $stats['sessions_stopped']++;
                        }

                        if ($result['auto_closed']) {
                            $stats['sessions_auto_closed']++;
                        }
                    } catch (Throwable $e) {
                        $stats['errors']++;

                        Log::error('AI_CHAT_BILLING_SESSION_ERROR', [
                            'session_id' => $candidate->id ?? null,
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                    }
                }
            });

        $stats['amount_charged'] = round($stats['amount_charged'], 2);

        Log::info('AI_CHAT_BILLING_RUN_FINISHED', $stats);

        return $stats;
    }

    /**
     * Frontend status endpoint.
     *
     * `status` means the API request succeeded.
     * `chat_active` means the current chat billing period is running.
     * `chat_stopped` means the current chat is not running.
     * `session_closed` means the permanent chat session was auto/manual closed.
     */
    public function chatStatus($sessionId, Request $request): JsonResponse
    {
        $session = AiChatSession::with('astrologer')
            ->where('user_id', $request->user()->id)
            ->findOrFail($sessionId);

        $pricePerMinute = $this->getChatPricePerMinute($session);

        $walletBalance = $this->getWalletBalance($request->user());

        $isSessionClosed = $session->status !== 'active';

        $isRunning = !$isSessionClosed
            && (bool) ($session->chat_active_since && !$session->chat_last_seen_at);

        if ($isRunning) {
            $activeSince = Carbon::parse($session->chat_active_since);

            $billedMinutes = (int) $session->chat_billed_minutes;

            $remainingWalletMinutes = $pricePerMinute > 0
                ? (int) floor($walletBalance / $pricePerMinute)
                : 0;

            $totalFundedMinutes = $billedMinutes + $remainingWalletMinutes;

            $paidUntil = $activeSince->copy()->addMinutes(
                $totalFundedMinutes
            );

            if (now()->greaterThanOrEqualTo($paidUntil)) {
                $stoppedAt = $paidUntil;

                $session->update([
                    'chat_active_since' => null,
                    'chat_last_seen_at' => $stoppedAt,
                ]);

                $isRunning = false;
            }
        }

        $chatStopped = !$isRunning;

        $availablePaidMinutes = $pricePerMinute > 0
            ? (int) floor($walletBalance / $pricePerMinute)
            : 0;

        $idleTimeoutSeconds = 180;
        $lastUserMessage = AiChatMessage::where('session_id', $session->id)
            ->where('sender', 'user')
            ->latest('id')
            ->first();

        $lastUserActivityAt = null;
        $autoCloseAt = null;
        $idleRemainingSeconds = 0;

        if ($isRunning) {
            $lastUserActivityAt = $lastUserMessage?->created_at
                ? Carbon::parse($lastUserMessage->created_at)
                : Carbon::parse($session->chat_active_since);

            // Ignore messages from an older billing period.
            $activeSince = Carbon::parse($session->chat_active_since);
            if ($lastUserActivityAt->lt($activeSince)) {
                $lastUserActivityAt = $activeSince->copy();
            }

            $autoCloseAt = $lastUserActivityAt->copy()
                ->addSeconds($idleTimeoutSeconds);

            $idleRemainingSeconds = max(
                0,
                now()->diffInSeconds($autoCloseAt, false)
            );
        }

        $payload = [
            'status' => true,
            'session_id' => $session->id,

            // Clear frontend state flags.
            'chat_started' => (bool) $session->chat_active_since,
            'chat_active' => $isRunning,
            'chat_stopped' => $chatStopped,
            'session_closed' => $isSessionClosed,

            'chat_active_since' => $session->chat_active_since,
            'chat_last_seen_at' => $session->chat_last_seen_at,
            'session_closed_at' => $session->closed_at,

            'chat_billed_minutes' => (int) $session->chat_billed_minutes,
            'total_amount' => (float) $session->total_amount,
            'price_per_minute' => $pricePerMinute,
            'wallet_balance' => $walletBalance,
            'available_paid_minutes' => $availablePaidMinutes,

            'idle_timeout_seconds' => $idleTimeoutSeconds,
            'last_user_message_at' => $lastUserActivityAt,
            'auto_close_at' => $autoCloseAt,
            'idle_remaining_seconds' => $idleRemainingSeconds,
        ];

        if ($isSessionClosed) {
            $payload['type'] = 'session_closed';
            $payload['message'] = 'Your chat session is closed.';
        } elseif ($chatStopped) {
            if ($pricePerMinute > 0 && $walletBalance < $pricePerMinute) {
                $payload['type'] = 'insufficient_balance';
                $payload['message'] = 'Your chat has ended because your wallet balance is insufficient. Please recharge your wallet to continue chatting.';
            } else {
                $payload['type'] = 'chat_stopped';
                $payload['message'] = 'Your chat has stopped.';
            }
        } else {

            $payload['type'] = 'chat_active';
            $payload['message'] = 'Your chat is active.';
        }

        return response()->json($payload);
    }

    public function startChat($sessionId, Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = User::where('id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $session = AiChatSession::with('astrologer')
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($sessionId);

            if ($session->status !== 'active') {
                DB::rollBack();

                return $this->errorResponse(
                    'This chat session is closed.',
                    422,
                    'session_closed'
                );
            }

            $pricePerMinute = $this->getChatPricePerMinute($session);

            if ($pricePerMinute <= 0) {
                DB::rollBack();

                return $this->errorResponse(
                    'Chat is temporarily unavailable for this astrologer.',
                    422,
                    'invalid_chat_price'
                );
            }

            $freeLimit = $this->getFreeMessageLimit();
            $freeMessagesUsed = $this->countUserFreeMessages($user);

            // Never restart an already running billing period.
            if ($session->chat_active_since && !$session->chat_last_seen_at) {
                $walletBalance = $this->getWalletBalance($user);
                $availablePaidMinutes = (int) floor(
                    $walletBalance / $pricePerMinute
                );

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Chat is already running.',
                    'session_id' => $session->id,
                    'chat_active_since' => $session->chat_active_since,
                    'free_messages' => max(0, $freeLimit - $freeMessagesUsed),
                    'price_per_minute' => $pricePerMinute,
                            'available_paid_minutes' => $availablePaidMinutes,
                ]);
            }

            // Chat can start only when the user has at least ₹80.
            // This applies even when the first question is free.
            $minimumStartBalance = self::MINIMUM_CHAT_START_BALANCE;

            $wallet = $user->wallet()
                ->lockForUpdate()
                ->first();

            $walletBalance = $wallet
                ? round((float) $wallet->balance, 2)
                : 0.0;

            if (!$wallet || $walletBalance < $minimumStartBalance) {
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'type' => 'insufficient_balance',
                    'message' => 'Minimum ₹80 wallet balance is required to start chat. Please recharge your wallet to continue.',
                    'required_balance' => $minimumStartBalance,
                    'wallet_balance' => $walletBalance,
                    'price_per_minute' => $pricePerMinute,
                ], 422);
            }

            // First-time user can enter and consume the one free question.
            if ($freeMessagesUsed < $freeLimit) {
                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Chat started successfully. Your first question is free.',
                    'session_id' => $session->id,
                    'chat_started' => true,
                    'chat_active' => false,
                    'chat_stopped' => false,
                    'session_closed' => false,
                    'chat_active_since' => null,
                    'chat_last_seen_at' => null,
                    'free_messages' => $freeLimit - $freeMessagesUsed,
                    'price_per_minute' => $pricePerMinute,
                    'wallet_balance' => $walletBalance,
                    'available_paid_minutes' => (int) floor($walletBalance / $pricePerMinute),
                    'chat_billed_minutes' => (int) $session->chat_billed_minutes,
                    'total_amount' => (float) $session->total_amount,
                ]);
            }

            // Paid chat also needs enough balance for its first paid minute.
            $requiredStartBalance = max(
                self::MINIMUM_CHAT_START_BALANCE,
                $pricePerMinute
            );

            $availablePaidMinutes = (int) floor(
                $walletBalance / $pricePerMinute
            );

            if (!$wallet || $walletBalance < $requiredStartBalance) {
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'type' => 'insufficient_balance',
                    'message' => 'Minimum ₹80 wallet balance is required to start chat, and the wallet must also cover the first paid minute.',
                            'required_balance' => $requiredStartBalance,
                    'wallet_balance' => $walletBalance,
                    'available_paid_minutes' => $availablePaidMinutes,
                ], 422);
            }

            $now = now();

            // Start a fresh billing period for this session. Historical
            // total_amount remains cumulative, while chat_billed_minutes
            // tracks minutes paid in the current active period.
            $session->update([
                'chat_billed_minutes' => 0,
            ]);

            // Prepay the first paid minute so the current minute is always
            // fully funded and cannot be cut short.
            $this->debitChatMinutes(
                $user,
                $session,
                $wallet,
                1,
                'AI Astrology Chat'
            );

            $session->update([
                'status' => 'active',
                'closed_at' => null,
                'chat_active_since' => $now,
                'chat_last_seen_at' => null,
                // chat_billed_minutes was set to 1 by the prepaid debit above.
                // total_amount intentionally remains cumulative for this session.
                'chat_free_used' => true,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Chat started successfully.',
                'session_id' => $session->id,

                'chat_started' => true,
                'chat_active' => true,
                'chat_stopped' => false,
                'session_closed' => false,

                'chat_active_since' => $now,
                'chat_last_seen_at' => null,

                'free_messages' => 0,
                'price_per_minute' => $pricePerMinute,
                'wallet_balance' => $this->getWalletBalance($user),
                'available_paid_minutes' => max(0, $availablePaidMinutes - 1),

                'chat_billed_minutes' => (int) $session->chat_billed_minutes,
                'total_amount' => (float) $session->total_amount,

                'idle_timeout_seconds' => self::CHAT_IDLE_TIMEOUT_SECONDS,
                'idle_remaining_seconds' => self::CHAT_IDLE_TIMEOUT_SECONDS,
                'auto_close_at' => $now->copy()->addSeconds(
                    self::CHAT_IDLE_TIMEOUT_SECONDS
                ),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AI_CHAT_START_ERROR', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
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

    public function stopChat($sessionId, Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = User::where('id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $session = AiChatSession::with('astrologer')
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($sessionId);

            if (!$session->chat_active_since || $session->chat_last_seen_at) {
                DB::rollBack();

                return $this->errorResponse(
                    'This chat session is already stopped.',
                    422,
                    'session_closed'
                );
            }

            $lastSeenAt = now();
            $activeSince = Carbon::parse($session->chat_active_since);
            $elapsedSeconds = max(0, $activeSince->diffInSeconds($lastSeenAt));
            $requiredPaidMinutes = max(
                1,
                (int) ceil($elapsedSeconds / 60)
            );

            $alreadyBilledMinutes = (int) $session->chat_billed_minutes;
            $newMinutes = max(
                0,
                $requiredPaidMinutes - $alreadyBilledMinutes
            );

            $pricePerMinute = $this->getChatPricePerMinute($session);
            $unpaidMinutes = $newMinutes;
            $wallet = $user->wallet()
                ->lockForUpdate()
                ->first();

            if ($wallet && $newMinutes > 0 && $pricePerMinute > 0) {
                $affordableMinutes = (int) floor(
                    (float) $wallet->balance / $pricePerMinute
                );

                $minutesToCharge = min(
                    $newMinutes,
                    max(0, $affordableMinutes)
                );

                if ($minutesToCharge > 0) {
                    $this->debitChatMinutes(
                        $user,
                        $session,
                        $wallet,
                        $minutesToCharge,
                        'AI Astrology Chat'
                    );

                    $unpaidMinutes -= $minutesToCharge;
                }
            }

            // Manual stop always ends the current billing period.
            $session->update([
                'chat_active_since' => null,
                'chat_last_seen_at' => $lastSeenAt,
            ]);

            DB::commit();
            $session->refresh();

            if ($unpaidMinutes > 0) {
                return response()->json([
                    'status' => false,
                    'type' => 'insufficient_balance',
                    'chat_stopped' => true,
                    'message' => "You do not have enough wallet balance to settle all chat time, that's why chat end. Please recharge your wallet to continue chatting..",
                    'chat_active_since' => null,
                    'chat_last_seen_at' => $session->chat_last_seen_at,
                    'chat_billed_minutes' => (int) $session->chat_billed_minutes,
                    'total_amount' => (float) $session->total_amount,
                ], 422);
            }

            return response()->json([
                'status' => true,
                'message' => 'Chat stopped successfully.',
                'chat_stopped' => true,
                'chat_active_since' => null,
                'chat_last_seen_at' => $session->chat_last_seen_at,
                'chat_billed_minutes' => (int) $session->chat_billed_minutes,
                'total_amount' => (float) $session->total_amount,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('AI_CHAT_STOP_ERROR', [
                'user_id' => auth()->id(),
                'session_id' => $sessionId,
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
        /*
         * Backward-compatible alias: stop only the current billing period.
         * The permanent session/history is intentionally kept active.
         */
        return $this->stopChat($id, $request);
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
            $rawData = is_array($chart->raw_data)
                ? $chart->raw_data
                : (json_decode((string) $chart->raw_data, true) ?? []);

            if (!is_array($rawData)) {
                $rawData = [];
            }

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

        if (!is_array($relevantCharts)) {
            $relevantCharts = [];
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

            $raw = is_array($chart->raw_data)
                ? $chart->raw_data
                : (json_decode((string) $chart->raw_data, true) ?? []);

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