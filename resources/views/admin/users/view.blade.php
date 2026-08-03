<div class="modal-header">
    <h5 class="modal-title">User Details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

    <div class="row">

        <!-- ==========================================================
            SECTION 1 : PROFILE + REVIEW SUMMARY
        ========================================================== -->

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-body text-center">

                    <img src="{{ $user->profile_image_url }}" class="rounded-circle border shadow" width="170"
                        height="170" style="object-fit:cover;">

                    <h5 class="mt-3 mb-1">
                        {{ $user->name }}
                    </h5>

                    <div class="text-muted">

                        {{ $user->code }}

                    </div>

                    <div class="mt-2">

                        @if ($user->status)
                            <span class="badge bg-success">

                                Active

                            </span>
                        @else
                            <span class="badge bg-danger">

                                Inactive

                            </span>
                        @endif

                    </div>

                </div>

            </div>

            <div class="card shadow-sm mt-3">

                <div class="card-header bg-primary text-white">

                    Review Summary

                </div>

                <div class="card-body p-2">

                    <table class="table table-bordered table-sm mb-0">

                        <tr>

                            <th>Total Reviews</th>

                            <td>

                                {{ $user->reviews->count() }}

                            </td>

                        </tr>

                        <tr>

                            <th>Average Rating</th>

                            <td>

                                <strong>

                                    {{ number_format($user->reviews->avg('rating') ?? 0, 1) }}

                                </strong>

                                <br>

                                <span class="text-warning">

                                    @for ($i = 1; $i <= 5; $i++)
                                        {!! $i <= round($user->reviews->avg('rating')) ? '&#9733;' : '&#9734;' !!}
                                    @endfor

                                </span>

                            </td>

                        </tr>

                    </table>

                </div>

            </div>

            <div class="card shadow-sm mt-3">

                <div class="card-header bg-info text-white">

                    Latest Reviews

                </div>

                <div class="card-body p-2" style="max-height:320px;overflow-y:auto;">

                    @forelse($latest_reviews as $review)

                        <div class="border rounded p-2 mb-2">

                            <div class="fw-bold">

                                {{ $review->astrologer->code }}

                                -

                                {{ $review->astrologer->name }}

                            </div>

                            <div class="text-warning">

                                @for ($i = 1; $i <= 5; $i++)
                                    {!! $i <= $review->rating ? '&#9733;' : '&#9734;' !!}
                                @endfor

                            </div>

                            <div class="small mt-1">

                                {{ $review->review ?: 'No Review Comment' }}

                            </div>

                            <div class="text-muted small mt-2">

                                {{ $review->created_at->format('d M Y h:i A') }}

                            </div>

                        </div>

                    @empty

                        <div class="alert alert-light text-center mb-0">

                            No Reviews Found

                        </div>

                    @endforelse

                </div>

            </div>

        </div>

        <!-- ==========================================================
    SECTION 2 : BASIC INFORMATION
