<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['name'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
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
            font-size: 7.5pt;
            color: #64748b;
            margin-top: 4px;
        }
        .params {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 5px 8px;
            font-size: 7.5pt;
            margin-bottom: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
            margin-bottom: 15px;
        }
        th, td {
            padding: 4px 5px;
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
            font-size: 65pt;
            color: rgba(220, 38, 38, 0.08);
            font-weight: bold;
            z-index: -1000;
        }
        .signatories {
            margin-top: 25px;
            width: 100%;
        }
        .sig-box {
            display: inline-block;
            width: 30%;
            font-size: 8pt;
        }
        .sig-line {
            border-top: 1px solid #0f172a;
            margin-top: 35px;
            padding-top: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    @if (! empty($data['is_provisional']))
        <div class="watermark">PROVISIONAL</div>
    @endif

    <div class="header">
        <div class="org-name">{{ $data['org']?->registered_name ?? config('app.name', 'Municipal Payroll System') }}</div>
        <div class="doc-title">
            {{ $meta['name'] }}
            @if (! empty($data['is_provisional']))
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
        @if (! empty($data['employer_agency_id']))
            &nbsp;|&nbsp; <span>Employer ID: <strong>{{ $data['employer_agency_id'] }}</strong></span>
        @endif
    </div>

    {{-- 1. Payroll Register --}}
    @if ($reportType === 'payroll_register')
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Employee No</th>
                    <th style="width: 25%;">Employee Name</th>
                    <th style="width: 20%;">Department</th>
                    <th style="width: 7%;" class="num">Days</th>
                    <th style="width: 12%;" class="num">Gross Pay</th>
                    <th style="width: 12%;" class="num">Deductions</th>
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

    {{-- 2. Payroll Summary --}}
    @elseif ($reportType === 'payroll_summary')
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

    {{-- 3, 4, 5. Statutory Remittance Reports --}}
    @elseif (in_array($reportType, ['sss_remittance', 'philhealth_remittance', 'pagibig_remittance'], true))
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Employee No</th>
                    <th style="width: 22%;">Employee Name</th>
                    <th style="width: 16%;">{{ $reportType === 'sss_remittance' ? 'SSS No' : ($reportType === 'philhealth_remittance' ? 'PhilHealth No' : 'Pag-IBIG MID') }}</th>
                    <th style="width: 16%;">Department</th>
                    <th style="width: 12%;" class="num">EE Share (Imp)</th>
                    <th style="width: 12%;" class="num">ER Share</th>
                    <th style="width: 12%;" class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['employee_no'] }}</td>
                        <td>{{ $r['employee_name'] }}</td>
                        <td>{{ $r['id_number'] }}</td>
                        <td>{{ $r['department'] }}</td>
                        <td class="num">₱{{ number_format((float) $r['employee_share'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['employer_share'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['total_contribution'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">TOTALS ({{ $data['totals']['employee_count'] }} covered)</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['employee_share'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['employer_share'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['total_contribution'], 2) }}</td>
                </tr>
            </tbody>
        </table>

    {{-- 6. Withholding Tax --}}
    @elseif ($reportType === 'withholding_tax')
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Employee No</th>
                    <th style="width: 28%;">Employee Name</th>
                    <th style="width: 18%;">TIN</th>
                    <th style="width: 18%;">Department</th>
                    <th style="width: 12%;" class="num">Taxable Comp</th>
                    <th style="width: 12%;" class="num">Tax Withheld</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['employee_no'] }}</td>
                        <td>{{ $r['employee_name'] }}</td>
                        <td>{{ $r['tin_no'] }}</td>
                        <td>{{ $r['department'] }}</td>
                        <td class="num">₱{{ number_format((float) $r['taxable_compensation'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['tax_withheld'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">TOTALS</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['taxable_compensation'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['tax_withheld'], 2) }}</td>
                </tr>
            </tbody>
        </table>

    {{-- 7. Bank Transmittal --}}
    @elseif ($reportType === 'bank_transmittal')
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Employee No</th>
                    <th style="width: 30%;">Account Name</th>
                    <th style="width: 20%;">Bank Name</th>
                    <th style="width: 20%;">Account Number</th>
                    <th style="width: 15%;" class="num">Net Pay</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['employee_no'] }}</td>
                        <td>{{ $r['employee_name'] }}</td>
                        <td>{{ $r['bank_name'] }}</td>
                        <td>{{ $r['bank_account_no'] }}</td>
                        <td class="num">₱{{ number_format((float) $r['net_pay'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">TOTAL TRANSMITTAL</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['net_pay'], 2) }}</td>
                </tr>
            </tbody>
        </table>

    {{-- 8. 13th Month Pay --}}
    @elseif ($reportType === 'thirteenth_month')
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Employee No</th>
                    <th style="width: 35%;">Employee Name</th>
                    <th style="width: 20%;">Department</th>
                    <th style="width: 15%;" class="num">Annual Basic Salary</th>
                    <th style="width: 15%;" class="num">Imported 13th Month</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['employee_no'] }}</td>
                        <td>{{ $r['employee_name'] }}</td>
                        <td>{{ $r['department'] }}</td>
                        <td class="num">₱{{ number_format((float) $r['basic_salary_earned'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['imported_13th_month'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">TOTALS</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['basic_salary_earned'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['imported_13th_month'], 2) }}</td>
                </tr>
            </tbody>
        </table>

    {{-- 9. Leave Ledger --}}
    @elseif ($reportType === 'leave_ledger')
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Employee No</th>
                    <th style="width: 35%;">Employee Name</th>
                    <th style="width: 20%;">Leave Type</th>
                    <th style="width: 10%;" class="num">Earned</th>
                    <th style="width: 10%;" class="num">Used</th>
                    <th style="width: 10%;" class="num">Remaining</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['employee_no'] }}</td>
                        <td>{{ $r['employee_name'] }}</td>
                        <td>{{ $r['leave_type'] }}</td>
                        <td class="num">{{ $r['credits_earned'] }}</td>
                        <td class="num">{{ $r['credits_used'] }}</td>
                        <td class="num">{{ $r['balance_remaining'] }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">TOTALS</td>
                    <td class="num">{{ $data['totals']['credits_earned'] }}</td>
                    <td class="num">{{ $data['totals']['credits_used'] }}</td>
                    <td class="num">{{ $data['totals']['balance_remaining'] }}</td>
                </tr>
            </tbody>
        </table>

    {{-- 10. Loan Ledger --}}
    @elseif ($reportType === 'loan_ledger')
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Employee No</th>
                    <th style="width: 25%;">Employee Name</th>
                    <th style="width: 18%;">Loan Type</th>
                    <th style="width: 15%;">Reference</th>
                    <th style="width: 10%;" class="num">Principal</th>
                    <th style="width: 10%;" class="num">Amortization</th>
                    <th style="width: 10%;" class="num">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['employee_no'] }}</td>
                        <td>{{ $r['employee_name'] }}</td>
                        <td>{{ $r['loan_type'] }}</td>
                        <td>{{ $r['loan_reference'] }}</td>
                        <td class="num">₱{{ number_format((float) $r['principal'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['amortization'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['outstanding_balance'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="4">TOTALS</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['principal'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['amortization'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['outstanding_balance'], 2) }}</td>
                </tr>
            </tbody>
        </table>

    {{-- 11. Cost Comparison --}}
    @elseif ($reportType === 'cost_comparison')
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Department</th>
                    <th style="width: 10%;" class="num">Cur Head</th>
                    <th style="width: 10%;" class="num">Prior Head</th>
                    <th style="width: 15%;" class="num">Cur Gross</th>
                    <th style="width: 15%;" class="num">Prior Gross</th>
                    <th style="width: 10%;" class="num">Variance</th>
                    <th style="width: 10%;" class="num">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['department'] }}</td>
                        <td class="num">{{ $r['current_headcount'] }}</td>
                        <td class="num">{{ $r['prior_headcount'] }}</td>
                        <td class="num">₱{{ number_format((float) $r['current_gross'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['prior_gross'], 2) }}</td>
                        <td class="num">₱{{ number_format((float) $r['gross_variance'], 2) }}</td>
                        <td class="num">{{ $r['gross_variance_pct'] }}%</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td>TOTALS</td>
                    <td colspan="2"></td>
                    <td class="num">₱{{ number_format((float) $data['totals']['current_gross'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['prior_gross'], 2) }}</td>
                    <td class="num">₱{{ number_format((float) $data['totals']['gross_variance'], 2) }}</td>
                    <td class="num">{{ $data['totals']['gross_variance_pct'] }}%</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Signatures Block --}}
    <table class="signatories">
        <tr>
            <td style="border: none; width: 33%;">
                <div class="sig-line">Prepared by: {{ $generatedBy->username }}</div>
            </td>
            <td style="border: none; width: 33%;">
                <div class="sig-line">Reviewed by: Municipal Accountant</div>
            </td>
            <td style="border: none; width: 33%;">
                <div class="sig-line">Approved by: Municipal Mayor</div>
            </td>
        </tr>
    </table>
</body>
</html>
