@extends('layouts.master')

@section('title')
    Dashboard
@endsection


@section('content')

<style>
    .stat-card {
        border: none;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(16,24,40,.06);
        transition: box-shadow .15s ease, transform .15s ease;
    }

    .stat-card:hover {
        box-shadow: 0 6px 16px rgba(16,24,40,.08);
        transform: translateY(-2px);
    }

    .stat-card .card-body {
        display: flex;
        align-items: center;
        gap: .9rem;
        padding: 1.1rem 1.2rem;
    }

    .stat-icon {
        width: 46px;
        height: 46px;
        min-width: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
    }

    .stat-icon.bg-astro { background: #eef2ff; color: #4f46e5; }
    .stat-icon.bg-astro-online { background: #ecfdf3; color: #12b76a; }
    .stat-icon.bg-user { background: #eff8ff; color: #2e90fa; }
    .stat-icon.bg-user-online { background: #fef6ee; color: #f79009; }

    .stat-label {
        font-size: .8rem;
        color: #667085;
        margin-bottom: .15rem;
    }

    .stat-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #101828;
        margin: 0;
    }

    .conn-card {
        border: none;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(16,24,40,.06);
    }

    .conn-card .card-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.1rem 1.3rem;
    }

    .conn-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #12b76a;
        display: inline-block;
        margin-right: 6px;
        box-shadow: 0 0 0 4px rgba(18,183,106,.15);
    }

    .chart-card {
        border: none;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(16,24,40,.06);
    }

    .chart-card .card-header {
        background: #fff;
        border-bottom: 1px solid #f0f2f5;
        border-radius: 14px 14px 0 0 !important;
        padding: .95rem 1.25rem;
        font-size: .95rem;
        font-weight: 700;
        color: #101828;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chart-card .card-header small {
        font-weight: 500;
        color: #98a2b3;
        font-size: .75rem;
    }

    .chart-card .card-body {
        padding: 1.25rem;
    }

    #dashboard_date_range {
        border-radius: 8px;
        background: #fff;
        max-width: 260px;
    }

    .dash-title {
        font-weight: 700;
        color: #101828;
    }

    /* ---------- Ranked rows (shared by 4 charts) ---------- */

    .rank-list {
        width: 100%;
    }

    .rank-row {
        display: grid;
        grid-template-columns: minmax(160px, 260px) minmax(0, 1fr) 80px;
        align-items: center;
        column-gap: 14px;
        padding: 6px 8px;
        border-radius: 10px;
        cursor: pointer;
        transition: background .12s ease;
    }

    .rank-row:hover,
    .rank-row.active {
        background: #f8f9fc;
    }

    .rank-name {
        color: #344054;
        font-size: 14px;
        font-weight: 600;
        /* text-align: right; */
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rank-track {
        width: 100%;
        height: 14px;
        background: #eef0f3;
        border-radius: 999px;
        overflow: hidden;
    }

    .rank-fill {
        height: 100%;
        border-radius: 999px;
        transition: width .35s ease, filter .12s ease;
    }

    .rank-row:hover .rank-fill,
    .rank-row.active .rank-fill {
        filter: brightness(.92);
    }

    .rank-value {
        color: #101828;
        font-size: 14px;
        font-weight: 700;
        text-align: right;
        white-space: nowrap;
    }

    .rank-toggle {
        display: block;
        margin: 10px auto 0;
        border: 1px solid #e4e7ec;
        background: #fff;
        color: #475467;
        font-size: 13px;
        font-weight: 600;
        padding: 6px 16px;
        border-radius: 8px;
    }

    .rank-toggle:hover {
        background: #f9fafb;
    }

    .rank-empty {
        text-align: center;
        color: #98a2b3;
        padding: 28px 0;
        font-size: 14px;
    }

    /* Tooltip */
    #rankTooltip {
        position: fixed;
        z-index: 9999;
        pointer-events: none;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity .1s ease, transform .1s ease;
        background: #101828;
        color: #fff;
        border-radius: 10px;
        padding: 9px 12px;
        font-size: 13px;
        line-height: 1.5;
        min-width: 150px;
        max-width: 260px;
        box-shadow: 0 8px 24px rgba(16,24,40,.25);
    }

    #rankTooltip.show {
        opacity: 1;
        transform: translateY(0);
    }

    #rankTooltip .tt-title {
        font-weight: 700;
        margin-bottom: 4px;
        word-break: break-word;
    }

    #rankTooltip .tt-line {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        color: #d0d5dd;
    }

    #rankTooltip .tt-line b {
        color: #fff;
    }

    @media (max-width: 991.98px) {
        .rank-row {
            grid-template-columns: minmax(120px, 190px) minmax(0, 1fr) 70px;
            column-gap: 10px;
        }

        .rank-name,
        .rank-value {
            font-size: 13px;
        }
    }

    @media (max-width: 767.98px) {
        .rank-row {
            grid-template-columns: 1fr auto;
            row-gap: 6px;
            margin-bottom: 8px;
        }

        .rank-name {
            text-align: left;
            grid-column: 1;
        }

        .rank-value {
            grid-column: 2;
        }

        .rank-track {
            grid-column: 1 / -1;
        }
    }