========================================================== -->

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-header bg-primary text-white">

                    Basic Information

                </div>

                <div class="card-body p-2">

                    <table class="table table-bordered table-striped table-sm mb-0">

                        <tr>
                            <th width="38%">User Code</th>
                            <td>{{ $user->code ?? 'N/A' }}</td>
                        </tr>

                        <tr>
                            <th>Full Name</th>
                            <td>{{ $user->name }}</td>
                        </tr>

                        <tr>
                            <th>Email</th>
                            <td>{{ $user->email ?: 'N/A' }}</td>
                        </tr>

                        <tr>
                            <th>Mobile</th>
                            <td>

                                {{ $user->country_code }}

                                {{ $user->mobile ?: 'N/A' }}

                            </td>
                        </tr>

                        <tr>
                            <th>Gender</th>

                            <td>

                                @if ($user->gender)
                                    <span class="badge bg-info">

                                        {{ ucfirst($user->gender) }}

                                    </span>
                                @else
                                    N/A
                                @endif

                            </td>

                        </tr>

                        <tr>

                            <th>Date of Birth</th>

                            <td>

                                {{ $user->dob ? $user->dob->format('d M Y') : 'N/A' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Birth Time</th>

                            <td>

                                {{ $user->birth_time ? \Carbon\Carbon::parse($user->birth_time)->format('h:i A') : 'N/A' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Birth Place</th>

                            <td>

                                {{ $user->birth_place['place'] ?? 'N/A' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Marital Status</th>

                            <td>

                                {{ $user->marital_status ?: 'N/A' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Occupation</th>

                            <td>

                                {{ $user->occupation ?: 'N/A' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Pincode</th>

                            <td>

                                {{ $user->pincode ?: 'N/A' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Address</th>

                            <td>

                                {{ $user->address ?: 'N/A' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Joined On</th>

                            <td>

                                {{ $user->created_at->format('d M Y h:i A') }}

                            </td>

                        </tr>

                        <tr>

                            <th>Last Seen</th>

                            <td>

                                {{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : 'Never' }}

                            </td>

                        </tr>

                    </table>

                </div>

            </div>

        </div>

        <!-- ==========================================================
    SECTION 3 : WALLET + AI CHAT SUMMARY
========================================================== -->

        <div class="col-md-3">

            <!-- Wallet Summary -->

            <div class="card shadow-sm">

                <div class="card-header bg-success text-white">

                    Wallet Summary

                </div>

                <div class="card-body p-2">

                    <table class="table table-bordered table-sm mb-0">

                        <tr>
                            <th width="55%">Current Balance</th>
                            <td>₹ {{ number_format($user->wallet->balance ?? 0, 2) }}</td>
                        </tr>

                        <tr>
                            <th>Total Added</th>
                            <td>₹ {{ number_format($user->wallet->total_added ?? 0, 2) }}</td>
                        </tr>

                        <tr>
                            <th>Total Spent</th>
                            <td>₹ {{ number_format($user->wallet->total_spent ?? 0, 2) }}</td>
                        </tr>

                        <tr>
                            <th>Call Spent</th>
                            <td>₹ {{ number_format($callSummary->total_amount ?? 0, 2) }}</td>
                        </tr>

                        <tr>
                            <th>AI Chat Spent</th>
                            <td>₹ {{ number_format($aiChatSummary->total_amount ?? 0, 2) }}</td>
                        </tr>

                        <tr>
                            <th>Last Recharge</th>
                            <td>

                                @if (optional($user->wallet)->last_recharge_at)
                                    ₹ {{ number_format($user->wallet->last_recharge_amount, 2) }}

                                    <br>

                                    <small class="text-muted">

                                        {{ \Carbon\Carbon::parse($user->wallet->last_recharge_at)->format('d M Y h:i A') }}

                                    </small>
                                @else
                                    N/A
                                @endif

                            </td>

                        </tr>

                        <tr>
                            <th>Wallet Created</th>
                            <td>

                                {{ optional($user->wallet)->created_at ? $user->wallet->created_at->format('d M Y') : 'N/A' }}

                            </td>
                        </tr>

                    </table>

                </div>

            </div>

            <!-- AI Chat Summary -->

            <div class="card shadow-sm mt-3">

                <div class="card-header bg-info text-white">

                    AI Chat Summary

                </div>

                <div class="card-body p-2">

                    <table class="table table-bordered table-sm mb-0">

                        <tr>
                            <th>Total Sessions</th>
                            <td>{{ $aiChatSummary->total_sessions }}</td>
                        </tr>

                        <tr>
                            <th>Free Questions</th>
                            <td>{{ $aiChatSummary->free_questions }}</td>
                        </tr>

                        <tr>
                            <th>Paid Questions</th>
                            <td>{{ $aiChatSummary->paid_questions }}</td>
                        </tr>

                        <tr>
                            <th>Total Questions</th>
                            <td>{{ $totalQuestions }}</td>
                        </tr>

                        <tr>
                            <th>Total AI Replies</th>
                            <td>{{ $totalReplies }}</td>
                        </tr>

                        <tr>
                            <th>Total Spent</th>
                            <td>

                                ₹ {{ number_format($aiChatSummary->total_amount, 2) }}

                            </td>

                        </tr>

                        <tr>

                            <th>Average Cost</th>

                            <td>

                                ₹

                                {{ $aiChatSummary->paid_questions
                                    ? number_format($aiChatSummary->total_amount / $aiChatSummary->paid_questions, 2)
                                    : '0.00' }}

                            </td>

                        </tr>

                        <tr>

                            <th>Last AI Chat</th>

                            <td>

                                {{ $lastChat ? \Carbon\Carbon::parse($lastChat)->format('d M Y h:i A') : 'N/A' }}

                            </td>

                        </tr>

                    </table>

                </div>

            </div>

            <!-- Account -->

            <div class="card shadow-sm mt-3">

                <div class="card-header bg-dark text-white">

                    Account Status

                </div>

                <div class="card-body p-2">

                    <table class="table table-bordered table-sm mb-0">

                        <tr>

                            <th>Status</th>

                            <td>

                                @if ($user->status)
                                    <span class="badge bg-success">

                                        Active

                                    </span>
                                @else
                                    <span class="badge bg-danger">

                                        Inactive

                                    </span>
                                @endif

                            </td>

                        </tr>

                        <tr>

                            <th>Created</th>

                            <td>

                                {{ $user->created_at->format('d M Y h:i A') }}

                            </td>

                        </tr>

                        <tr>

                            <th>Last Seen</th>

                            <td>

                                {{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : 'Never' }}

                            </td>

                        </tr>

                    </table>

                </div>

            </div>

        </div>

        <!-- ==========================================================
    SECTION 4 : RECHARGE HISTORY
========================================================== -->

        <div class="col-md-3">

            <div class="card shadow-sm">

                <div class="card-header bg-warning">

                    <strong>Recharge History</strong>

                </div>

                <div class="card-body p-2" style="max-height:650px;overflow-y:auto;">

                    @forelse($rechargeHistory as $recharge)
                        <div class="border rounded p-2 mb-2">

                            <div class="d-flex justify-content-between">

                                <div>

                                    <strong>

                                        ₹ {{ number_format($recharge->amount, 2) }}

                                    </strong>

                                </div>

                                <div>

                                    <span class="badge bg-success">

                                        Recharge

                                    </span>

                                </div>

                            </div>

                            <table class="table table-sm table-borderless mt-2 mb-0">

                                <tr>

                                    <th width="40%">

                                        Balance Before

                                    </th>

                                    <td>

                                        ₹ {{ number_format($recharge->balance_before, 2) }}

                                    </td>

                                </tr>

                                <tr>

                                    <th>

                                        Balance After

                                    </th>

                                    <td>

                                        ₹ {{ number_format($recharge->balance_after, 2) }}

                                    </td>

                                </tr>

                                <tr>

                                    <th>

                                        Payment Method

                                    </th>

                                    <td>

                                        {{ strtoupper($recharge->payment_method ?? 'N/A') }}

                                    </td>

                                </tr>

                                <tr>

                                    <th>

                                        Gateway Txn

                                    </th>

                                    <td>

                                        {{ $recharge->gateway_txn_id ?: 'N/A' }}

                                    </td>

                                </tr>

                                <tr>

                                    <th>

                                        Recharge Time

                                    </th>

                                    <td>

                                        {{ \Carbon\Carbon::parse($recharge->recharged_at)->format('d M Y h:i A') }}

                                    </td>

                                </tr>

                            </table>

                        </div>

                    @empty

                        <div class="alert alert-light text-center mb-0">

                            No Recharge History Found

                        </div>
                    @endforelse

                </div>

            </div>

        </div>

        <!-- ==========================================================
    SECTION 5 : AI CHAT TRANSACTIONS
========================================================== -->

        <div class="col-md-12 mt-4">

            <div class="card shadow-sm">

                <div class="card-header bg-dark text-white">

                    <strong>AI Chat Transactions</strong>

                </div>

                <div class="card-body p-0">

                    <div style="max-height:400px;overflow-y:auto;">

                        <table class="table table-bordered table-hover table-sm mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>Date</th>

                                    <th>AI Astrologer</th>

                                    <th>Expertise</th>

                                    <th>Type</th>

                                    <th>Amount</th>

                                    <th>Wallet Before</th>

                                    <th>Wallet After</th>

                                    <th>Remark</th>

                                </tr>

                            </thead>

                            <tbody>

                                @forelse($aiTransactions as $tx)
                                    <tr>

                                        @php
                                            $createdAt = \Carbon\Carbon::parse($tx->created_at);
                                        @endphp

                                        <td width="150">
                                            {{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y') }}
                                            <br>
                                            <small class="text-muted">
                                                {{ \Carbon\Carbon::parse($tx->created_at)->format('h:i A') }}
                                            </small>
                                        </td>

                                        <td>

                                            <strong>

                                                {{ $tx->astrologer_name }}

                                            </strong>

                                        </td>

                                        <td>

                                            {{ $tx->expertise_name }}

                                        </td>

                                        <td>

                                            @if ($tx->type == 'debit')
                                                <span class="badge bg-danger">

                                                    Debit

                                                </span>
                                            @else
                                                <span class="badge bg-success">

                                                    Credit

                                                </span>
                                            @endif

                                        </td>

                                        <td>

                                            ₹ {{ number_format($tx->amount, 2) }}

                                        </td>

                                        <td>

                                            ₹ {{ number_format($tx->balance_before, 2) }}

                                        </td>

                                        <td>

                                            ₹ {{ number_format($tx->balance_after, 2) }}

                                        </td>

                                        <td>

                                            {{ $tx->remark ?? '-' }}

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td colspan="8" class="text-center text-muted">

                                            No AI Chat Transactions Found

                                        </td>

                                    </tr>
                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

        <!-- ==========================================================
            SECTION 6 : AI CHAT SESSIONS
        ========================================================== -->

        <div class="col-md-12 mt-4">

            <div class="card shadow-sm">

                <div class="card-header bg-primary text-white">

                    <strong>

                        AI Chat Sessions

                    </strong>

                </div>

                <div class="card-body">

                    @forelse($aiChatHistory as $chat)
                        @php

                            $conversation = [];

                            $userQuestion = null;

                            foreach ($chat->messages->sortBy('id') as $message) {
                                if ($message->sender == 'user') {
                                    $userQuestion = $message;
                                } elseif ($message->sender == 'assistant' && $userQuestion) {
                                    $conversation[] = [
                                        'question' => $userQuestion,

                                        'answer' => $message,
                                    ];

                                    $userQuestion = null;
                                }
                            }

                            $conversation = collect($conversation)->take(-3);

                        @endphp
                        <div class="card border mb-4">

                            <div class="card-body">

                                <div class="row align-items-center">

                                    <div class="col-md-1 text-center">

                                        <img src="{{ $chat->astrologer->image
                                            ? asset('storage/aiAstrologers/' . $chat->astrologer->image)
                                            : asset('default-user.png') }}"
                                            class="rounded-circle border" width="70" height="70"
                                            style="object-fit:cover;">

                                    </div>

                                    <div class="col-md-5">

                                        <h5 class="mb-1">

                                            {{ $chat->astrologer->name }}

                                        </h5>

                                        <div class="text-muted">

                                            {{ $chat->expertise->name }}

                                        </div>

                                    </div>

                                    <div class="col-md-6">

                                        <table class="table table-bordered table-sm mb-0">

                                            <tr>

                                                <th>Started</th>

                                                <td>

                                                    {{ optional($chat->started_at)->format('d M Y h:i A') }}

                                                </td>

                                            </tr>

                                            <tr>

                                                <th>Last Message</th>

                                                <td>

                                                    {{ optional($chat->last_message_at)->format('d M Y h:i A') }}

                                                </td>

                                            </tr>

                                            <tr>

                                                <th>Free Questions</th>

                                                <td>

                                                    {{ $chat->free_messages_used }}

                                                </td>

                                            </tr>

                                            <tr>

                                                <th>Paid Questions</th>

                                                <td>

                                                    {{ $chat->paid_messages }}

                                                </td>

                                            </tr>

                                            <tr>

                                                <th>Total Questions</th>

                                                <td>

                                                    {{ $chat->free_messages_used + $chat->paid_messages }}

                                                </td>

                                            </tr>

                                            <tr>

                                                <th>Total Amount</th>

                                                <td>

                                                    ₹ {{ number_format($chat->total_amount, 2) }}

                                                </td>

                                            </tr>

                                        </table>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <h6 class="mt-3 text-primary">

                            Last 3 Questions & Answers

                        </h6>

                        @forelse($conversation as $chatItem)
                            <div class="border rounded p-3 mb-3">

                                <strong class="text-primary">

                                    Q.

                                </strong>

                                {{ $chatItem['question']->message }}

                                <hr>

                                <strong class="text-success">

                                    AI

                                </strong>

                                {{ $chatItem['answer']->message }}

                            </div>

                        @empty

                            <p class="text-muted">

                                No Conversation Found

                            </p>
                        @endforelse

                    @empty

                        <div class="alert alert-light">

                            No AI Chat Sessions Found

                        </div>
                    @endforelse

                </div>

            </div>

        </div>



    </div>

</div>

<div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
