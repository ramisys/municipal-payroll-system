<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payroll Records Search Export</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
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
        .meta {
            font-size: 8pt;
            color: #64748b;
            margin-top: 4px;
        }
        .criteria {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 10px;
            font-size: 8pt;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        th, td {
            padding: 5px 6px;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="org-name">{{ config('app.name', 'Municipal Payroll System') }}</div>
        <div class="doc-title">Payroll Records Search Export (UC-29)</div>
        <div class="meta">
            Generated: {{ $generatedAt->format('Y-m-d H:i:s') }} | Exported by: {{ $generatedBy->username }} ({{ $generatedBy->role?->role_name }}) | Total records: {{ $lines->count() }}
        </div>
    </div>

    @if (! empty($appliedCriteria))
        <div class="criteria">
            <strong>Criteria applied:</strong> {{ implode(' · ', $appliedCriteria) }}
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Employee No</th>
                <th style="width: 22%;">Employee Name</th>
                <th style="width: 18%;">Department</th>
                <th style="width: 12%;">Period</th>
                <th style="width: 12%;" class="num">Gross Pay</th>
                <th style="width: 12%;" class="num">Deductions</th>
                <th style="width: 12%;" class="num">Net Pay</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totGross = '0.00';
                $totDeductions = '0.00';
                $totNet = '0.00';
            @endphp
            @forelse ($lines as $line)
                @php
                    $emp = $line->employee;
                    $dept = $emp?->currentEmploymentDetail?->department?->department_name ?? '—';
                    $period = $line->run?->period;
                    $totGross = bcadd($totGross, (string) $line->gross_pay, 2);
                    $totDeductions = bcadd($totDeductions, (string) $line->total_deductions, 2);
                    $totNet = bcadd($totNet, (string) $line->net_pay, 2);
                @endphp
                <tr>
                    <td>{{ $emp?->employee_no }}</td>
                    <td>{{ $emp?->fullName() }}</td>
                    <td>{{ $dept }}</td>
                    <td>{{ $period ? "{$period->payroll_year}-{$period->period_no}" : '—' }}</td>
                    <td class="num">₱{{ number_format((float) $line->gross_pay, 2) }}</td>
                    <td class="num">₱{{ number_format((float) $line->total_deductions, 2) }}</td>
                    <td class="num">₱{{ number_format((float) $line->net_pay, 2) }}</td>
                    <td>{{ $line->run?->run_status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 15px;">No records matched the search criteria.</td>
                </tr>
            @endforelse
            @if ($lines->isNotEmpty())
                <tr class="total-row">
                    <td colspan="4">TOTALS ({{ $lines->count() }} records)</td>
                    <td class="num">₱{{ number_format((float) $totGross, 2) }}</td>
                    <td class="num">₱{{ number_format((float) $totDeductions, 2) }}</td>
                    <td class="num">₱{{ number_format((float) $totNet, 2) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