</style>

<div id="rankTooltip"></div>

<div class="row mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h4 class="dash-title">Dashboard</h4>
    </div>
</div>


{{-- TOP STATS --}}

<div class="row g-3 mb-3">

    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon bg-astro"><i class="fa fa-user-astronaut"></i></div>
                <div>
                    <p class="stat-label mb-0">Total Astrologers</p>
                    <h4 class="stat-value total_astrologers"><i class="fa fa-spinner fa-pulse"></i></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon bg-astro-online"><i class="fa fa-circle"></i></div>
                <div>
                    <p class="stat-label mb-0">Astrologers Online</p>
                    <h4 class="stat-value online_astrologers"><i class="fa fa-spinner fa-pulse"></i></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon bg-user"><i class="fa fa-users"></i></div>
                <div>
                    <p class="stat-label mb-0">Total Users</p>
                    <h4 class="stat-value total_users"><i class="fa fa-spinner fa-pulse"></i></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon bg-user-online"><i class="fa fa-circle"></i></div>
                <div>
                    <p class="stat-label mb-0">Users Online</p>
                    <h4 class="stat-value online_users"><i class="fa fa-spinner fa-pulse"></i></h4>
                </div>
            </div>
        </div>
    </div>

</div>


{{-- ACTIVE CONNECTIONS --}}

<div class="row g-3 mb-4">

    <div class="col-md-6">
        <div class="card conn-card">
            <div class="card-body">
                <div>
                    <p class="text-muted mb-1"><span class="conn-dot"></span>Active Call Connections</p>
                    <h4 class="stat-value active_call_connections mb-0">
                        <i class="fa fa-spinner fa-pulse"></i>
                    </h4>
                </div>
                <i class="fa fa-phone-volume fa-2x text-primary opacity-25"></i>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card conn-card">
            <div class="card-body">
                <div>
                    <p class="text-muted mb-1"><span class="conn-dot"></span>Active AI Chat Connections</p>
                    <h4 class="stat-value active_chat_connections mb-0">
                        <i class="fa fa-spinner fa-pulse"></i>
                    </h4>
                </div>
                <i class="fa fa-comments fa-2x text-primary opacity-25"></i>
            </div>
        </div>
    </div>

</div>


{{-- DATE RANGE --}}

<div class="row g-3 mb-4">
    <div class="col-md-12 d-flex justify-content-end align-items-center">
        <label class="me-2 fw-bold mb-0">Date Range:</label>
        <input
            type="text"
            id="dashboard_date_range"
            class="form-control"
            placeholder="Select Date Range"
        >
    </div>
</div>


{{-- PLATFORM GROWTH --}}

<div class="row g-3">
    <div class="col-md-12">
        <div class="card chart-card">
            <div class="card-header">Platform Growth</div>
            <div class="card-body">
                <div style="height: 350px;">
                    <canvas id="growthChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- USER CONNECTIONS --}}

<div class="row g-3 mt-1">
    <div class="col-md-12">
        <div class="card chart-card">
            <div class="card-header">User Connections</div>
            <div class="card-body">
                <div style="height: 350px;">
                    <canvas id="engagementChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- USERS BY EXPERTISE --}}

