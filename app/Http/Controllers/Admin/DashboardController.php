<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends AdminController
{
    /**
     * Dashboard
     */
    public function getIndex(Request $request)
    {
        return view('admin.dashboard.index');
    }


    /**
     * TOP STATS
     */
    public function getStats(Request $request)
    {
        return response()->json([

            /**
             * Total Astrologers
             */
            'total_astrologers' => User::where('type', 'astro')
                ->count(),

            /**
             * Total Users
             */
            'total_users' => User::where('type', 'user')
                ->count(),

            /**
             * Online Astrologers
             */
            'online_astrologers' => User::where('type', 'astro')
                ->where('is_online', 1)
                ->whereDate('updated_at', now()->toDateString())
                ->count(),

            /**
             * Online Users
             */
            'online_users' => User::where('type', 'user')
                ->where('is_online', 1)
                ->whereDate('updated_at', now()->toDateString())
                ->count(),

            /**
             * Active Calls
             */
            'active_call_connections' => DB::table('call_sessions')
                ->where('status', 'active')
                ->count(),

            /**
             * Active AI Chats
             */
            'active_chat_connections' => DB::table('ai_chat_sessions')
                ->where('status', 'active')
                ->count(),
        ]);
    }


    /**
     * GRAPH 1
     *
     * PLATFORM GROWTH
     *
     * Daily registered users and astrologers.
     */
    public function getGrowthGraph(Request $request)
    {
        $start = Carbon::parse($request->start_date)
            ->startOfDay();

        $end = Carbon::parse($request->end_date)
            ->endOfDay();

        $dates = $this->getDaysBetweenDates($start, $end);


        /**
         * Astrologers registered per day
         */
        $astro = User::selectRaw(
            'DATE(created_at) as date, COUNT(id) as total'
        )
            ->where('type', 'astro')
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date')
            ->toArray();


        /**
         * Users registered per day
         */
        $users = User::selectRaw(
            'DATE(created_at) as date, COUNT(id) as total'
        )
            ->where('type', 'user')
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date')
            ->toArray();


        $labels = [];
        $astrologers = [];
        $customers = [];


        foreach ($dates as $date) {

            $labels[] = $date;

            $astrologers[] = $astro[$date] ?? 0;

            $customers[] = $users[$date] ?? 0;
        }


        return response()->json([
            'labels' => $labels,
            'astrologers' => $astrologers,
            'customers' => $customers,
        ]);
    }


    /**
     * GRAPH 2
     *
     * USER CONNECTIONS
     *
     * Daily calls and AI chat sessions.
     *
     * IMPORTANT:
     * AI chats are counted based on actual user messages,
     * not session creation date.
     */
    public function getEngagementGraph(Request $request)
    {
        $start = Carbon::parse($request->start_date)
            ->startOfDay();

        $end = Carbon::parse($request->end_date)
            ->endOfDay();

        $dates = $this->getDaysBetweenDates($start, $end);


        /**
         * Calls
         */
        $calls = DB::table('call_sessions')
            ->selectRaw(
                'DATE(created_at) as date, COUNT(id) as total'
            )
            ->whereBetween('created_at', [$start, $end])
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date')
            ->toArray();


        /**
         * AI Chat Sessions
         *
         * Count only sessions in which a USER actually sent
         * at least one message during the selected date range.
         *
         * This fixes the case where:
         *
         * session created = yesterday
         * user chatted = today
         *
         * In that case today's graph will still show the chat.
         */
        $chats = DB::table('ai_chat_messages as messages')
            ->join(
                'ai_chat_sessions as sessions',
                'messages.session_id',
                '=',
                'sessions.id'
            )
            ->where('messages.sender', 'user')
            ->whereBetween(
                'messages.created_at',
                [$start, $end]
            )
            ->selectRaw(
                'DATE(messages.created_at) as date,
                COUNT(DISTINCT messages.session_id) as total'
            )
            ->groupByRaw('DATE(messages.created_at)')
            ->pluck('total', 'date')
            ->toArray();


        $labels = [];
        $callData = [];
        $chatData = [];


        foreach ($dates as $date) {

            $labels[] = $date;

            $callData[] = $calls[$date] ?? 0;

            $chatData[] = $chats[$date] ?? 0;
        }


        return response()->json([
            'labels' => $labels,
            'calls' => $callData,
            'chats' => $chatData,
        ]);
    }


    /**
     * GRAPH 3
     *
     * USERS BY EXPERTISE
     *
     * Unique users who actually sent a chat message
     * during the selected date range.
     */
    public function getExpertiseUsersGraph(Request $request)
    {
        $start = Carbon::parse($request->start_date)
            ->startOfDay();

        $end = Carbon::parse($request->end_date)
            ->endOfDay();


        $data = DB::table('ai_chat_messages as messages')
            ->join(
                'ai_chat_sessions as sessions',
                'messages.session_id',
                '=',
                'sessions.id'
            )
            ->join(
                'ai_astrologer_expertises as expertises',
                'sessions.expertise_id',
                '=',
                'expertises.id'
            )
            ->where('messages.sender', 'user')
            ->whereBetween(
                'messages.created_at',
                [$start, $end]
            )
            ->select(
                'expertises.id',
                'expertises.name'
            )
            ->selectRaw(
                'COUNT(DISTINCT sessions.user_id) as users'
            )
            ->groupBy(
                'expertises.id',
                'expertises.name'
            )
            ->orderByDesc('users')
            ->get();


        return response()->json([
            'labels' => $data->pluck('name')->values(),
            'users' => $data->pluck('users')->values(),
        ]);
    }


    /**
     * GRAPH 4
     *
     * USERS BY ASTROLOGER
     *
     * Unique users who actually chatted with each AI astrologer
     * during the selected date range.
     */
    public function getAstrologerUsersGraph(Request $request)
    {
        $start = Carbon::parse($request->start_date)
            ->startOfDay();

        $end = Carbon::parse($request->end_date)
            ->endOfDay();


        $data = DB::table('ai_chat_messages as messages')
            ->join(
                'ai_chat_sessions as sessions',
                'messages.session_id',
                '=',
                'sessions.id'
            )
            ->join(
                'ai_astrologers as astrologers',
                'sessions.astrologer_id',
                '=',
                'astrologers.id'
            )
            ->where('messages.sender', 'user')
            ->whereBetween(
                'messages.created_at',
                [$start, $end]
            )
            ->select(
                'astrologers.id',
                'astrologers.name'
            )
            ->selectRaw(
                'COUNT(DISTINCT sessions.user_id) as users'
            )
            ->groupBy(
                'astrologers.id',
                'astrologers.name'
            )
            ->orderByDesc('users')
            ->get();


        return response()->json([
            'labels' => $data->pluck('name')->values(),
            'users' => $data->pluck('users')->values(),
        ]);
    }


    /**
     * GRAPH 5
     *
     * SPEND BY ASTROLOGER
     *
     * Uses ai_chat_transactions as billing source.
     *
     * Only debit transactions are counted.
     */
    public function getAstrologerSpendGraph(Request $request)
    {
        $start = Carbon::parse($request->start_date)
            ->startOfDay();

        $end = Carbon::parse($request->end_date)
            ->endOfDay();


        $data = DB::table('ai_chat_transactions as transactions')
            ->join(
                'ai_chat_sessions as sessions',
                'transactions.session_id',
                '=',
                'sessions.id'
            )
            ->join(
                'ai_astrologers as astrologers',
                'sessions.astrologer_id',
                '=',
                'astrologers.id'
            )
            ->where(
                'transactions.type',
                'debit'
            )
            ->whereBetween(
                'transactions.created_at',
                [$start, $end]
            )
            ->select(
                'astrologers.id',
                'astrologers.name'
            )
            ->selectRaw(
                'COUNT(DISTINCT transactions.user_id) as users'
            )
            ->selectRaw(
                'COALESCE(SUM(transactions.amount), 0) as amount'
            )
            ->groupBy(
                'astrologers.id',
                'astrologers.name'
            )
            ->orderByDesc('amount')
            ->get();


        return response()->json([
            'labels' => $data->pluck('name')->values(),
            'users' => $data->pluck('users')->values(),
            'amount' => $data->pluck('amount')->values(),
        ]);
    }


    /**
     * GRAPH 6
     *
     * REVIEWS BY ASTROLOGER
     *
     * Only active reviews are counted.
     */
    public function getAstrologerReviewsGraph(Request $request)
    {
        $start = Carbon::parse($request->start_date)
            ->startOfDay();

        $end = Carbon::parse($request->end_date)
            ->endOfDay();


        $data = DB::table('astrologer_reviews as reviews')
            ->join(
                'ai_astrologers as astrologers',
                'reviews.astrologer_id',
                '=',
                'astrologers.id'
            )
            ->where(
                'reviews.is_active',
                1
            )
            ->whereBetween(
                'reviews.created_at',
                [$start, $end]
            )
            ->select(
                'astrologers.id',
                'astrologers.name'
            )
            ->selectRaw(
                'COUNT(reviews.id) as reviews'
            )
            ->groupBy(
                'astrologers.id',
                'astrologers.name'
            )
            ->orderByDesc('reviews')
            ->get();


        return response()->json([
            'labels' => $data->pluck('name')->values(),
            'reviews' => $data->pluck('reviews')->values(),
        ]);
    }


    /**
     * GET DATES BETWEEN START AND END
     */
    private function getDaysBetweenDates($start, $end)
    {
        $start = Carbon::parse($start);

        $end = Carbon::parse($end);

        $dates = [];


        while ($start->lte($end)) {

            $dates[] = $start->toDateString();

            $start->addDay();
        }


        return $dates;
    }
}