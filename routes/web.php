<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::namespace('App\Http\Controllers\Auth')
    ->name('auth.')
    ->group(function () {

        // Unsecured Routes
        Route::get('/', 'AuthController@getLogin');
        Route::get('/login', 'AuthController@getLogin')->name('login.index');
        Route::post('/login', 'AuthController@postLogin')->name('login');

        Route::get('/forgot/password', 'AuthController@getForgotPassword')->name('forgot.password.index');
        Route::post('/forgot/password', 'AuthController@postForgotPassword')->name('forgot.password');

        Route::get('/password/reset/request/{token}', 'AuthController@getResetPassword')->name('password.reset.request');
        Route::post('/password/reset', 'AuthController@postResetPassword')->name('password.reset');

        // 2 Factor Auth Routes
        Route::prefix('two-factor')
            ->name('two.factor.')
            ->group(function () {
                Route::get('/auth/{token}', 'AuthController@getTwoFactorAuthIndex')->name('index');
                Route::post('/auth/verify', 'AuthController@postVerifyTwoFactorAuth')->name('verify');
            });

        // Secured Routes
        Route::middleware('auth')
            ->group(function () {
                Route::get('/logout', 'AuthController@getLogout')->name('logout');
            });
    });

Route::namespace('App\Http\Controllers\Admin')
    ->name('admin.')
    ->group(function () {

        // Secured Routes
        Route::middleware(['auth', 'employee'])
            ->group(function () {
                // Route::prefix('dashboard')
                //     ->name('dashboard.')
                //     ->group(function () {
                //         Route::get('/', 'DashboardController@getIndex')->name('index');
                //         Route::get('/navigation', 'DashboardController@getNav')->name('navigation');
                //         Route::get('/stats', 'DashboardController@getStats')->name('stats');
                //         Route::get('/orders/graph', 'DashboardController@getOrdersGraph')->name('orders.graph');
                //         Route::get('/graph/growth', 'DashboardController@getGrowthGraph')->name('graph.growth');
                //         Route::get('/graph/engagement', 'DashboardController@getEngagementGraph')->name('graph.engagement');
                //         Route::get('/login/requested', 'DashboardController@getLoginRequested')->name('login.requested');
                //         Route::get('/login/request/status', 'DashboardController@getLoginRequestStatus')->name('login.request.status');
                //     });

                Route::prefix('dashboard')
                    ->name('dashboard.')
                    ->group(function () {

                        Route::get('/', 'DashboardController@getIndex')
                            ->name('index');

                        Route::get('/navigation', 'DashboardController@getNav')
                            ->name('navigation');

                        Route::get('/stats', 'DashboardController@getStats')
                            ->name('stats');

                        Route::get('/orders/graph', 'DashboardController@getOrdersGraph')
                            ->name('orders.graph');

                        Route::get('/graph/growth', 'DashboardController@getGrowthGraph')
                            ->name('graph.growth');

                        Route::get('/graph/engagement', 'DashboardController@getEngagementGraph')
                            ->name('graph.engagement');


                        /*
                        |--------------------------------------------------------------------------
                        | AI CHAT DASHBOARD GRAPHS
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/graph/expertise/users',
                            'DashboardController@getExpertiseUsersGraph'
                        )->name('graph.expertise.users');


                        Route::get(
                            '/graph/astrologer/users',
                            'DashboardController@getAstrologerUsersGraph'
                        )->name('graph.astrologer.users');


                        Route::get(
                            '/graph/astrologer/spend',
                            'DashboardController@getAstrologerSpendGraph'
                        )->name('graph.astrologer.spend');


                        Route::get(
                            '/graph/astrologer/reviews',
                            'DashboardController@getAstrologerReviewsGraph'
                        )->name('graph.astrologer.reviews');


                        /*
                        |--------------------------------------------------------------------------
                        | LOGIN REQUEST
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            '/login/requested',
                            'DashboardController@getLoginRequested'
                        )->name('login.requested');


                        Route::get(
                            '/login/request/status',
                            'DashboardController@getLoginRequestStatus'
                        )->name('login.request.status');

                    });

                Route::prefix('profile')
                    ->name('profile.')
                    ->group(function () {
                        Route::get('/', 'ProfileController@getIndex')->name('details');
                        Route::post('/', 'ProfileController@postUpdate')->name('update');
                        Route::post('/change_password', 'ProfileController@postChangePassword')->name('change.password');
                        Route::get('/logout', 'ProfileController@getLogout')->name('logout');
                    });

                Route::prefix('permissions')
                    ->name('permissions.')
                    ->group(function () {

                        Route::get('/', 'PermissionController@getIndex')->name('index');
                        Route::get('list', 'PermissionController@getList')->name('list');
                        
                        Route::get('update/{id}', 'PermissionController@getUpdate')->name('update.index');
                        Route::post('update/{id}', 'PermissionController@postUpdate')->name('update');
                    });

                Route::prefix('zodiac_signs')
                    ->name('zodiac_signs.')
                    ->group(function () {

                        Route::get('/', 'ZodiacController@getIndex')->name('index');
                        Route::get('list', 'ZodiacController@getList')->name('list');

                        Route::get('create', 'ZodiacController@getCreate')->name('create.index');
                        Route::post('create', 'ZodiacController@postCreate')->name('create');

                        Route::get('update/{id?}', 'ZodiacController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'ZodiacController@postUpdate')->name('update');

                        Route::get('delete/{id?}', 'ZodiacController@getDelete')->name('delete');

                        Route::get('change/status/{id?}', 'ZodiacController@getChangeStatus')->name('change.status');
                    });

                Route::prefix('horoscopes')
                    ->name('horoscopes.')
                    ->group(function () {

                        Route::get('/', 'HoroscopeController@getIndex')->name('index');
                        Route::get('list', 'HoroscopeController@getList')->name('list');

                        Route::get('create', 'HoroscopeController@getCreate')->name('create.index');
                        Route::post('create', 'HoroscopeController@postCreate')->name('create');

                        Route::get('update/{id?}', 'HoroscopeController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'HoroscopeController@postUpdate')->name('update');

                        Route::get('delete/{id?}', 'HoroscopeController@getDelete')->name('delete');

                        Route::get('change/status/{id?}', 'HoroscopeController@getChangeStatus')->name('change.status');
                    });

                Route::prefix('countries')
                    ->name('countries.')
                    ->group(function () {
                        Route::get('/', 'CountryController@getIndex')->name('index');
                        Route::get('list', 'CountryController@getList')->name('list');
                        Route::get('create', 'CountryController@getCreate')->name('create.index');
                        Route::post('create', 'CountryController@postCreate')->name('create');
                        Route::get('update/{id?}', 'CountryController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'CountryController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'CountryController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'CountryController@getChangeStatus')->name('change.status');
                    });

                Route::prefix('states')
                    ->name('states.')
                    ->group(function () {
                        Route::get('/', 'StateController@getIndex')->name('index');
                        Route::get('list', 'StateController@getList')->name('list');
                        Route::get('list/country_wise/{country_id?}', 'StateController@getCountryWiseList')->name('country_wise.list');
                        Route::get('create', 'StateController@getCreate')->name('create.index');
                        Route::post('create', 'StateController@postCreate')->name('create');
                        Route::get('update/{id?}', 'StateController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'StateController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'StateController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'StateController@getChangeStatus')->name('change.status');

                        Route::prefix('import')
                            ->name('import.xlsx.')
                            ->group(function () {
                                Route::get('/', 'StateController@getXlsxImport')->name('index');
                                Route::get('download/sample', 'StateController@getXlsxImportSampleDownload')->name('download.sample');
                                Route::post('/', 'StateController@postXlsxImport')->name('data');
                            });

                        Route::prefix('export')
                            ->name('export.xlsx.')
                            ->group(function () {
                                Route::get('/', 'StateController@exportXlsx')->name('data');
                            });
                    });

                Route::prefix('cities')
                    ->name('cities.')
                    ->group(function () {
                        Route::get('/', 'CityController@getIndex')->name('index');
                        Route::get('list', 'CityController@getList')->name('list');
                        Route::get('list/state_wise/{state_id?}', 'CityController@getStateWiseList')->name('state_wise.list');
                        Route::get('create', 'CityController@getCreate')->name('create.index');
                        Route::post('create', 'CityController@postCreate')->name('create');
                        Route::get('update/{id?}', 'CityController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'CityController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'CityController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'CityController@getChangeStatus')->name('change.status');

                        Route::prefix('import')
                            ->name('import.xlsx.')
                            ->group(function () {
                                Route::get('/', 'CityController@getXlsxImport')->name('index');
                                Route::get('download/sample', 'CityController@getXlsxImportSampleDownload')->name('download.sample');
                                Route::post('/', 'CityController@postXlsxImport')->name('data');
                            });

                        Route::prefix('export')
                            ->name('export.xlsx.')
                            ->group(function () {
                                Route::get('/', 'CityController@exportXlsx')->name('data');
                            });
                    });

                Route::prefix('pin_codes')
                    ->name('pin_codes.')
                    ->group(function () {
                        Route::get('/', 'PinCodeController@getIndex')->name('index');
                        Route::get('list', 'PinCodeController@getList')->name('list');
                        Route::get('search/pin_code', 'PinCodeController@getLocationViaPinCode')->name('search.pin_code');
                        Route::get('create', 'PinCodeController@getCreate')->name('create.index');
                        Route::post('create', 'PinCodeController@postCreate')->name('create');
                        Route::get('update/{id?}', 'PinCodeController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'PinCodeController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'PinCodeController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'PinCodeController@getChangeStatus')->name('change.status');

                        Route::prefix('import')
                            ->name('import.xlsx.')
                            ->group(function () {
                                Route::get('/', 'PinCodeController@getXlsxImport')->name('index');
                                Route::get('download/sample', 'PinCodeController@getXlsxImportSampleDownload')->name('download.sample');
                                Route::post('/', 'PinCodeController@postXlsxImport')->name('data');
                            });

                        Route::prefix('export')
                            ->name('export.xlsx.')
                            ->group(function () {
                                Route::get('/', 'PinCodeController@exportXlsx')->name('data');
                            });
                    });

                Route::prefix('hierarchies')
                    ->name('hierarchies.')
                    ->group(function () {
                        Route::get('/', 'HierarchyController@getIndex')->name('index');
                        Route::get('list', 'HierarchyController@getList')->name('list');
                    });

                Route::prefix('roles')
                    ->name('roles.')
                    ->group(function () {
                        Route::get('/', 'RoleController@getIndex')->name('index');
                        Route::get('list', 'RoleController@getList')->name('list');
                        Route::get('create', 'RoleController@getCreate')->name('create.index');
                        Route::post('create', 'RoleController@postCreate')->name('create');
                        Route::get('update/{id?}', 'RoleController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'RoleController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'RoleController@getDelete')->name('delete');
                    });

                Route::prefix('login_requests')
                    ->name('login_requests.')
                    ->group(function () {
                        Route::get('/', 'LoginRequestController@getIndex')->name('index');
                        Route::get('/list', 'LoginRequestController@getList')->name('list');
                        Route::post('/status/update', 'LoginRequestController@postStatusUpdate')->name('status.update');

                        Route::prefix('reports')
                            ->name('reports.')
                            ->group(function () {
                                Route::get('/', 'LoginRequestController@getReportIndex')->name('index');
                                Route::post('list', 'LoginRequestController@getReportList')->name('list');
                            });

                        Route::prefix('export')
                            ->name('export.xlsx.')
                            ->group(function () {
                                Route::get('/', 'LoginRequestController@exportXlsx')->name('data');
                            });
                    });

                Route::prefix('orders')
                    ->name('orders.')
                    ->group(function () {
                        Route::get('/', 'OrderController@getIndex')->name('index');
                        Route::post('list', 'OrderController@getList')->name('list');
                        Route::get('create', 'OrderController@getCreate')->name('create.index');
                        Route::post('create', 'OrderController@postCreate')->name('create');
                        Route::post('update/{id?}', 'OrderController@postUpdate')->name('update');
                        Route::get('view/{id?}', 'OrderController@getView')->name('view');
                        Route::get('download/invoice/{id?}', 'OrderController@getDownloadInvoice')->name('download.invoice');
                        Route::get('tracking/history/{id}', 'OrderController@getTrackingHistory')->name('tracking.history');
                        Route::get('tracking/history/list/{id}', 'OrderController@getTrackingHistoryList')->name('tracking.history.list');

                        Route::prefix('report')
                            ->name('report.')
                            ->group(function () {
                                Route::get('/', 'OrderController@getReportIndex')->name('index');
                                Route::post('list', 'OrderController@getReportList')->name('list');
                                Route::get('stats', 'OrderController@getReportStats')->name('stats');
                            });
                    });

                Route::prefix('settings')
                    ->name('settings.')
                    ->group(function () {
                        Route::get('/', 'SettingController@getIndex')->name('index');
                        Route::post('update', 'SettingController@postUpdate')->name('update');
                    });

                Route::prefix('astrologers')
                    ->name('astrologers.')
                    ->group(function () {
                        Route::get('/', 'AstroController@getIndex')->name('index');
                        Route::get('list', 'AstroController@getList')->name('list');
                        Route::get('get-states/{country_id}', 'AstroController@getStatesByCountry')->name('get.states');
                        Route::get('get-cities/{state_id}', 'AstroController@getCitiesByState')->name('get.cities');
                        Route::get('get-pincodes/{city_id}', 'AstroController@getPinCodesByCity')->name('get.pincodes');
                        Route::get('create', 'AstroController@getCreate')->name('create.index');
                        Route::post('create', 'AstroController@postCreate')->name('create');
                        Route::get('update/{id?}', 'AstroController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'AstroController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'AstroController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'AstroController@getChangeStatus')->name('change.status');
                        Route::get('view/{id?}', 'AstroController@getView')->name('view');
                        Route::post('earnings/filter', 'AstroController@filterEarnings')->name('astro.earnings.filter');
                    });

                Route::prefix('users')
                    ->name('users.')
                    ->group(function () {
                        Route::get('/', 'UserController@getIndex')->name('index');
                        Route::get('list', 'UserController@getList')->name('list');
                        Route::get('get-states/{country_id}', 'UserController@getStatesByCountry')->name('get.states');
                        Route::get('get-cities/{state_id}', 'UserController@getCitiesByState')->name('get.cities');
                        Route::get('get-pincodes/{city_id}', 'UserController@getPinCodesByCity')->name('get.pincodes');
                        Route::get('create', 'UserController@getCreate')->name('create.index');
                        Route::post('create', 'UserController@postCreate')->name('create');
                        Route::get('update/{id?}', 'UserController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'UserController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'UserController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'UserController@getChangeStatus')->name('change.status');
                        Route::get('view/{id?}', 'UserController@getView')->name('view');
                    });

                Route::prefix('interactions')
                    ->name('interactions.')
                    ->group(function () {
                        Route::get('/', 'InteractionController@getIndex')->name('index');
                        Route::get('list', 'InteractionController@getList')->name('list');
                        Route::get('view/{id?}', 'InteractionController@getView')->name('view');
                    });

                Route::prefix('payouts')
                    ->name('payouts.')
                    ->group(function () {
                        Route::get('/', 'PayoutController@getIndex')->name('index');
                        Route::get('/list', 'PayoutController@getList')->name('list');
                        Route::get('update/{id?}', 'PayoutController@getUpdate')->name('update.index');
                        Route::post('/approve/{id}', 'PayoutController@approve')->name('approve');
                        Route::post('/reject/{id}', 'PayoutController@reject')->name('reject');
                        Route::get('view/{id?}', 'PayoutController@getView')->name('view');
                        Route::get('delete/{id?}', 'PayoutController@getDelete')->name('delete');
                    });

                Route::prefix('blog_categories')
                    ->name('blog_categories.')
                    ->group(function () {
                        Route::get('/', 'BlogCategoryController@getIndex')->name('index');
                        Route::get('list', 'BlogCategoryController@getList')->name('list');
                        Route::get('create', 'BlogCategoryController@getCreate')->name('create.index');
                        Route::post('create', 'BlogCategoryController@postCreate')->name('create');
                        Route::get('update/{id?}', 'BlogCategoryController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'BlogCategoryController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'BlogCategoryController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'BlogCategoryController@getChangeStatus')->name('change.status');
                        Route::get('view/{id?}', 'BlogCategoryController@getView')->name('view');
                    });

                Route::prefix('blogs')
                    ->name('blogs.')
                    ->group(function () {
                        Route::get('/', 'BlogController@getIndex')->name('index');
                        Route::get('list', 'BlogController@getList')->name('list');
                        Route::get('create', 'BlogController@getCreate')->name('create.index');
                        Route::post('create', 'BlogController@postCreate')->name('create');
                        Route::get('update/{id?}', 'BlogController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'BlogController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'BlogController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'BlogController@getChangeStatus')->name('change.status');
                        Route::get('view/{id?}', 'BlogController@getView')->name('view');
                    });

                /*  
                |--------------------------------------------------------------------------
                | Astro Store routes
                |--------------------------------------------------------------------------
                */

                Route::prefix('coupons')
                    ->name('coupons.')
                    ->group(function () {
                        Route::get('/', 'CouponController@getIndex')->name('index');
                        Route::get('list', 'CouponController@getList')->name('list');
                        Route::get('create', 'CouponController@getCreate')->name('create.index');
                        Route::post('create', 'CouponController@postCreate')->name('create');
                        Route::get('update/{id?}', 'CouponController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'CouponController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'CouponController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'CouponController@getChangeStatus')->name('change.status');
                    });

                Route::prefix('product_categories')
                    ->name('product_categories.')
                    ->group(function () {
                        Route::get('/', 'CategoryController@getIndex')->name('index');
                        Route::get('list', 'CategoryController@getList')->name('list');
                        Route::get('create', 'CategoryController@getCreate')->name('create.index');
                        Route::post('create', 'CategoryController@postCreate')->name('create');
                        Route::get('update/{id?}', 'CategoryController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'CategoryController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'CategoryController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'CategoryController@getChangeStatus')->name('change.status');
                        Route::get('/get-category-code', 'CategoryController@getCategoryCode')->name('getCategoryCode');
                    });

                Route::prefix('products')
                    ->name('products.')
                    ->group(function () {
                        Route::get('/', 'ProductController@getIndex')->name('index');
                        Route::get('list', 'ProductController@getList')->name('list');
                        Route::get('create', 'ProductController@getCreate')->name('create.index');
                        Route::post('create', 'ProductController@postCreate')->name('create');
                        Route::get('update/{id?}', 'ProductController@getUpdate')->name('update.index');
                        Route::delete('remove-image/{id?}', 'ProductController@removeImage')->name('remove-image');
                        Route::post('update/{id?}', 'ProductController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'ProductController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'ProductController@getChangeStatus')->name('change.status');
                        Route::get('/get-product-code', 'ProductController@getProductCode')->name('getProductCode');
                    });

                Route::prefix('orders')
                    ->name('orders.')
                    ->group(function () {
                        Route::get('/', 'OrderController@getIndex')->name('index');
                        Route::get('list', 'OrderController@getList')->name('list');
                        Route::get('view/{id?}', 'OrderController@getView')->name('view');
                    });

                Route::prefix('returns')
                    ->name('returns.')
                    ->group(function () {
                        Route::get('/', 'ReturnController@getIndex')->name('index');
                        Route::get('list', 'ReturnController@getList')->name('list');
                        Route::get('view/{id?}', 'ReturnController@getView')->name('view');
                    });

                Route::prefix('product_stocks')
                    ->name('product_stocks.')
                    ->group(function () {
                        Route::get('/', 'ProductStockController@getIndex')->name('index');
                        Route::get('list', 'ProductStockController@getList')->name('list');
                        Route::get('view/{id?}', 'ProductStockController@getView')->name('view');
                        Route::post('update/{id}', 'ProductStockController@updateStock')->name('update');
                    });

                Route::prefix('astro_banners')
                    ->name('astro_banners.')
                    ->group(function () {
                        Route::get('/', 'AstroBannerController@getIndex')->name('index');
                        Route::get('list', 'AstroBannerController@getList')->name('list');
                        Route::get('create', 'AstroBannerController@getCreate')->name('create.index');
                        Route::post('create', 'AstroBannerController@postCreate')->name('create');
                        Route::get('update/{id?}', 'AstroBannerController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'AstroBannerController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'AstroBannerController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'AstroBannerController@getChangeStatus')->name('change.status');
                    });

                Route::prefix('store_banners')
                    ->name('store_banners.')
                    ->group(function () {
                        Route::get('/', 'StoreBannerController@getIndex')->name('index');
                        Route::get('list', 'StoreBannerController@getList')->name('list');
                        Route::get('create', 'StoreBannerController@getCreate')->name('create.index');
                        Route::post('create', 'StoreBannerController@postCreate')->name('create');
                        Route::get('update/{id?}', 'StoreBannerController@getUpdate')->name('update.index');
                        Route::post('update/{id?}', 'StoreBannerController@postUpdate')->name('update');
                        Route::get('delete/{id?}', 'StoreBannerController@getDelete')->name('delete');
                        Route::get('change/status/{id?}', 'StoreBannerController@getChangeStatus')->name('change.status');
                    });

                Route::prefix('send_mail')
                    ->name('send_mail.')
                    ->group(function () {
                        Route::get('/', 'MailController@getIndex')->name('index');
                        Route::post('/send', 'MailController@sendMail')->name('send');
                    });

                Route::prefix('bulk_mails')
                    ->name('bulk_mails.')
                    ->group(function () {
                        Route::get('/', 'BulkMailController@getIndex')->name('index');
                        Route::get('list', 'BulkMailController@getList')->name('list');
                        Route::get('create', 'BulkMailController@getCreate')->name('create.index');
                        Route::post('create', 'BulkMailController@postCreate')->name('create');
                    });
            });
    });