<div class="row g-3 mt-1">
    <div class="col-md-12">
        <div class="card chart-card">
            <div class="card-header">
                Users by Expertise
                <small></small>
            </div>
            <div class="card-body">
                <div id="expertiseUsersChartWrapper" class="rank-list"></div>
            </div>
        </div>
    </div>
</div>


{{-- USERS BY ASTROLOGER --}}

<div class="row g-3 mt-1">
    <div class="col-md-12">
        <div class="card chart-card">
            <div class="card-header">
                Users by Astrologer
                <small></small>
            </div>
            <div class="card-body">
                <div id="astrologerUsersChartWrapper" class="rank-list"></div>
            </div>
        </div>
    </div>
</div>


{{-- SPEND BY ASTROLOGER --}}

<div class="row g-3 mt-1">
    <div class="col-md-12">
        <div class="card chart-card">
            <div class="card-header">
                Spend by Astrologer
                <small></small>
            </div>
            <div class="card-body">
                <div id="astrologerSpendChartWrapper" class="rank-list"></div>
            </div>
        </div>
    </div>
</div>


{{-- REVIEWS BY ASTROLOGER --}}

<div class="row g-3 mt-1 mb-4">
    <div class="col-md-12">
        <div class="card chart-card">
            <div class="card-header">
                Reviews by Astrologer
                <small></small>
            </div>
            <div class="card-body">
                <div id="astrologerReviewsChartWrapper" class="rank-list"></div>
            </div>
        </div>
    </div>
</div>

@endsection


@section('script')

<script>

/*
|--------------------------------------------------------------------------
| DATE PICKER
|--------------------------------------------------------------------------
*/

const datePickerRanges = {
    'Today': [moment(), moment()],
    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
    'Last 15 Days': [moment().subtract(14, 'days'), moment()],
    'This Month': [moment().startOf('month'), moment().endOf('month')],
    'Last Month': [
        moment().subtract(1, 'month').startOf('month'),
        moment().subtract(1, 'month').endOf('month')
    ],
    'Last 3 Months': [
        moment().subtract(2, 'month').startOf('month'),
        moment().endOf('month')
    ],
    'This Year': [moment().startOf('year'), moment().endOf('year')],
    'Last Year': [
        moment().subtract(1, 'year').startOf('year'),
        moment().subtract(1, 'year').endOf('year')
    ]
};

const datePickerLocale = {
    format: 'YYYY-MM-DD',
    applyLabel: 'Apply',
    cancelLabel: 'Cancel',
    customRangeLabel: 'Custom'
};

const start = moment().subtract(6, 'days');
const end = moment();

$('#dashboard_date_range').daterangepicker({
    startDate: start,
    endDate: end,
    ranges: datePickerRanges,
    locale: datePickerLocale
}, loadGraphs);


/*
|--------------------------------------------------------------------------
| LINE CHART OPTIONS
|--------------------------------------------------------------------------
*/

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: { legend: { display: true } },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
    }
};

const growthChart = new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: { labels: [], datasets: [] },
    options: lineChartOptions
});

