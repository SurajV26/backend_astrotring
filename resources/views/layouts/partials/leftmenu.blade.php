@php
    $user = auth()->user();
@endphp

<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">

                {{-- ================= ADMIN ================= --}}
                @if ($user && $user->isSuperAdmin())

                    <li>
                        <a href="{{ route('admin.dashboard.index') }}">
                            <i class="bx bx-home-circle"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('admin.interactions.index') }}">
                            <i class="bx bx-transfer"></i>
                            <span>Interactions</span>
                        </a>
                    </li>

                    <li class="menu-title">PERMISSIONS</li>
                    <li>
                        <a href="{{ route('admin.permissions.index') }}">
                            <i class="bx bx-lock-alt"></i>
                            <span>Permission</span>
                        </a>
                    </li>

                    {{-- <li class="menu-title">HOROSCOPE</li>

                <li>
                    <a href="{{ route('admin.zodiac_signs.index') }}">
                        <i class="bx bx-wind"></i>
                        <span>Zodiac Signs</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('admin.horoscopes.index') }}">
                        <i class="bx bx-star"></i>
                        <span>Horoscope</span>
                    </a>
                </li> --}}

                    <li class="menu-title">USERS</li>
                    <li>
                        <a href="{{ route('admin.astrologers.index') }}" class="waves-effect">
                            <i class="bx bx-user-circle"></i>
                            <span key="t-chat">Astrologers</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.users.index') }}">
                            <i class="bx bx-user"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.payouts.index') }}">
                            <i class="bx bx-wallet"></i>
                            <span>Payout Requests</span>
                        </a>
                    </li>

                    <li class="menu-title">BLOG</li>

                    <li>
                        <a href="{{ route('admin.blog_categories.index') }}">
                            <i class="bx bx-layer"></i>
                            <span>Blog Category</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('admin.blogs.index') }}">
                            <i class="bx bx-list-ul"></i>
                            <span>Blog</span>
                        </a>
                    </li>

                    <li class="menu-title">BANNERS</li>

                    <li>
                        <a href="{{ route('admin.astro_banners.index') }}">
                            <i class="bx bx-image"></i>
                            <span>Astro Banner</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('admin.bulk_mails.index') }}">
                            <i class="bx bx-envelope"></i>
                            <span>Send Mail</span>
                        </a>
                    </li>

                    <li class="menu-title">POLICIES</li>

                    <li>
                        <a href="{{ route('admin.policy_categories.index') }}">
                            <i class="bx bx-layer"></i>
                            <span>Policy Category</span>
                        </a>
                    </li>

                    <li>
                        <a href="{{ route('admin.policies.index') }}">
                            <i class="bx bx-list-ul"></i>
                            <span>Policy</span>
                        </a>
                    </li>

                    {{-- ================= EMPLOYEE ================= --}}
                @elseif($user)
                    @if ($user->hasAccess('dashboard'))
                        <li>
                            <a href="{{ route('admin.dashboard.index') }}">
                                <i class="bx bx-home-circle"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                    @endif

                    @if ($user->hasAccess('earned'))
                        <li>
                            <a href="{{ route('admin.dashboard.index') }}">
                                <i class="bx bx-rupee"></i>
                                <span>Earned</span>
                            </a>
                        </li>
                    @endif

                    @if ($user->hasAccess('interactions'))
                        <li>
                            <a href="{{ route('admin.interactions.index') }}">
                                <i class="bx bx-transfer"></i>
                                <span>Interactions</span>
                            </a>
                        </li>
                    @endif

                    {{-- HOROSCOPE --}}
                    {{-- @if ($user->hasAccess('zodiac_signs') || $user->hasAccess('horoscopes'))
                <li class="menu-title">HOROSCOPE MAINTENANCE</li>
                @endif

                @if ($user->hasAccess('zodiac_signs'))
                <li>
                    <a href="{{ route('admin.zodiac_signs.index') }}">
                        <i class="bx bx-wind"></i>
                        <span>Zodiac Signs</span>
                    </a>
                </li>
                @endif --}}

                    {{-- @if ($user->hasAccess('horoscopes'))
                <li>
                    <a href="{{ route('admin.horoscopes.index') }}">
                        <i class="bx bx-star"></i>
                        <span>Horoscope</span>
                    </a>
                </li>
                @endif --}}

                    {{-- USERS --}}
                    @if ($user->hasAccess('astrologers') || $user->hasAccess('users') || $user->hasAccess('payouts'))
                        <li class="menu-title">Astrologers & Users</li>
                    @endif

                    @if ($user->hasAccess('astrologers'))
                        <li>
                            <a href="{{ route('admin.astrologers.index') }}">
                                <i class="bx bx-user-circle"></i>
                                <span>Astrologers</span>
                            </a>
                        </li>
                    @endif

                    @if ($user->hasAccess('users'))
                        <li>
                            <a href="{{ route('admin.users.index') }}">
                                <i class="bx bx-group"></i>
                                <span>Users</span>
                            </a>
                        </li>
                    @endif

                    @if ($user->hasAccess('payouts'))
                        <li>
                            <a href="{{ route('admin.payouts.index') }}">
                                <i class="bx bx-money"></i>
                                <span>Payout Requests</span>
                            </a>
                        </li>
                    @endif

                    {{-- BLOG --}}
                    @if ($user->hasAccess('blog_categories') || $user->hasAccess('blogs'))
                        <li class="menu-title">Blog</li>
                    @endif

                    @if ($user->hasAccess('blog_categories'))
                        <li>
                            <a href="{{ route('admin.blog_categories.index') }}">
                                <i class="bx bx-layer"></i>
                                <span>Blog Category</span>
                            </a>
                        </li>
                    @endif

                    @if ($user->hasAccess('blogs'))
                        <li>
                            <a href="{{ route('admin.blogs.index') }}">
                                <i class="bx bx-list-ul"></i>
                                <span>Blog</span>
                            </a>
                        </li>
                    @endif

                    {{-- BANNERS --}}
                    @if ($user->hasAccess('astro_banners') || $user->hasAccess('store_banners') || $user->hasAccess('bulk_mails'))
                        <li class="menu-title">Banners</li>
                    @endif

                    @if ($user->hasAccess('astro_banners'))
                        <li>
                            <a href="{{ route('admin.astro_banners.index') }}">
                                <i class="bx bx-image"></i>
                                <span>Astro Banner</span>
                            </a>
                        </li>
                    @endif

                    @if ($user->hasAccess('bulk_mails'))
                        <li>
                            <a href="{{ route('admin.bulk_mails.index') }}">
                                <i class="bx bx-envelope"></i>
                                <span>Send Mail</span>
                            </a>
                        </li>
                    @endif

                    {{-- POLICIES --}}
                    @if ($user->hasAccess('policy_categories') || $user->hasAccess('policies'))
                        <li class="menu-title">Policy</li>
                    @endif

                    @if ($user->hasAccess('policy_categories'))
                        <li>
                            <a href="{{ route('admin.policy_categories.index') }}">
                                <i class="bx bx-layer"></i>
                                <span>Policy Category</span>
                            </a>
                        </li>
                    @endif

                    @if ($user->hasAccess('policies'))
                        <li>
                            <a href="{{ route('admin.policies.index') }}">
                                <i class="bx bx-list-ul"></i>
                                <span>Policy</span>
                            </a>
                        </li>
                    @endif

                @endif

            </ul>
        </div>
    </div>
</div>
