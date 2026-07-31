<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\AstrologerApiController;
use App\Http\Controllers\Api\ReviewApiController;
use App\Http\Controllers\Api\HoroscopeApiController;
use App\Http\Controllers\Api\BlogCategoryApiController;
use App\Http\Controllers\Api\BlogApiController;
use App\Http\Controllers\Api\PayoutApiController;
use App\Http\Controllers\Api\RazorpayPaymentController;
use App\Http\Controllers\Api\StoreRazorpayPaymentController;
use App\Http\Controllers\Api\WalletApiController;
use App\Http\Controllers\Api\StoreWalletApiController;
use App\Http\Controllers\Api\StoreWalletTopupController;
use App\Http\Controllers\Api\UserPaymentAccountApiController;
use App\Http\Controllers\Api\PayoutRequestApiController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\CallApiController;
use App\Http\Controllers\Api\EasyGoApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\CartApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\PurchaseApiController;
use App\Http\Controllers\Api\AlternativeAddressApiController;
use App\Http\Controllers\Api\CouponApiController;
use App\Http\Controllers\Api\StoreReviewApiController;
use App\Http\Controllers\Api\ReturnApiController;
use App\Http\Controllers\Api\BannerApiController;
use App\Http\Controllers\Api\HoroscopeGenerateController;
use App\Http\Controllers\Api\AiChatApiController;
use App\Http\Controllers\Api\AiChatSessionApiController;
use App\Http\Controllers\Api\AiChatTransactionApiController;
use App\Http\Controllers\Api\AiAstrologyController;
use App\Http\Controllers\Api\AiAstrologerExpertiseQuestionController;

/*
|--------------------------------------------------------------------------
| Public APIs (No Auth Required)
|--------------------------------------------------------------------------
*/

Route::post('/call/webhook', [EasyGoApiController::class, 'callWebhook']);

Route::get('/ai-astrologers', [AiAstrologyController::class, 'index']);
Route::get('/ai-astrologers/{slug}', [AiAstrologyController::class, 'show']);
Route::get('/ai-astrologer-expertise/{slug}/questions', [AiAstrologerExpertiseQuestionController::class, 'index']);

// USER AUTH
Route::prefix('user')->group(function () {
    Route::post('register', [UserApiController::class, 'register']);
    Route::post('login', [UserApiController::class, 'login']);
    Route::post('verify-login-otp', [UserApiController::class, 'verifyLoginOtp']);
    Route::post('resend-otp', [UserApiController::class, 'resendOtp']);
    Route::post('forgot-password', [UserApiController::class, 'forgotPassword']);
    Route::post('verify-otp', [UserApiController::class, 'verifyOtp']);
    Route::post('reset-password', [UserApiController::class, 'resetPassword']);
});

// ASTROLOGER AUTH
Route::prefix('astro')->group(function () {
    Route::post('register', [AstrologerApiController::class, 'register']);
    Route::post('login', [AstrologerApiController::class, 'login']);
    // Route::get('/{id}', [AstrologerApiController::class, 'show']);
    Route::get('/{id}', [AstrologerApiController::class, 'show'])->whereNumber('id');
    Route::post('forgot-password', [AstrologerApiController::class, 'forgotPassword']);
    Route::post('verify-otp', [AstrologerApiController::class, 'verifyOtp']);
    Route::post('reset-password', [AstrologerApiController::class, 'resetPassword']);
});


Route::get('astro', [AstrologerApiController::class, 'list']);

// Horoscopes
Route::get('horoscopes', [HoroscopeApiController::class, 'index']);
Route::get('horoscopes/{id}', [HoroscopeApiController::class, 'show']);

// Blog Categories
Route::get('blog_categories', [BlogCategoryApiController::class, 'index']);
Route::get('blog_categories/{id}', [BlogCategoryApiController::class, 'show']);

// Blogs
Route::get('blogs', [BlogApiController::class, 'index']);
Route::get('blogs/{id}', [BlogApiController::class, 'show']);

// BANNERS
Route::get('banners', [BannerApiController::class, 'index']);


/*
|--------------------------------------------------------------------------
| Astro Store APIs
|--------------------------------------------------------------------------
*/

// CATEGORIES
Route::get('/categories', [CategoryApiController::class, 'index']);
Route::get('/categories/{slug}', [CategoryApiController::class, 'show']);

// PRODUCTS
Route::get('/products', [ProductApiController::class, 'index']);

// COUPONS
// Route::post('coupon/apply', [CouponApiController::class, 'apply']);
Route::get('coupons', [CouponApiController::class, 'index']);