const engagementChart = new Chart(document.getElementById('engagementChart'), {
    type: 'line',
    data: { labels: [], datasets: [] },
    options: lineChartOptions
});


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function escapeHtml(value)
{
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatNumber(n)
{
    return Number(n || 0).toLocaleString('en-IN');
}

function formatRupee(n)
{
    return '₹' + Number(n || 0).toLocaleString('en-IN', {
        maximumFractionDigits: 2
    });
}


/*
|--------------------------------------------------------------------------
| RANKED ROWS (shared renderer)
|--------------------------------------------------------------------------
|
| mode 'percent' -> bar + right label = share % of total
| mode 'value'   -> bar relative to max, right label = formatted value
| Tooltip (hover / click) shows name, value and share %.
|--------------------------------------------------------------------------
*/

const RANK_LIMIT = 10;

const rankStore = {};

function renderRankList(wrapperId, labels, values, options)
{
    const wrapper = document.getElementById(wrapperId);

    if (!wrapper) {
        return;
    }

    const opts = Object.assign({
        color: '#f4b740',
        mode: 'percent',
        valueLabel: 'Users',
        format: formatNumber
    }, options || {});

    const safeLabels = Array.isArray(labels) ? labels : [];
    const safeValues = Array.isArray(values) ? values : [];

    const items = safeLabels
        .map((label, i) => ({
            label: label || '-',
            value: Number(safeValues[i] || 0)
        }))
        .sort((a, b) => b.value - a.value);

    const total = items.reduce((s, i) => s + i.value, 0);
    const max = items.length ? items[0].value : 0;

    if (!items.length || total <= 0) {
        wrapper.innerHTML =
            '<div class="rank-empty">No data available for the selected date range.</div>';
        delete rankStore[wrapperId];
        return;
    }

    items.forEach(item => {
        item.share = (item.value / total) * 100;
    });

    const prev = rankStore[wrapperId];

    rankStore[wrapperId] = {
        items: items,
        opts: opts,
        expanded: prev ? prev.expanded : false
    };

    drawRankList(wrapperId);
}

function drawRankList(wrapperId)
{
    const wrapper = document.getElementById(wrapperId);
    const store = rankStore[wrapperId];

    if (!wrapper || !store) {
        return;
    }

    const { items, opts, expanded } = store;
    const max = items[0].value;

    const visible = expanded ? items : items.slice(0, RANK_LIMIT);

    let html = visible.map((item, idx) => {

        const width = opts.mode === 'percent'
            ? item.share
            : (max > 0 ? (item.value / max) * 100 : 0);

        const rightText = opts.mode === 'percent'
            ? Math.round(item.share) + '%'
            : opts.format(item.value);

        return `
            <div class="rank-row" data-idx="${idx}">
                <div class="rank-name">${escapeHtml(item.label)}</div>
                <div class="rank-track">
                    <div class="rank-fill"
                         style="width:${Math.max(0, Math.min(100, width))}%;background:${opts.color};"></div>
                </div>
                <div class="rank-value">${rightText}</div>
            </div>
        `;
    }).join('');

    if (items.length > RANK_LIMIT) {
        html += `
            <button type="button" class="rank-toggle" data-toggle="${wrapperId}">
                ${expanded ? 'Show top ' + RANK_LIMIT : 'Show all (' + items.length + ')'}
            </button>
        `;
    }

    wrapper.innerHTML = html;
}


/*
|--------------------------------------------------------------------------
| TOOLTIP (hover + click)
|--------------------------------------------------------------------------
*/

const rankTooltip = document.getElementById('rankTooltip');

let pinnedRow = null;

function getRowData(row)
{
    const wrapper = row.closest('.rank-list');

    if (!wrapper) {
        return null;
    }

    const store = rankStore[wrapper.id];

    if (!store) {
        return null;
    }

    const item = store.items[Number(row.dataset.idx)];

    return item ? { item, opts: store.opts } : null;
}

function showRankTooltip(row, x, y)
{
    const data = getRowData(row);

    if (!data) {
        return;
    }

    const { item, opts } = data;

    const valueText = opts.mode === 'percent'
        ? formatNumber(item.value)
        : opts.format(item.value);

    rankTooltip.innerHTML = `
        <div class="tt-title">${escapeHtml(item.label)}</div>
        <div class="tt-line"><span>${escapeHtml(opts.valueLabel)}</span><b>${valueText}</b></div>
        <div class="tt-line"><span>Share</span><b>${item.share.toFixed(1)}%</b></div>
    `;

    rankTooltip.classList.add('show');

    positionRankTooltip(x, y);
}

function positionRankTooltip(x, y)
{
    const pad = 14;
    const w = rankTooltip.offsetWidth;
    const h = rankTooltip.offsetHeight;

    let left = x + pad;
    let top = y + pad;

    if (left + w > window.innerWidth - 8) {
        left = x - w - pad;
    }

    if (top + h > window.innerHeight - 8) {
        top = y - h - pad;
    }

    rankTooltip.style.left = Math.max(8, left) + 'px';
    rankTooltip.style.top = Math.max(8, top) + 'px';
}

function hideRankTooltip()
{
    rankTooltip.classList.remove('show');

    if (pinnedRow) {
        pinnedRow.classList.remove('active');
        pinnedRow = null;
    }
}

document.addEventListener('mousemove', function (e) {

    if (pinnedRow) {
        return;
    }

    const row = e.target.closest ? e.target.closest('.rank-row') : null;

    if (row) {
        showRankTooltip(row, e.clientX, e.clientY);
    } else {
        rankTooltip.classList.remove('show');
    }

});

document.addEventListener('click', function (e) {

    const toggle = e.target.closest ? e.target.closest('[data-toggle]') : null;

    if (toggle) {
        const id = toggle.dataset.toggle;

        if (rankStore[id]) {
            rankStore[id].expanded = !rankStore[id].expanded;
            hideRankTooltip();
            drawRankList(id);
        }

        return;
    }

    const row = e.target.closest ? e.target.closest('.rank-row') : null;

    if (!row) {
        hideRankTooltip();
        return;
    }

    if (pinnedRow === row) {
        hideRankTooltip();
        return;
    }

    if (pinnedRow) {
        pinnedRow.classList.remove('active');
    }

    pinnedRow = row;
    row.classList.add('active');

    showRankTooltip(row, e.clientX, e.clientY);

});

document.addEventListener('scroll', hideRankTooltip, true);


/*
|--------------------------------------------------------------------------
| LOAD ALL GRAPHS
|--------------------------------------------------------------------------
*/

function loadGraphs(start, end)
{
    loadStats();

    const params = {
        start_date: start.format('YYYY-MM-DD'),
        end_date: end.format('YYYY-MM-DD')
    };

    /* PLATFORM GROWTH */
    $.get('{{ route("admin.dashboard.graph.growth") }}', params, function (res) {

        growthChart.data.labels = res.labels;

        growthChart.data.datasets = [
            {
                label: 'Astrologers',
                data: res.astrologers,
                borderWidth: 2,
                tension: 0.3
            },
            {
                label: 'Users',
                data: res.customers,
                borderWidth: 2,
                tension: 0.3
            }
        ];

        growthChart.update();

    });

    /* USER CONNECTIONS */
    $.get('{{ route("admin.dashboard.graph.engagement") }}', params, function (res) {

        engagementChart.data.labels = res.labels;

        engagementChart.data.datasets = [
            {
                label: 'Calls',
                data: res.calls,
                borderWidth: 2,
                tension: 0.3
            },
            {
                label: 'AI Chats',
                data: res.chats,
                borderWidth: 2,
                tension: 0.3
            }
        ];

        engagementChart.update();

    });

    /* USERS BY EXPERTISE (%) */
    $.get('{{ route("admin.dashboard.graph.expertise.users") }}', params, function (res) {

        renderRankList(
            'expertiseUsersChartWrapper',
            res.labels || [],
            res.users || [],
            { color: '#f4b740', mode: 'percent', valueLabel: 'Users' }
        );

    });

    /* USERS BY ASTROLOGER (%) */
    $.get('{{ route("admin.dashboard.graph.astrologer.users") }}', params, function (res) {

        renderRankList(
            'astrologerUsersChartWrapper',
            res.labels || [],
            res.users || [],
            { color: '#4f46e5', mode: 'percent', valueLabel: 'Users' }
        );

    });

    /* SPEND BY ASTROLOGER (₹) */
    $.get('{{ route("admin.dashboard.graph.astrologer.spend") }}', params, function (res) {

        renderRankList(
            'astrologerSpendChartWrapper',
            res.labels || [],
            res.amount || [],
            {
                color: '#12b76a',
                mode: 'value',
                valueLabel: 'Amount Spent',
                format: formatRupee
            }
        );

    });

    /* REVIEWS BY ASTROLOGER */
    $.get('{{ route("admin.dashboard.graph.astrologer.reviews") }}', params, function (res) {

        renderRankList(
            'astrologerReviewsChartWrapper',
            res.labels || [],
            res.reviews || [],
            {
                color: '#f79009',
                mode: 'value',
                valueLabel: 'Reviews',
                format: formatNumber
            }
        );

    });
}


/*
|--------------------------------------------------------------------------
| LOAD TOP STATS
|--------------------------------------------------------------------------
*/

function loadStats()
{
    $.get('{{ route("admin.dashboard.stats") }}', function (res) {

        Object.keys(res).forEach(function (key) {
            $('.' + key).text(res[key]);
        });

    });
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

loadGraphs(start, end);

</script>

@endsection