<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiAstrologer;
use App\Models\AstrologerReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiAstrologerReviewController extends Controller
{
    /**
     * Add or update a review for an astrologer.
     *
     * User ID is always taken from the authenticated user.
     *
     * Astrologer can be identified by:
     * - astrologer_id
     * - astrologer_slug
     * - or both
     *
     * If both ID and slug are provided, they must belong
     * to the same astrologer.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'astrologer_id' => [
                'nullable',
                'integer',
                'exists:ai_astrologers,id',
            ],

            'astrologer_slug' => [
                'nullable',
                'string',
                'max:255',
            ],

            'rating' => [
                'required',
                'integer',
                'between:1,5',
            ],

            'review' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        /*
         * At least one astrologer identifier is required.
         */
        if (
            empty($validated['astrologer_id']) &&
            empty($validated['astrologer_slug'])
        ) {
            throw ValidationException::withMessages([
                'astrologer' => [
                    'Either astrologer_id or astrologer_slug is required.',
                ],
            ]);
        }

        /*
         * Find astrologer by ID, slug, or both.
         */
        $astrologer = $this->findAstrologer($validated);

        if (!$astrologer) {
            return response()->json([
                'status' => false,
                'message' => $this->hasBothAstrologerIdentifiers($validated)
                    ? 'Invalid astrologer ID and slug combination.'
                    : 'Astrologer not found.',
            ], $this->hasBothAstrologerIdentifiers($validated) ? 422 : 404);
        }

        /*
         * Create or update the user's review for this astrologer.
         *
         * One user can review many different astrologers,
         * but one user can have only one active review per astrologer.
         */
        $review = DB::transaction(function () use (
            $user,
            $astrologer,
            $validated
        ) {
            return AstrologerReview::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'astrologer_id' => $astrologer->id,
                ],
                [
                    'rating' => $validated['rating'],
                    'review' => $validated['review'] ?? null,
                    'is_active' => true,
                ]
            );
        });

        /*
         * Get updated rating statistics.
         */
        $ratingStats = $this->getRatingStats($astrologer->id);

        $message = $review->wasRecentlyCreated
            ? 'Review submitted successfully.'
            : 'Review updated successfully.';

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => [
                'id' => $review->id,
                'astrologer_id' => $astrologer->id,
                'astrologer_slug' => $astrologer->slug,
                'rating' => (int) $review->rating,
                'review' => $review->review,
                'average_rating' => $ratingStats['average_rating'],
                'total_reviews' => $ratingStats['total_reviews'],
                'created_at' => $review->created_at,
                'updated_at' => $review->updated_at,
            ],
        ]);
    }

    /**
     * Get astrologer reviews.
     *
     * CASE 1:
     * GET /api/astrologer/reviews
     *
     * Returns active reviews of ALL astrologers.
     *
     * CASE 2:
     * GET /api/astrologer/reviews?astrologer_id=8
     *
     * Returns active reviews of astrologer ID 8 only.
     *
     * CASE 3:
     * GET /api/astrologer/reviews?astrologer_slug=dev-malhotra
     *
     * Returns active reviews of the given astrologer only.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'astrologer_id' => [
                'nullable',
                'integer',
            ],

            'astrologer_slug' => [
                'nullable',
                'string',
                'max:255',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $hasAstrologerFilter =
            !empty($validated['astrologer_id']) ||
            !empty($validated['astrologer_slug']);

        /*
         * ---------------------------------------------------------
         * CASE 1:
         * Specific astrologer requested
         * ---------------------------------------------------------
         */
        if ($hasAstrologerFilter) {
            $astrologer = $this->findAstrologer($validated);

            if (!$astrologer) {
                return response()->json([
                    'status' => false,
                    'message' => $this->hasBothAstrologerIdentifiers($validated)
                        ? 'Invalid astrologer ID and slug combination.'
                        : 'Astrologer not found.',
                ], $this->hasBothAstrologerIdentifiers($validated) ? 422 : 404);
            }

            $perPage = $validated['per_page'] ?? 10;

            $reviews = AstrologerReview::query()
                ->with([
                    'user:id,name',
                    'astrologer:id,name,slug',
                ])
                ->where('astrologer_id', $astrologer->id)
                ->where('is_active', true)
                ->latest()
                ->paginate($perPage);

            $ratingStats = $this->getRatingStats($astrologer->id);

            return response()->json([
                'status' => true,
                'message' => 'Astrologer reviews fetched successfully.',
                'data' => [
                    'astrologer' => [
                        'id' => $astrologer->id,
                        'name' => $astrologer->name,
                        'slug' => $astrologer->slug,
                    ],

                    'rating' => [
                        'average' => $ratingStats['average_rating'],
                        'total_reviews' => $ratingStats['total_reviews'],
                    ],

                    'reviews' => $reviews,
                ],
            ]);
        }

        /*
         * ---------------------------------------------------------
         * CASE 2:
         * No astrologer filter
         *
         * Return reviews of ALL astrologers.
         * ---------------------------------------------------------
         */
        $perPage = $validated['per_page'] ?? 20;

        $reviews = AstrologerReview::query()
            ->with([
                'user:id,name',
                'astrologer:id,name,slug',
            ])
            ->where('is_active', true)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'All astrologer reviews fetched successfully.',
            'data' => [
                'total_reviews' => $reviews->total(),
                'reviews' => $reviews,
            ],
        ]);
    }

    /**
     * Get reviews submitted by the logged-in user.
     *
     * CASE 1:
     * GET /api/user/astrologer/my-review
     *
     * Returns ALL active reviews submitted by the logged-in user.
     *
     * CASE 2:
     * GET /api/user/astrologer/my-review?astrologer_id=8
     *
     * Returns only the logged-in user's review for astrologer 8.
     *
     * CASE 3:
     * GET /api/user/astrologer/my-review?astrologer_slug=dev-malhotra
     *
     * Returns only the logged-in user's review for that astrologer.
     */
    public function myReview(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'astrologer_id' => [
                'nullable',
                'integer',
            ],

            'astrologer_slug' => [
                'nullable',
                'string',
                'max:255',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $hasAstrologerFilter =
            !empty($validated['astrologer_id']) ||
            !empty($validated['astrologer_slug']);

        /*
         * ---------------------------------------------------------
         * CASE 1:
         * Specific astrologer requested
         * ---------------------------------------------------------
         */
        if ($hasAstrologerFilter) {
            $astrologer = $this->findAstrologer($validated);

            if (!$astrologer) {
                return response()->json([
                    'status' => false,
                    'message' => $this->hasBothAstrologerIdentifiers($validated)
                        ? 'Invalid astrologer ID and slug combination.'
                        : 'Astrologer not found.',
                ], $this->hasBothAstrologerIdentifiers($validated) ? 422 : 404);
            }

            $review = AstrologerReview::query()
                ->with([
                    'astrologer:id,name,slug',
                ])
                ->where('user_id', $user->id)
                ->where('astrologer_id', $astrologer->id)
                ->where('is_active', true)
                ->first();

            return response()->json([
                'status' => true,
                'message' => $review
                    ? 'Your review fetched successfully.'
                    : 'You have not reviewed this astrologer yet.',
                'data' => $review,
            ]);
        }

        /*
         * ---------------------------------------------------------
         * CASE 2:
         * No astrologer filter
         *
         * Return ALL reviews of logged-in user.
         * ---------------------------------------------------------
         */
        $perPage = $validated['per_page'] ?? 10;

        $reviews = AstrologerReview::query()
            ->with([
                'astrologer:id,name,slug',
            ])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Your astrologer reviews fetched successfully.',
            'data' => [
                'total_reviews' => $reviews->total(),
                'reviews' => $reviews,
            ],
        ]);
    }

    /**
     * Delete logged-in user's own review.
     *
     * The review is not permanently deleted.
     * It is deactivated using is_active = false.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $review = AstrologerReview::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$review) {
            return response()->json([
                'status' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        $review->update([
            'is_active' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Review deleted successfully.',
        ]);
    }

    /**
     * Find astrologer by ID, slug, or both.
     *
     * If both are supplied, they must refer to the same astrologer.
     */
    private function findAstrologer(array $validated): ?AiAstrologer
    {
        $query = AiAstrologer::query();

        if (!empty($validated['astrologer_id'])) {
            $query->where('id', $validated['astrologer_id']);
        }

        if (!empty($validated['astrologer_slug'])) {
            $query->where('slug', $validated['astrologer_slug']);
        }

        return $query->first();
    }

    /**
     * Check whether both astrologer ID and slug were supplied.
     */
    private function hasBothAstrologerIdentifiers(array $validated): bool
    {
        return !empty($validated['astrologer_id'])
            && !empty($validated['astrologer_slug']);
    }

    /**
     * Get rating statistics for an astrologer.
     */
    private function getRatingStats(int $astrologerId): array
    {
        $stats = AstrologerReview::query()
            ->where('astrologer_id', $astrologerId)
            ->where('is_active', true)
            ->selectRaw(
                'AVG(rating) as average_rating, COUNT(*) as total_reviews'
            )
            ->first();

        return [
            'average_rating' => round(
                (float) ($stats->average_rating ?? 0),
                1
            ),
            'total_reviews' => (int) ($stats->total_reviews ?? 0),
        ];
    }
}