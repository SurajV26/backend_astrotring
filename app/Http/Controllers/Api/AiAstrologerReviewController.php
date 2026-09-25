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
         * One user can review multiple astrologers.
         * Same user + same astrologer = update existing review.
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
         * Load complete response data.
         */
        $review->load([
            'user:id,name,profile_image',
            'astrologer:id,name,slug',
            'astrologer.expertises:id,ai_astrologer_id,name,slug',
        ]);

        $ratingStats = $this->getRatingStats($astrologer->id);

        $message = $review->wasRecentlyCreated
            ? 'Review submitted successfully.'
            : 'Review updated successfully.';

        return response()->json([
            'status' => true,
            'message' => $message,

            'data' => [
                'id' => $review->id,

                'user' => [
                    'id' => $review->user->id,
                    'name' => $review->user->name,
                    'profile_image' => $this->getProfileImageUrl(
                        $review->user->profile_image
                    ),
                ],

                'astrologer' => [
                    'id' => $review->astrologer->id,
                    'name' => $review->astrologer->name,
                    'slug' => $review->astrologer->slug,

                    'expertises' => $review->astrologer->expertises
                        ->map(function ($expertise) {
                            return [
                                'id' => $expertise->id,
                                'name' => $expertise->name,
                                'slug' => $expertise->slug,
                            ];
                        })
                        ->values(),
                ],

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
     * Without filter:
     * GET /api/astrologer/reviews
     *
     * Returns all active reviews of all astrologers.
     *
     * With ID:
     * GET /api/astrologer/reviews?astrologer_id=8
     *
     * With slug:
     * GET /api/astrologer/reviews?astrologer_slug=dev-malhotra
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
         * =========================================================
         * SPECIFIC ASTROLOGER
         * =========================================================
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
                    'user:id,name,profile_image',

                    'astrologer:id,name,slug',

                    'astrologer.expertises:id,ai_astrologer_id,name,slug',
                ])
                ->where('astrologer_id', $astrologer->id)
                ->where('is_active', true)
                ->latest()
                ->paginate($perPage);

            $reviews->getCollection()->transform(
                fn ($review) => $this->formatReview($review)
            );

            $ratingStats = $this->getRatingStats($astrologer->id);

            return response()->json([
                'status' => true,
                'message' => 'Astrologer reviews fetched successfully.',

                'data' => [
                    'astrologer' => [
                        'id' => $astrologer->id,
                        'name' => $astrologer->name,
                        'slug' => $astrologer->slug,

                        'expertises' => $astrologer->load('expertises')
                            ->expertises
                            ->map(function ($expertise) {
                                return [
                                    'id' => $expertise->id,
                                    'name' => $expertise->name,
                                    'slug' => $expertise->slug,
                                ];
                            })
                            ->values(),
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
         * =========================================================
         * ALL ASTROLOGERS
         * =========================================================
         */
        $perPage = $validated['per_page'] ?? 20;

        $reviews = AstrologerReview::query()
            ->with([
                'user:id,name,profile_image',

                'astrologer:id,name,slug',

                'astrologer.expertises:id,ai_astrologer_id,name,slug',
            ])
            ->where('is_active', true)
            ->latest()
            ->paginate($perPage);

        $reviews->getCollection()->transform(
            fn ($review) => $this->formatReview($review)
        );

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
     * Get reviews of logged-in user.
     *
     * Without filter:
     * returns all reviews given by logged-in user.
     *
     * With astrologer ID/slug:
     * returns only that user's review for that astrologer.
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
         * =========================================================
         * SPECIFIC ASTROLOGER
         * =========================================================
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
                    'user:id,name,profile_image',

                    'astrologer:id,name,slug',

                    'astrologer.expertises:id,ai_astrologer_id,name,slug',
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

                'data' => $review
                    ? $this->formatReview($review)
                    : null,
            ]);
        }

        /*
         * =========================================================
         * ALL REVIEWS OF LOGGED-IN USER
         * =========================================================
         */
        $perPage = $validated['per_page'] ?? 10;

        $reviews = AstrologerReview::query()
            ->with([
                'user:id,name,profile_image',

                'astrologer:id,name,slug',

                'astrologer.expertises:id,ai_astrologer_id,name,slug',
            ])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest()
            ->paginate($perPage);

        $reviews->getCollection()->transform(
            fn ($review) => $this->formatReview($review)
        );

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
     */
    private function findAstrologer(array $validated): ?AiAstrologer
    {
        $query = AiAstrologer::query();

        if (!empty($validated['astrologer_id'])) {
            $query->where(
                'id',
                $validated['astrologer_id']
            );
        }

        if (!empty($validated['astrologer_slug'])) {
            $query->where(
                'slug',
                $validated['astrologer_slug']
            );
        }

        return $query->first();
    }

    /**
     * Check whether both ID and slug were supplied.
     */
    private function hasBothAstrologerIdentifiers(array $validated): bool
    {
        return !empty($validated['astrologer_id'])
            && !empty($validated['astrologer_slug']);
    }

    /**
     * Format review response consistently.
     */
    private function formatReview(AstrologerReview $review): array
    {
        return [
            'id' => $review->id,

            'user' => [
                'id' => $review->user?->id,
                'name' => $review->user?->name,
                'profile_image' => $this->getProfileImageUrl(
                    $review->user?->profile_image
                ),
            ],

            'astrologer' => [
                'id' => $review->astrologer?->id,
                'name' => $review->astrologer?->name,
                'slug' => $review->astrologer?->slug,

                'expertises' => $review->astrologer?->expertises
                    ? $review->astrologer->expertises
                        ->map(function ($expertise) {
                            return [
                                'id' => $expertise->id,
                                'name' => $expertise->name,
                                'slug' => $expertise->slug,
                            ];
                        })
                        ->values()
                    : [],
            ],

            'rating' => (int) $review->rating,
            'review' => $review->review,
            'is_active' => (bool) $review->is_active,
            'created_at' => $review->created_at,
            'updated_at' => $review->updated_at,
        ];
    }

    /**
     * Get profile image URL.
     */
    private function getProfileImageUrl(?string $image): string
    {
        if (!$image) {
            return asset('default-user.png');
        }

        return asset('storage/user/' . $image);
    }

    /**
     * Get rating statistics.
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

            'total_reviews' => (int) (
                $stats->total_reviews ?? 0
            ),
        ];
    }
}