@extends('layouts.app')
@section('title', 'Dashboard V2')

@section('css')
<style>
    .dash-v2 {
        background: linear-gradient(180deg, #f7f9fb 0%, #ffffff 100%);
        border-radius: 10px;
        padding: 18px;
    }
    .dash-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }
    .dash-actions {
        margin-left: auto;
        justify-content: flex-end;
    }
    .dash-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .dash-title {
        font-size: 22px;
        font-weight: 700;
        color: #1f2d3d;
        margin: 0;
    }
    .period-toggle {
        display: inline-flex;
        gap: 6px;
        background: #fff;
        padding: 6px;
        border-radius: 999px;
        border: 1px solid #e6edf3;
        box-shadow: 0 4px 12px rgba(31, 45, 61, 0.06);
    }
    .year-select {
        border: 1px solid #e6edf3;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #4a5568;
        background: #fff;
    }
    .range-input {
        border: 1px solid #e6edf3;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        color: #4a5568;
        background: #fff;
        width: 210px;
    }
    .export-btn {
        border: 1px solid #1abc9c;
        background: #1abc9c;
        color: #fff;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        white-space: nowrap;
    }
    .export-btn:hover {
        background: #17a589;
        border-color: #17a589;
    }
    .ai-card {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .ai-loading {
        font-size: 12px;
        color: #718096;
    }
    .ai-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .ai-box {
        border: 1px solid #e6edf3;
        border-radius: 10px;
        padding: 12px;
        background: #f9fbfd;
    }
    .ai-box h4 {
        font-size: 12px;
        margin: 0 0 8px 0;
        color: #2d3748;
    }
    .ai-list {
        margin: 0;
        padding-left: 16px;
        font-size: 12px;
        color: #4a5568;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .ai-meta {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        font-size: 11px;
        color: #718096;
    }
    .ai-chip {
        border: 1px solid #e6edf3;
        border-radius: 999px;
        padding: 4px 8px;
        background: #fff;
        font-weight: 600;
    }
    .ai-chat {
        border: 1px solid #e6edf3;
        border-radius: 12px;
        padding: 12px;
        background: #fff;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ai-chat-messages {
        border: 1px solid #eef2f7;
        border-radius: 10px;
        padding: 10px;
        background: #f8fafc;
        min-height: 120px;
        max-height: 240px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-size: 12px;
        color: #2d3748;
    }
    .ai-chat-msg {
        padding: 8px 10px;
        border-radius: 10px;
        max-width: 85%;
        line-height: 1.4;
    }
    .ai-chat-user {
        align-self: flex-end;
        background: #e6fffa;
        border: 1px solid #b2f5ea;
    }
    .ai-chat-ai {
        align-self: flex-start;
        background: #fff;
        border: 1px solid #e6edf3;
    }
    .ai-chat-input {
        display: flex;
        gap: 8px;
    }
    .ai-chat-input input {
        flex: 1;
        border: 1px solid #e6edf3;
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 12px;
        background-color: #fff;
        color: #2d3748;
    }
    .ai-chat-input button {
        border: 1px solid #1abc9c;
        background: #1abc9c;
        color: #fff;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    @media (max-width: 900px) {
        .ai-section {
            grid-template-columns: 1fr;
        }
    }
    .daterangepicker select.monthselect,
    .daterangepicker select.yearselect {
        background: #ffffff;
        color: #2d3748;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
    }
    .period-toggle a {
        padding: 6px 12px;
        border-radius: 999px;
        color: #4a5568;
        font-weight: 600;
        font-size: 12px;
        text-decoration: none;
    }
    .period-toggle a.active {
        background: #1abc9c;
        color: #fff;
    }
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #e6edf3;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
    }
    .stat-label {
        font-size: 12px;
        color: #7f8c8d;
        font-weight: 600;
        margin-bottom: 6px;
    }
    .stat-value {
        font-size: 22px;
        font-weight: 700;
        color: #1f2d3d;
    }
    .stat-sub {
        font-size: 11px;
        color: #95a5a6;
        margin-top: 6px;
    }
    .revenue-breakdown {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .revenue-chip {
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        width: fit-content;
        min-width: 190px;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid transparent;
    }
    .revenue-chip .label {
        font-size: 11px;
        font-weight: 700;
        margin: 0;
        padding: 0;
        color: inherit;
    }
    .revenue-chip .value {
        font-size: 12px;
        font-weight: 800;
        color: inherit;
    }
    .revenue-chip.vat {
        background: #fff4e5;
        border-color: #ffd8a8;
        color: #9a3412;
    }
    .revenue-chip.inc {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #3730a3;
    }
    .dash-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 14px;
        margin-bottom: 16px;
    }
    .card {
        background: #fff;
        border: 1px solid #e6edf3;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
    }
    .card-title {
        font-size: 14px;
        font-weight: 700;
        color: #1f2d3d;
        margin-bottom: 12px;
    }
    .bar-chart {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        min-height: 220px;
        overflow-x: auto;
        padding-bottom: 8px;
    }
    .bar-group {
        min-width: 28px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }
    .bars {
        display: flex;
        gap: 4px;
        align-items: flex-end;
        height: 200px;
    }
    .bar {
        width: 8px;
        border-radius: 6px;
    }
    .bar-revenue {
        background: #6C5CE7;
    }
    .bar-qty {
        background: #00B894;
    }
    .bar-label {
        font-size: 10px;
        color: #718096;
        white-space: nowrap;
    }
    .legend {
        display: flex;
        gap: 12px;
        font-size: 11px;
        color: #7f8c8d;
        margin-top: 8px;
    }
    .legend span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .legend i {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .donut-wrap {
        display: flex;
        gap: 16px;
        align-items: center;
    }
    .donut {
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: #f1f5f9;
        position: relative;
    }
    .donut:after {
        content: '';
        position: absolute;
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: #fff;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        box-shadow: inset 0 0 0 1px #e6edf3;
    }
    .category-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-size: 12px;
        color: #4a5568;
    }
    .category-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .category-name {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .top-products {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .product-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .product-list.single {
        grid-template-columns: 1fr;
    }
    .product-card {
        display: flex;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e6edf3;
        border-radius: 10px;
        background: #fff;
        align-items: center;
    }
    .collapse-toggle {
        border: 1px solid #e6edf3;
        background: #fff;
        color: #4a5568;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 8px;
    }
    .product-thumb {
        width: 54px;
        height: 54px;
        border-radius: 10px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
    }
    .product-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .product-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .product-meta .name {
        font-weight: 700;
        color: #2d3748;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .product-meta .sub {
        font-size: 11px;
        color: #718096;
    }
    .product-stats {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 11px;
        color: #4a5568;
        min-width: 180px;
    }
    .product-stats .value {
        font-weight: 700;
        color: #1f2d3d;
        font-size: 12px;
    }
    .product-stats .stat-block {
        text-align: right;
        min-width: 80px;
    }
    .product-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding-bottom: 8px;
        border-bottom: 1px dashed #e6edf3;
        font-size: 12px;
        color: #4a5568;
    }
    .product-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .product-name {
        font-weight: 600;
        color: #2d3748;
    }
    @media (max-width: 1100px) {
        .stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .dash-row {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 640px) {
        .stat-grid {
            grid-template-columns: 1fr;
        }
        .donut-wrap {
            flex-direction: column;
        }
        .product-list {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<section class="content no-print">
    <div class="dash-v2">
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Dashboard V2</h1>
                <div class="stat-sub">
                    {{ $range_start->format('d M Y') }} - {{ $range_end->format('d M Y') }}
                </div>
            </div>
            <div class="dash-actions">
            <div class="period-toggle">
                <a href="{{ route('dashboard.v2', ['period' => 'day']) }}" class="{{ $period === 'day' ? 'active' : '' }}">Day</a>
                <a href="{{ route('dashboard.v2', ['period' => 'week']) }}" class="{{ $period === 'week' ? 'active' : '' }}">Week</a>
                <a href="{{ route('dashboard.v2', ['period' => 'month']) }}" class="{{ $period === 'month' ? 'active' : '' }}">Month</a>
                <a href="{{ route('dashboard.v2', ['period' => 'year', 'year' => $selected_year]) }}" class="{{ $period === 'year' ? 'active' : '' }}">Year</a>
            </div>
            <form method="GET" action="{{ route('dashboard.v2') }}" id="dashboard_v2_range_form">
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="year" value="{{ $selected_year }}">
                <input type="hidden" name="start_date" id="dashboard_v2_start_date" value="{{ $custom_start }}">
                <input type="hidden" name="end_date" id="dashboard_v2_end_date" value="{{ $custom_end }}">
                <input type="text" class="range-input" id="dashboard_v2_date_range" placeholder="Select date range" readonly>
            </form>
            <form method="GET" action="{{ route('dashboard.v2') }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <select name="year" class="year-select" onchange="this.form.submit()">
                    @foreach ($year_options as $year)
                        <option value="{{ $year }}" {{ $year == $selected_year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
            </form>
            <form method="GET" action="{{ route('dashboard.v2.export') }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="year" value="{{ $selected_year }}">
                <input type="hidden" name="start_date" value="{{ $custom_start }}">
                <input type="hidden" name="end_date" value="{{ $custom_end }}">
                <button type="submit" class="export-btn">
                    <i class="fa fa-download"></i> Export CSV
                </button>
            </form>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label">Total revenue</div>
                <div class="stat-value">฿ {{ @num_format($total_revenue_ex_vat ?? 0) }}</div>
                <div class="revenue-breakdown">
                    <div class="revenue-chip vat">
                        <span class="label">VAT</span>
                        <span class="value">฿ {{ @num_format($total_vat ?? 0) }}</span>
                    </div>
                    <div class="revenue-chip inc">
                        <span class="label">Inc VAT</span>
                        <span class="value">฿ {{ @num_format($total_revenue) }}</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total orders</div>
                <div class="stat-value">{{ number_format($total_orders) }}</div>
                <div class="stat-sub">Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Products sold</div>
                <div class="stat-value">{{ number_format($products_sold) }}</div>
                <div class="stat-sub">Qty sold</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Customers</div>
                <div class="stat-value">{{ number_format($total_customers) }}</div>
                <div class="stat-sub">Unique customers</div>
            </div>
        </div>

        <div class="dash-row">
            <div class="card">
                <div class="card-title">Product sales</div>
                @php
                    $maxRevenue = !empty($trend_revenue) ? max($trend_revenue) : 0;
                    $maxQty = !empty($trend_qty) ? max($trend_qty) : 0;
                    $maxRevenue = $maxRevenue > 0 ? $maxRevenue : 1;
                    $maxQty = $maxQty > 0 ? $maxQty : 1;
                @endphp
                <div class="bar-chart">
                    @foreach ($trend_labels as $i => $label)
                        @php
                            $revHeight = ($trend_revenue[$i] / $maxRevenue) * 100;
                            $qtyHeight = ($trend_qty[$i] / $maxQty) * 100;
                        @endphp
                        <div class="bar-group">
                            <div class="bars">
                                <div class="bar bar-revenue" style="height: {{ $revHeight }}%;"></div>
                                <div class="bar bar-qty" style="height: {{ $qtyHeight }}%;"></div>
                            </div>
                            <div class="bar-label">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="legend">
                    <span><i style="background: #6C5CE7;"></i> Revenue</span>
                    <span><i style="background: #00B894;"></i> Quantity</span>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Top products</div>
                <div class="top-products">
                    @forelse ($top_products as $product)
                        <div class="product-row">
                            <div class="product-name">{{ $product->name }}</div>
                            <div>{{ number_format($product->qty) }} pcs</div>
                            <div>฿ {{ @num_format($product->total) }}</div>
                        </div>
                    @empty
                        <div class="stat-sub">No sales in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="dash-row">
            <div class="card">
                <div class="card-title">Sales by product category</div>
                @php
                    $gradientParts = [];
                    $cursor = 0;
                    foreach ($category_breakdown as $cat) {
                        $next = $cursor + $cat['percent'];
                        $gradientParts[] = $cat['color'] . ' ' . $cursor . '% ' . $next . '%';
                        $cursor = $next;
                    }
                    $gradient = !empty($gradientParts) ? implode(', ', $gradientParts) : '#e2e8f0 0% 100%';
                @endphp
                <div class="donut-wrap">
                    <div class="donut" style="background: conic-gradient({{ $gradient }});"></div>
                    <div class="category-list">
                        @forelse ($category_breakdown as $cat)
                            <div class="category-item">
                                <div class="category-name">
                                    <span class="dot" style="background: {{ $cat['color'] }};"></span>
                                    {{ $cat['name'] }}
                                </div>
                                <div>{{ $cat['percent'] }}%</div>
                            </div>
                        @empty
                            <div class="stat-sub">No category data.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-title">Summary</div>
                <div class="stat-sub" style="margin-bottom: 10px;">
                    Period: {{ ucfirst($period) }}
                </div>
                <div class="top-products">
                    <div class="product-row">
                        <div class="product-name">Revenue</div>
                        <div>฿ {{ @num_format($total_revenue) }}</div>
                    </div>
                    <div class="product-row">
                        <div class="product-name">Orders</div>
                        <div>{{ number_format($total_orders) }}</div>
                    </div>
                    <div class="product-row">
                        <div class="product-name">Products sold</div>
                        <div>{{ number_format($products_sold) }}</div>
                    </div>
                    <div class="product-row">
                        <div class="product-name">Customers</div>
                        <div>{{ number_format($total_customers) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 14px;">
            <div class="card-title">AI Suggestions / คำแนะนำ AI</div>
            <div class="ai-card" id="ai_suggestions_card">
                <div class="ai-loading" id="ai_suggestions_loading">Loading AI suggestions...</div>
                <div class="ai-meta" id="ai_suggestions_meta" style="display: none;">
                    <span class="ai-chip" id="ai_confidence_chip">Confidence: -</span>
                    <span class="ai-chip" id="ai_range_chip">Range: -</span>
                </div>
                <div class="ai-section" id="ai_suggestions_content" style="display: none;">
                    <div class="ai-box">
                        <h4>English</h4>
                        <ul class="ai-list" id="ai_bullets_en"></ul>
                        <h4 style="margin-top: 10px;">Risks</h4>
                        <ul class="ai-list" id="ai_risks_en"></ul>
                        <h4 style="margin-top: 10px;">Assumptions</h4>
                        <ul class="ai-list" id="ai_assumptions_en"></ul>
                    </div>
                    <div class="ai-box">
                        <h4>ภาษาไทย</h4>
                        <ul class="ai-list" id="ai_bullets_th"></ul>
                        <h4 style="margin-top: 10px;">ความเสี่ยง</h4>
                        <ul class="ai-list" id="ai_risks_th"></ul>
                        <h4 style="margin-top: 10px;">สมมติฐาน</h4>
                        <ul class="ai-list" id="ai_assumptions_th"></ul>
                    </div>
                </div>
                <div class="ai-chat">
                    <div class="ai-chat-messages" id="ai_chat_messages">
                        <div class="ai-chat-msg ai-chat-ai">Ask about demand, ordering, or categories. / ถามเรื่องดีมานด์ การสั่งซื้อ หรือหมวดหมู่สินค้าได้เลย</div>
                    </div>
                    <div class="ai-chat-input">
                        <input type="text" id="ai_chat_input" placeholder="Ask the AI..." />
                        <button type="button" id="ai_chat_send">Send</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 14px;">
            <div class="card-title">
                Products sold
                <span class="stat-sub" style="margin-left: 8px;">
                    ({{ $range_start->format('d M Y') }} - {{ $range_end->format('d M Y') }})
                </span>
                <button type="button" class="ai-purchase-button" id="btn_open_purchase_plan">
                    <i class="fa fa-magic"></i> Plan Order (AI)
                </button>
                <button type="button" class="collapse-toggle" id="toggle_products_sold">
                    <i class="fa fa-chevron-up"></i> Hide
                </button>
            </div>
            <div class="product-list" id="products_sold_section">
                @forelse ($period_products as $product)
                    @php
                        $imageUrl = !empty($product->image) ? asset('/uploads/img/' . rawurlencode($product->image)) : null;
                    @endphp
                    <div class="product-card">
                        <div class="product-thumb">
                            @if (!empty($imageUrl))
                                <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
                            @else
                                <i class="fa fa-image" style="color:#cbd5e1;"></i>
                            @endif
                        </div>
                        <div class="product-meta">
                            <div class="name">{{ $product->name }}</div>
                            <div class="sub">Price: ฿ {{ @num_format($product->price_inc_tax ?? 0) }}</div>
                            <div class="sub">Stock: {{ number_format($product->stock ?? 0) }}</div>
                        </div>
                        <div class="product-stats">
                            <div class="stat-block">
                                <div>Sold</div>
                                <div class="value">{{ number_format($product->qty_sold ?? 0) }}</div>
                            </div>
                            <div class="stat-block">
                                <div>Total</div>
                                <div class="value">฿ {{ @num_format($product->total_sold ?? 0) }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="stat-sub">No products sold in this period.</div>
                @endforelse
            </div>
        </div>

        <div class="card" style="margin-top: 14px;">
            <div class="card-title">
                Products not sold
                <span class="stat-sub" style="margin-left: 8px;">
                    ({{ $range_start->format('d M Y') }} - {{ $range_end->format('d M Y') }})
                </span>
            </div>
            <div class="product-list single">
                @forelse ($not_sold_products as $product)
                    @php
                        $imageUrl = !empty($product->image) ? asset('/uploads/img/' . rawurlencode($product->image)) : null;
                    @endphp
                    <div class="product-card">
                        <div class="product-thumb">
                            @if (!empty($imageUrl))
                                <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
                            @else
                                <i class="fa fa-image" style="color:#cbd5e1;"></i>
                            @endif
                        </div>
                        <div class="product-meta">
                            <div class="name">{{ $product->name }}</div>
                            <div class="sub">Price: ฿ {{ @num_format($product->price_inc_tax ?? 0) }}</div>
                            <div class="sub">Stock: {{ number_format($product->stock ?? 0) }}</div>
                        </div>
                        <div class="product-stats">
                            <div class="stat-block">
                                <div>Sold</div>
                                <div class="value">0</div>
                            </div>
                            <div class="stat-block">
                                <div>Total</div>
                                <div class="value">฿ 0.00</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="stat-sub">No products without sales in this period.</div>
                @endforelse
            </div>
            @if ($not_sold_products instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div style="margin-top: 12px;">
                    {{ $not_sold_products->appends(request()->except('not_sold_page'))->links() }}
                </div>
            @endif
        </div>
    </div>
    <!-- Purchase Plan Modal -->
    <div class="purchase-plan-overlay" id="purchase_plan_modal">
        <div class="purchase-plan-modal" id="purchase_plan_printable">
            <div class="purchase-plan-header no-print">
                <h3><i class="fa fa-magic"></i> AI Purchase Plan Generator</h3>
                <button type="button" class="close-btn" id="btn_close_purchase_plan">&times;</button>
            </div>
            <div class="purchase-plan-body">
                <div class="purchase-plan-controls no-print">
                    <div class="input-group">
                        <label>Monthly Budget (THB)</label>
                        <input type="number" id="purchase_budget" value="2200000" step="100000" />
                    </div>
                    <div class="input-group">
                        <label>Cover Days</label>
                        <input type="number" id="purchase_days" value="60" />
                    </div>
                    <div class="input-group" style="flex: 1; display: none;">
                        <label>Exclude Products</label>
                        <input type="text" id="purchase_exclude" placeholder="e.g. RB899, RB5300" />
                    </div>
                    <button type="button" class="action-btn" id="btn_generate_plan">Generate Plan</button>
                    <button type="button" class="action-btn-outline" id="btn_print_plan" style="display:none;"><i class="fa fa-print"></i> Print PDF</button>
                    <a href="{{ url('sells/create?status=quotation') }}" class="action-btn" id="btn_create_quotation_from_plan" style="display:none;"><i class="fa fa-file-text-o" aria-hidden="true"></i> Create Quotation</a>
                </div>
                
                <div id="purchase_plan_loading" style="display:none; text-align:center; padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p>AI is analyzing the data and preparing your plan...</p>
                </div>

                <div id="purchase_plan_result" class="purchase-plan-result" style="display:none;"></div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
    <script>
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        var startVal = $('#dashboard_v2_start_date').val();
        var endVal = $('#dashboard_v2_end_date').val();

        $('#dashboard_v2_date_range').daterangepicker({
            autoUpdateInput: false,
            showDropdowns: true,
            minYear: 2020,
            maxYear: parseInt(moment().format('YYYY'), 10) + 1,
            linkedCalendars: false, // allow left/right calendars to move independently
            locale: {
                format: moment_date_format,
                separator: ' ~ ',
                applyLabel: 'Apply',
                cancelLabel: 'Clear',
                fromLabel: 'From',
                toLabel: 'To',
                customRangeLabel: 'Custom',
                weekLabel: 'W',
                daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                firstDay: 0
            }
        });

        if (startVal && endVal) {
            var startMoment = moment(startVal);
            var endMoment = moment(endVal);
            $('#dashboard_v2_date_range').data('daterangepicker').setStartDate(startMoment);
            $('#dashboard_v2_date_range').data('daterangepicker').setEndDate(endMoment);
            $('#dashboard_v2_date_range').val(startMoment.format(moment_date_format) + ' ~ ' + endMoment.format(moment_date_format));
        }

        $('#dashboard_v2_date_range').on('apply.daterangepicker', function(ev, picker) {
            $('#dashboard_v2_start_date').val(picker.startDate.format('YYYY-MM-DD'));
            $('#dashboard_v2_end_date').val(picker.endDate.format('YYYY-MM-DD'));
            $('#dashboard_v2_range_form').submit();
        });

        $('#dashboard_v2_date_range').on('cancel.daterangepicker', function(ev, picker) {
            $('#dashboard_v2_start_date').val('');
            $('#dashboard_v2_end_date').val('');
            $(this).val('');
            $('#dashboard_v2_range_form').submit();
        });

        $('#toggle_products_sold').on('click', function() {
            var isVisible = $('#products_sold_section').is(':visible');
            if (isVisible) {
                $('#products_sold_section').slideUp(150);
                $(this).html('<i class="fa fa-chevron-down"></i> Show');
                localStorage.setItem('dashboard_v2_products_sold_collapsed', 'true');
            } else {
                $('#products_sold_section').slideDown(150);
                $(this).html('<i class="fa fa-chevron-up"></i> Hide');
                localStorage.setItem('dashboard_v2_products_sold_collapsed', 'false');
            }
        });

        var collapsed = localStorage.getItem('dashboard_v2_products_sold_collapsed');
        if (collapsed === 'true') {
            $('#products_sold_section').hide();
            $('#toggle_products_sold').html('<i class="fa fa-chevron-down"></i> Show');
        }

        var aiResponseId = null;
        var aiChatHistory = [];
        var aiFilters = {
            period: '{{ $period }}',
            year: '{{ $selected_year }}',
            start_date: '{{ $custom_start }}',
            end_date: '{{ $custom_end }}'
        };

        function renderList($el, items) {
            $el.empty();
            if (!items || !items.length) {
                $el.append('<li>No data</li>');
                return;
            }
            items.forEach(function(item) {
                $el.append('<li>' + $('<div>').text(item).html() + '</li>');
            });
        }

        function loadAiSuggestions() {
            $('#ai_suggestions_loading').show().text('Loading AI suggestions...');
            $('#ai_suggestions_content').hide();
            $('#ai_suggestions_meta').hide();

            $.get('{{ route("dashboard.v2.ai") }}', aiFilters)
                .done(function(res) {
                    if (!res || !res.success) {
                        console.error('AI suggestions failed:', res);
                        var msg = res && res.message ? res.message : 'AI suggestions are unavailable.';
                        $('#ai_suggestions_loading').text(msg);
                        return;
                    }

                    aiResponseId = res.response_id || null;
                    var data = res.data || {};
                    $('#ai_confidence_chip').text('Confidence: ' + (data.confidence || '-'));
                    $('#ai_range_chip').text('Range: {{ $range_start->format("Y-m-d") }} to {{ $range_end->format("Y-m-d") }}');

                    renderList($('#ai_bullets_en'), data.bullets_en || []);
                    renderList($('#ai_bullets_th'), data.bullets_th || []);
                    renderList($('#ai_risks_en'), data.risks_en || []);
                    renderList($('#ai_risks_th'), data.risks_th || []);
                    renderList($('#ai_assumptions_en'), data.assumptions_en || []);
                    renderList($('#ai_assumptions_th'), data.assumptions_th || []);

                    $('#ai_suggestions_loading').hide();
                    $('#ai_suggestions_content').show();
                    $('#ai_suggestions_meta').show();
                })
                .fail(function(jqXHR) {
                    console.error('AI suggestions request failed.', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        responseJSON: jqXHR.responseJSON
                    });
                    var msg = 'AI suggestions are unavailable.';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        msg = jqXHR.responseJSON.message;
                    } else if (jqXHR.responseText) {
                        msg = jqXHR.responseText;
                    }
                    $('#ai_suggestions_loading').text(msg);
                });
        }

        function appendChatMessage(text, isUser) {
            var cls = isUser ? 'ai-chat-user' : 'ai-chat-ai';
            var safe = isUser ? $('<div>').text(text).html() : marked.parse(text);
            $('#ai_chat_messages').append('<div class="ai-chat-msg ' + cls + '">' + safe + '</div>');
            var container = $('#ai_chat_messages');
            container.scrollTop(container[0].scrollHeight);
        }

        function sendChatMessage() {
            var input = $('#ai_chat_input');
            var message = input.val().trim();
            if (!message) {
                return;
            }
            input.val('');
            appendChatMessage(message, true);

            aiChatHistory.push({ role: 'user', content: message });
            if (aiChatHistory.length > 20) {
                aiChatHistory = aiChatHistory.slice(aiChatHistory.length - 20);
            }

            $.post('{{ route("dashboard.v2.ai.chat") }}', {
                message: message,
                history: aiChatHistory,
                previous_response_id: aiResponseId,
                period: aiFilters.period,
                year: aiFilters.year,
                start_date: aiFilters.start_date,
                end_date: aiFilters.end_date
            })
            .done(function(res) {
                if (!res || !res.success) {
                    console.error('AI chat failed:', res);
                    var msg = res && res.message ? res.message : 'AI response unavailable.';
                    appendChatMessage(msg, false);
                    aiChatHistory.pop();
                    return;
                }
                aiResponseId = res.response_id || aiResponseId;
                if (res.reply_th) {
                    reply = res.reply_th;
                } else if (res.reply_en) {
                    reply = res.reply_en;
                }
                var finalReply = reply || 'ไม่มีการตอบกลับ';
                appendChatMessage(finalReply, false);
                
                aiChatHistory.push({ role: 'assistant', content: finalReply });
            })
            .fail(function(jqXHR) {
                console.error('AI chat request failed.', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    responseJSON: jqXHR.responseJSON
                });
                var msg = 'AI response unavailable.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    msg = jqXHR.responseJSON.message;
                } else if (jqXHR.responseText) {
                    msg = jqXHR.responseText;
                }
                appendChatMessage(msg, false);
                aiChatHistory.pop();
            });
        }

        $('#ai_chat_send').on('click', function() {
            sendChatMessage();
        });
        $('#ai_chat_input').on('keypress', function(e) {
            if (e.which === 13) {
                sendChatMessage();
            }
        });

        // Purchase Plan Generator UI Logic
        var purchasePlanModal = $('#purchase_plan_modal');
        var btnOpenPlan = $('#btn_open_purchase_plan');
        var btnClosePlan = $('#btn_close_purchase_plan');
        var btnGeneratePlan = $('#btn_generate_plan');
        var btnPrintPlan = $('#btn_print_plan');
        var btnCreateQuotation = $('#btn_create_quotation_from_plan');
        var planResultBody = $('#purchase_plan_result');
        var planLoading = $('#purchase_plan_loading');

        btnOpenPlan.on('click', function(e) {
            e.preventDefault();
            purchasePlanModal.css('display', 'flex');
        });

        btnClosePlan.on('click', function() {
            purchasePlanModal.hide();
        });

        purchasePlanModal.on('click', function(e) {
            if (e.target === this) {
                purchasePlanModal.hide();
            }
        });

        btnPrintPlan.on('click', function() {
            window.print();
        });

        btnGeneratePlan.on('click', function() {
            var budget = $('#purchase_budget').val();
            var days = $('#purchase_days').val();
            var exclude = $('#purchase_exclude').val();

            if (!budget || !days) {
                alert('Please enter both Budget and Cover Days.');
                return;
            }

            planResultBody.hide().empty();
            btnPrintPlan.hide();
            btnCreateQuotation.hide();
            planLoading.show();
            btnGeneratePlan.prop('disabled', true).text('Generating...');

            $.post('{{ route("dashboard.v2.ai.purchase_plan") }}', {
                monthly_budget: budget,
                cover_days: days,
                exclude_products: exclude,
                period: aiFilters.period,
                year: aiFilters.year,
                start_date: aiFilters.start_date,
                end_date: aiFilters.end_date
            })
            .done(function(res) {
                planLoading.hide();
                btnGeneratePlan.prop('disabled', false).text('Regenerate Plan');

                if (!res || !res.success) {
                    planResultBody.html('<div style="color:red;">Error: ' + (res.message || 'Failed to generate plan.') + '</div>').show();
                    return;
                }

                var renderPlan = function(enText, thText) {
                    var html = '';
                    if (!thText && enText) {
                        thText = 'ยังไม่มีคำแปลไทย ระบบจะแสดงเนื้อหาภาษาอังกฤษชั่วคราว:\n\n' + enText;
                    }
                    if (thText) {
                        html += '<h2 style="margin-top:0;">แผนจัดซื้อ (TH)</h2>';
                        html += '<div class="plan-block th">' + marked.parse(thText) + '</div>';
                    }
                    if (enText) {
                        html += '<h2 style="margin-top:24px;">Purchase Plan (EN)</h2>';
                        html += '<div class="plan-block en">' + marked.parse(enText) + '</div>';
                    }
                    planResultBody.html(html || '<div>No plan could be generated.</div>').show();
                };

                // unwrap if backend returned raw JSON string
                var en = res.reply_en || '';
                var th = res.reply_th || '';

                var tryParse = function(text) {
                    try { return JSON.parse(text); } catch (e) { return null; }
                };

                // If reply_en itself is a JSON blob, parse it
                if (!th && en && en.trim().startsWith('{')) {
                    var parsed = tryParse(en);
                    if (parsed && (parsed.reply_en || parsed.reply_th)) {
                        en = parsed.reply_en || en;
                        th = parsed.reply_th || th;
                    }
                }

                // normalize escaped newlines from JSON string literals
                en = en ? en.replace(/\\n/g, '\n') : '';
                th = th ? th.replace(/\\n/g, '\n') : '';

                renderPlan(en, th);
                btnPrintPlan.show();
                if (res.suggested_items && res.suggested_items.length > 0) {
                    sessionStorage.setItem('ai_quotation_items', JSON.stringify(res.suggested_items));
                    btnCreateQuotation.show();
                } else {
                    btnCreateQuotation.hide();
                }
            })
            .fail(function(jqXHR) {
                planLoading.hide();
                btnGeneratePlan.prop('disabled', false).text('Generate Plan');

                var msg = 'Failed to generate plan.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    msg = jqXHR.responseJSON.message;
                }
                planResultBody.html('<div style="color:red;">Error: ' + msg + '</div>').show();
            });
        });

        loadAiSuggestions();
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    .ai-chat-msg p { margin-bottom: 8px; }
    .ai-chat-msg p:last-child { margin-bottom: 0; }
    .ai-chat-msg ul, .ai-chat-msg ol { padding-left: 20px; margin-bottom: 8px; }
    .ai-chat-msg li { margin-bottom: 4px; }
    .ai-chat-msg strong { font-weight: bold; }

    /* Purchase Plan Additions */
    .ai-purchase-button { padding: 4px 10px; background: #6C5CE7; color: white; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; float: right; margin-right: 10px; }
    .ai-purchase-button:hover { background: #5b4cc4; }
    .purchase-plan-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
    .purchase-plan-modal { background: #fff; width: 1200px; max-width: 98%; max-height: 90vh; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    .purchase-plan-header { padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
    .purchase-plan-header h3 { margin: 0; font-size: 18px; color: #1e293b; font-weight: 600; }
    .purchase-plan-header .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
    .purchase-plan-body { padding: 20px; overflow-y: auto; flex: 1; }
    .purchase-plan-controls { display: flex; gap: 15px; margin-bottom: 20px; align-items: flex-end; background: #f1f5f9; padding: 15px; border-radius: 6px; }
    .purchase-plan-controls .input-group { display: flex; flex-direction: column; gap: 5px; }
    .purchase-plan-controls label { font-size: 13px; font-weight: 600; color: #475569; margin: 0; }
    .purchase-plan-controls input { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; background-color: #ffffff; color: #1e293b; }
    .purchase-plan-controls .action-btn { background: #00B894; color: white; border: none; padding: 9px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .purchase-plan-controls .action-btn:hover { background: #00a082; }
    .purchase-plan-controls .action-btn-outline { background: white; color: #475569; border: 1px solid #cbd5e1; padding: 9px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; }
    .purchase-plan-controls .action-btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }
    
    .purchase-plan-result { background: #fff; line-height: 1.6; color: #334155; }
    .purchase-plan-result h1, .purchase-plan-result h2, .purchase-plan-result h3 { color: #1e293b; margin-top: 20px; margin-bottom: 10px; font-weight: 600; }
    .purchase-plan-result h1 { font-size: 22px; }
    .purchase-plan-result h2 { font-size: 18px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
    .purchase-plan-result ul, .purchase-plan-result ol { padding-left: 20px; margin-bottom: 15px; }
    .purchase-plan-result p { margin-bottom: 15px; }
    .purchase-plan-result strong { color: #0f172a; }

    @media print {
        body * { visibility: hidden !important; }
        #purchase_plan_modal, #purchase_plan_modal * { visibility: visible !important; }
        #purchase_plan_modal { position: static !important; display: block !important; width: 100% !important; height: auto !important; background: #fff !important; box-shadow: none !important; }
        #purchase_plan_printable { position: static !important; left: 0; top: 0; width: 100% !important; max-width: none !important; box-shadow: none !important; border-radius: 0 !important; background: #fff !important; }
        .purchase-plan-overlay { position: static !important; width: 100% !important; height: auto !important; background: none !important; display: block !important; }
        .no-print, .no-print * { display: none !important; }
        .purchase-plan-result { padding: 0; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { margin: 15mm; }
    }
</style>
@endsection
