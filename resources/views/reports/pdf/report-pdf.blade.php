<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['name'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            color: #1e293b;
            margin: 0;
            padding: 10px;
        }
        .header {
            margin-bottom: 12px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
        }
        .org-name {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            color: #334155;
            margin-top: 2px;
        }
        .provisional-badge {
            display: inline-block;
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
            padding: 3px 8px;
            font-size: 8.5pt;
            font-weight: bold;
            margin-left: 8px;
            border-radius: 3px;
        }
        .meta {
            font-size: 8pt;
            color: #64748b;
            margin-top: 4px;
        }
        .params {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 5px 8px;
            font-size: 8pt;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 15px;
        }
        th, td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
            color: #334155;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .num {
            text-align: right;
            font-family: DejaVu Sans Mono, monospace;
        }
        .total-row td {
            font-weight: bold;
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            background-color: #f8fafc;
        }
        .watermark {
            position: fixed;
            top: 35%;
            left: 20%;
            transform: rotate(-30deg);
            font-size: 70pt;
            color: rgba(220, 38, 38, 0.08);
            font-weight: bold;
            z-index: -1000;
        }
    </style>
</head>
<body>
    @if ($data['is_provisional'])
        <div class="watermark">PROVISIONAL</div>
    @endif

    <div class="header">
        <div class="org-name">{{ $data['org']?->registered_name ?? config('app.name', 'Municipal Payroll System') }}</div>
        <div class="doc-title">
            {{ $meta['name'] }}
            @if ($data['is_provisional'])
                <span class="provisional-badge">PROVISIONAL (AC-5.3.5)</span>
            @endif
        </div>
        <div class="meta">
            Generated: {{ $generatedAt->format('Y-m-d H:i:s') }} | By: {{ $generatedBy->username }} ({{ $generatedBy->role?->role_name }})
        </div>
    </div>

    <div class="params">
        <strong>Parameters:</strong>
        @foreach ($data['parameters'] as $k => $v)
            <span>{{ $k }}: <strong>{{ $v }}</strong></span>@if (! $loop->last) &nbsp;|&nbsp; @endif
        @endforeach
    </div>

    @if ($reportType === 'payroll_register')
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Employee No</th>
                    <th style="width: 24%;">Employee Name</th>
                    <th style="width: 18%;">Department</th>
                    <th style="width: 8%;" class="num">Days</th>
                    <th style="width: 13%;" class="num">Gross Pay</th>
                    <th style="width: 13%;" class="num">Deductions</th>
                    <th style="width: 14%;" class="num">Net Pay</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['lines'] as $line)
                    @php
                        $dept = $line->employee?->currentEmploymentDetail?->department?->department_name ?? '—';
                    @endphp
                    <tr>
                        <td>{{ $line->employee?->employee_no }}</td>
                        <td>{{ $line->employee?->fullName() }}</td>
                        <td>{{ $dept }}</td>
                        <td class="num">{{ number_format((float) $line->days_worked, 2) }}</td>
                        <td class="num">₱{{ number_format((float) $line->gross_pay, 2) }}</td>
                        <td class="num">₱{{ number_format((float) $line->total_deductions, 2) }}</td>
                        <td class="num">₱{{ number_format((float) $line->net_pay, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">TOTALS ({{ $data['totals']['employee_count'] }} employees)</td>
                    <td class="num">{{ number_format((float) $data['totals']['days_worked'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['gross_pay'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['total_deductions'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['net_pay'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @elseif ($reportType === 'payroll_summary')
        <h3 style="font-size: 9pt; margin-bottom: 5px;">Department Summaries</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">Department</th>
                    <th style="width: 15%;" class="num">Headcount</th>
                    <th style="width: 15%;" class="num">Gross Pay</th>
                    <th style="width: 15%;" class="num">Total Deductions</th>
                    <th style="width: 15%;" class="num">Net Pay</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['department_summaries'] as $ds)
                    <tr>
                        <td>{{ $ds['department'] }}</td>
                        <td class="num">{{ $ds['headcount'] }}</td>
                        <td class="num">₱{{ number_format((float) $ds['gross_pay'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $ds['total_deductions'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $ds['net_pay'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>TOTALS</td>
                    <td class="num">{{ $data['totals']['employee_count'] }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['gross_pay'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['total_deductions'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['net_pay'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div style="width: 100%;">
            <div style="float: left; width: 48%;">
                <h4 style="font-size: 8.5pt; margin-bottom: 4px;">Earnings by Category</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['earning_totals'] as $name => $amt)
                            <tr>
                                <td>{{ $name }}</td>
                                <td class="num">₱{{ number_format((float) $amt, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="float: right; width: 48%;">
                <h4 style="font-size: 8.5pt; margin-bottom: 4px;">Deductions by Category</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="num">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['deduction_totals'] as $name => $amt)
                            <tr>
                                <td>{{ $name }}</td>
                                <td class="num">₱{{ number_format((float) $amt, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="clear: both;"></div>
        </div>
    @endif
</body>
</html>
