<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
    * { box-sizing: border-box; }

    body {
        font-family: thmanyahsans;
        direction: rtl;
        font-size: 11px;
        color: #1b1b1b;
        margin: 0;
        padding: 24px 28px;
    }

    header {
        display: table;
        width: 100%;
        border-bottom: 2px solid #2c4a6e;
        padding-bottom: 10px;
        margin-bottom: 16px;
    }
    header .brand {
        display: table-cell;
        font-size: 20px;
        font-weight: 700;
        color: #2c4a6e;
        vertical-align: bottom;
    }
    header .meta {
        display: table-cell;
        text-align: left;
        vertical-align: bottom;
        font-size: 11px;
        color: #6b6b6b;
    }

    h1 {
        font-size: 15px;
        margin: 0 0 2px;
    }
    .range {
        font-size: 11px;
        color: #6b6b6b;
        margin: 0 0 16px;
    }

    section { margin-bottom: 18px; }
    h2 {
        font-size: 12.5px;
        font-weight: 700;
        background: #f5f4f0;
        border-radius: 6px;
        padding: 5px 10px;
        margin: 0 0 8px;
    }

    .cards {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-spacing: 6px 0;
    }
    .card {
        display: table-cell;
        border: 1px solid #e2e0d8;
        border-radius: 8px;
        padding: 8px 10px;
    }
    .card .label { font-size: 10px; color: #6b6b6b; margin: 0 0 4px; }
    .card .value { font-size: 13px; font-weight: 700; }
    .value.positive { color: #1a7f4b; }
    .value.negative { color: #b3261e; }

    table { width: 100%; border-collapse: collapse; }
    th, td {
        text-align: right;
        padding: 5px 8px;
        border-bottom: 1px solid #ececec;
        font-size: 10.5px;
    }
    th { color: #6b6b6b; font-weight: 700; }
    td.amount, th.amount { text-align: left; font-variant-numeric: tabular-nums; }
    tr.total td { font-weight: 700; border-top: 1px solid #cfcabb; border-bottom: none; }

    .decision {
        border: 1px solid #e2e0d8;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 11px;
    }

    .bar-track {
        background: #eeece4;
        border-radius: 4px;
        height: 6px;
        width: 100px;
    }
    .bar-fill {
        background: #2c4a6e;
        border-radius: 4px;
        height: 6px;
    }

</style>
</head>
<body>

<header>
    <div class="brand">موفّر</div>
    <div class="meta">تقرير وين راحت فلوسك · صدر بتاريخ {{ $generatedAt }}</div>
</header>

<h1>{{ $period['label'] }}</h1>
<p class="range">{{ $period['range'] }}</p>

<section>
    <h2>ملخّص الفترة</h2>
    <div class="cards">
        <div class="card">
            <p class="label">الدخل</p>
            <p class="value">{{ \App\Services\ReportPdfService::money($summary['income']) }}</p>
        </div>
        <div class="card">
            <p class="label">المصاريف</p>
            <p class="value">{{ \App\Services\ReportPdfService::money($summary['expenses']) }}</p>
        </div>
        <div class="card">
            <p class="label">الادخار</p>
            <p class="value">{{ \App\Services\ReportPdfService::money($summary['savings']) }}</p>
        </div>
        <div class="card">
            <p class="label">{{ $summary['surplus'] >= 0 ? 'الفائض' : 'العجز' }}</p>
            <p class="value {{ $summary['surplus'] >= 0 ? 'positive' : 'negative' }}">{{ \App\Services\ReportPdfService::money(abs($summary['surplus'])) }}</p>
        </div>
    </div>
</section>

<section>
    <h2>قرارك في الفائض/العجز</h2>
    <p class="decision">{{ $decision }}</p>
</section>

<section>
    <h2>مقارنة بالشهر السابق ({{ $previousSummary['label'] }})</h2>
    <table>
        <thead>
            <tr><th></th><th class="amount">{{ $period['label'] }}</th><th class="amount">{{ $previousSummary['label'] }}</th></tr>
        </thead>
        <tbody>
            <tr><td>الدخل</td><td class="amount">{{ \App\Services\ReportPdfService::money($summary['income']) }}</td><td class="amount">{{ \App\Services\ReportPdfService::money($previousSummary['income']) }}</td></tr>
            <tr><td>المصاريف</td><td class="amount">{{ \App\Services\ReportPdfService::money($summary['expenses']) }}</td><td class="amount">{{ \App\Services\ReportPdfService::money($previousSummary['expenses']) }}</td></tr>
            <tr><td>الادخار</td><td class="amount">{{ \App\Services\ReportPdfService::money($summary['savings']) }}</td><td class="amount">{{ \App\Services\ReportPdfService::money($previousSummary['savings']) }}</td></tr>
            <tr><td>الفائض/العجز</td><td class="amount">{{ \App\Services\ReportPdfService::money($summary['surplus']) }}</td><td class="amount">{{ \App\Services\ReportPdfService::money($previousSummary['surplus']) }}</td></tr>
        </tbody>
    </table>
</section>

<section>
    <h2>الالتزامات — مدفوع {{ \App\Services\ReportPdfService::money($commitments['paid']) }} · محجوز {{ \App\Services\ReportPdfService::money($commitments['reserved']) }}</h2>
    @if (count($commitments['rows']) > 0)
        <table>
            <thead>
                <tr><th>الالتزام</th><th>الاستحقاق</th><th>الحالة</th><th class="amount">المبلغ</th></tr>
            </thead>
            <tbody>
                @foreach ($commitments['rows'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['due_date'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td class="amount">{{ \App\Services\ReportPdfService::money($row['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>لا التزامات في هذه الفترة.</p>
    @endif
</section>

<section>
    <h2>المصاريف حسب الفئة</h2>
    @if (count($categories) > 0)
        <table>
            <thead>
                <tr><th>الفئة</th><th>النسبة</th><th class="amount">المبلغ</th></tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category['name'] }}</td>
                        <td>
                            <div class="bar-track"><div class="bar-fill" style="width: {{ min(100, $category['percentage']) }}px"></div></div>
                            {{ $category['percentage'] }}٪
                        </td>
                        <td class="amount">{{ \App\Services\ReportPdfService::money($category['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>لا مصاريف مسجَّلة في هذه الفترة.</p>
    @endif
</section>

<section>
    <h2>قائمة المعاملات</h2>
    @if (count($transactions) > 0)
        <table>
            <thead>
                <tr><th>التاريخ</th><th>البيان</th><th class="amount">المبلغ</th></tr>
            </thead>
            <tbody>
                @foreach ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction['date'] }}</td>
                        <td>{{ $transaction['label'] }}</td>
                        <td class="amount {{ $transaction['amount'] < 0 ? 'negative' : 'positive' }}">{{ \App\Services\ReportPdfService::money($transaction['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>لا معاملات مسجَّلة في هذه الفترة.</p>
    @endif
</section>

</body>
</html>