// ORDERS Delivery
Route::post('order/{id}/delivered', [OrderApiController::class, 'markDelivered']);

// STORE REVIEWS
Route::get('products/{id}/reviews', [StoreReviewApiController::class, 'productReviews']);

// ALL REVIEWS
Route::get('reviews', [ReviewApiController::class, 'index']);

// RETURNS
Route::post('returns/{id}/picked', [ReturnApiController::class, 'picked']);

// Route::post('/horoscope', [HoroscopeGenerateController::class, 'generate']);
Route::prefix('astrology')->group(function () {

    Route::post('/full-report', [HoroscopeGenerateController::class, 'fullReport']);

    Route::post('/daily', [HoroscopeGenerateController::class, 'daily']);
    Route::post('/weekly', [HoroscopeGenerateController::class, 'weekly']);
    Route::post('/monthly', [HoroscopeGenerateController::class, 'monthly']);

    Route::post('/birth-chart', [HoroscopeGenerateController::class, 'birthChart']);
    Route::post('/planet-positions', [HoroscopeGenerateController::class, 'planetPositions']);
    Route::post('/dasha', [HoroscopeGenerateController::class, 'dasha']);
});

/*
|--------------------------------------------------------------------------
| Protected APIs (Auth Required for BOTH USER & ASTRO)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Razorpay
    Route::prefix('razorpay')->group(function () {
        Route::post('/create-order', [RazorpayPaymentController::class, 'createOrder']);
        Route::post('/verify', [RazorpayPaymentController::class, 'verify']);
    });

    // store
    Route::prefix('store')->group(function () {
        Route::post('/create-order', [StoreRazorpayPaymentController::class, 'createOrder']);
        Route::post('/verify-payment', [StoreRazorpayPaymentController::class, 'verify']);
        Route::post('/order/cancel/{id}', [StoreRazorpayPaymentController::class, 'cancelOrder']);
        Route::get('/order/{id}', [StoreRazorpayPaymentController::class, 'orderDetails']);
    });

    Route::post('/wallet/topup/create-order', [StoreWalletTopupController::class, 'createTopupOrder']);
    Route::post('/wallet/topup/verify', [StoreWalletTopupController::class, 'verifyTopup']);

    /*
    |--------------------------------------------------------------------------
    | USER PROTECTED API
    |--------------------------------------------------------------------------
    */
    Route::prefix('user')
        ->middleware(['auth:sanctum', 'user'])
        ->group(function () {

            Route::get('profile', [UserApiController::class, 'profile']);
            Route::post('update', [UserApiController::class, 'update']);
            Route::post('change-password', [UserApiController::class, 'changePassword']);
            Route::post('logout', [UserApiController::class, 'logout']);
            Route::delete('delete', [UserApiController::class, 'delete']);

            Route::prefix('ai-chat')->group(function () {
                // Route::get('/topics', [AiChatApiController::class, 'topics']);
                Route::post('/start-session', [AiChatApiController::class, 'startSession']);
                Route::post('/send-message', [AiChatApiController::class, 'sendMessage']);
                Route::get('/sessions', [AiChatApiController::class, 'sessions']);
                Route::get('/history/{sessionId}', [AiChatApiController::class, 'history']);
                Route::post('/close-session/{sessionId}', [AiChatApiController::class, 'closeSession']);
            });
            
            // AI Chat Session
            Route::get('/chat-sessions', [AiChatSessionApiController::class, 'index']);
            Route::get('/chat-sessions/{id}', [AiChatSessionApiController::class, 'show']);

            // AI Chat Transaction
            Route::get('/chat-transactions', [AiChatTransactionApiController::class, 'index']);


            /*
            |--------------------------------------------------------------------------
            | Wishlist APIs
            |--------------------------------------------------------------------------
            */
            Route::get('/wishlist', [ProductApiController::class, 'wishlist']);
            Route::post('/wishlist/add', [ProductApiController::class, 'addToWishlist']);
            Route::post('/wishlist/remove', [ProductApiController::class, 'removeFromWishlist']);

            /*
            |--------------------------------------------------------------------------
            | CART APIs
            |--------------------------------------------------------------------------
            */
            Route::get('cart', [CartApiController::class, 'view']);
            Route::post('cart/add', [CartApiController::class, 'add']);
            Route::post('cart/update', [CartApiController::class, 'updateQty']);
            Route::delete('cart/remove/{id}', [CartApiController::class, 'remove']);

            /*
            |--------------------------------------------------------------------------
            | ORDER APIs
            |--------------------------------------------------------------------------
            */
            Route::post('order/place', [OrderApiController::class, 'place']);
            Route::get('orders', [OrderApiController::class, 'index']);
            Route::get('orders/{id}', [OrderApiController::class, 'show']);

            /*
            |--------------------------------------------------------------------------
            | ALTERNATIVE ADDRESS APIs
            |--------------------------------------------------------------------------
            */
            Route::get('addresses', [AlternativeAddressApiController::class, 'index']);
            Route::post('addresses', [AlternativeAddressApiController::class, 'store']);
            Route::get('addresses/{id}', [AlternativeAddressApiController::class, 'show']);
            Route::put('addresses/{id}', [AlternativeAddressApiController::class, 'update']);
            Route::delete('addresses/{id}', [AlternativeAddressApiController::class, 'destroy']);

            /*
            |--------------------------------------------------------------------------
            | REVIEW APIs
            |--------------------------------------------------------------------------
            */
            Route::post('review', [StoreReviewApiController::class, 'store']);

            /*
            |--------------------------------------------------------------------------
            | RETURN APIs
            |--------------------------------------------------------------------------
            */
            Route::post('return/request', [ReturnApiController::class, 'request']);
            Route::get('returns', [ReturnApiController::class, 'myReturns']);

        });

    /*
    |--------------------------------------------------------------------------
    | ASTROLOGER PROTECTED API
    |--------------------------------------------------------------------------
    */
    Route::prefix('astro')
        ->middleware(['auth:sanctum', 'astro'])
        ->group(function () {

            Route::get('profile', [AstrologerApiController::class, 'profile']);
            Route::post('update', [AstrologerApiController::class, 'update']);
            Route::post('change-password', [AstrologerApiController::class, 'changePassword']);
            Route::post('logout', [AstrologerApiController::class, 'logout']);
            Route::delete('delete', [AstrologerApiController::class, 'delete']);

        });


    /*
    |--------------------------------------------------------------------------
    | WALLET API (FOR BOTH USER & ASTRO)
    |--------------------------------------------------------------------------
    */
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletApiController::class, 'show']);
        Route::post('/recharge', [WalletApiController::class, 'recharge']);
        Route::get('/recharge-history', [WalletApiController::class, 'rechargeHistory']);
         Route::get('/chat-analysis', [WalletApiController::class, 'chatAnalysis']);
    });
    
    Route::prefix('payment_accounts')->group(function () {
        Route::get('/', [UserPaymentAccountApiController::class, 'index']);
        Route::post('/', [UserPaymentAccountApiController::class, 'store']);
        Route::put('/', [UserPaymentAccountApiController::class, 'update']);
        Route::delete('/', [UserPaymentAccountApiController::class, 'destroy']);
    });

    Route::prefix('user_payment_account')->group(function () {
        Route::get('/', [UserPaymentAccountApiController::class, 'index']);
        Route::post('/', [UserPaymentAccountApiController::class, 'store']);
        Route::put('/{id}', [UserPaymentAccountApiController::class, 'update']);
        Route::delete('/{id}', [UserPaymentAccountApiController::class, 'destroy']);
    });

    Route::prefix('payout')->group(function () {
        Route::post('/request', [PayoutApiController::class, 'store']);
        Route::get('/history', [PayoutApiController::class, 'index']);
    });

    // Call
    Route::prefix('call')->group(function () {
        Route::post('/start', [CallApiController::class, 'start']);
        Route::post('/pulse', [CallApiController::class, 'pulse']);
        Route::post('/end', [CallApiController::class, 'end']);
        Route::get('/', [CallApiController::class, 'index']);
    });
    
    Route::post('/call/initiate', [EasyGoApiController::class, 'initiateCall']);
    

    // Chat
    Route::prefix('chat')->group(function () {
        Route::post('/start', [ChatApiController::class, 'start']);
        Route::post('/pulse', [ChatApiController::class, 'pulse']);
        Route::post('/end', [ChatApiController::class, 'end']);
        Route::get('/', [ChatApiController::class, 'index']);
    });

    Route::prefix('store-wallet')->group(function () {
        Route::get('/', [StoreWalletApiController::class, 'show']);        
        Route::get('/history', [StoreWalletApiController::class, 'history']);
        Route::get('/summary', [StoreWalletApiController::class, 'summary']); 
        Route::get('/spend-history', [StoreWalletApiController::class, 'spendHistory']);
    });

});