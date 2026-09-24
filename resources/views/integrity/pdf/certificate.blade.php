<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cryptographic Integrity Certificate — #{{ $v->integrity_verification_id }}</title>
    <style>
        @page {
            margin: 25mm 20mm 20mm 20mm;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #1a1a1a;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 14pt;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 1px;
            color: #0f172a;
        }
        .header h2 {
            font-size: 11pt;
            font-weight: normal;
            margin: 3px 0;
            color: #334155;
        }
        .header h3 {
            font-size: 9pt;
            font-weight: normal;
            margin: 0;
            color: #64748b;
        }
        .title-box {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .title-box h2 {
            margin: 0;
            font-size: 13pt;
            letter-spacing: 0.5px;
            color: #0f172a;
        }
        .cert-no {
            font-size: 8pt;
            color: #64748b;
            margin-top: 4px;
            font-family: monospace;
        }
        .outcome-banner {
            margin: 15px 0;
            padding: 12px;
            text-align: center;
            border-radius: 4px;
        }
        .outcome-MATCH {
            background-color: #ecfdf5;
            border: 2px solid #10b981;
            color: #065f46;
        }
        .outcome-MISMATCH {
            background-color: #fef2f2;
            border: 2px solid #ef4444;
            color: #991b1b;
        }
        .outcome-UNVERIFIABLE {
            background-color: #fffbeb;
            border: 2px solid #f59e0b;
            color: #92400e;
        }
        .outcome-title {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
        }
        .outcome-desc {
            font-size: 8.5pt;
            margin-top: 4px;
        }
        table.meta-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 8.5pt;
        }
        table.meta-table th, table.meta-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
        }
        table.meta-table th {
            background-color: #f1f5f9;
            text-align: left;
            width: 28%;
            color: #334155;
        }
        .hash-box {
            font-family: monospace;
            font-size: 7.5pt;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px;
            word-break: break-all;
            margin-top: 2px;
        }
        .signatures {
            margin-top: 40px;
            width: 100%;
        }
        .sig-col {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }
        .sig-line {
            margin-top: 45px;
            border-top: 1px solid #334155;
            padding-top: 5px;
            font-weight: bold;
            font-size: 9pt;
        }
        .sig-title {
            font-size: 8pt;
            color: #64748b;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            text-align: justify;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Republic of the Philippines</h1>
        <h2>{{ $org?->registered_name ?? 'Municipality of Example' }}</h2>
        <h3>Municipal Payroll Integrity & Audit Assurance Layer (FR-6.3, UC-31)</h3>
    </div>

    <div class="title-box">
        <h2>CERTIFICATE OF CRYPTOGRAPHIC INTEGRITY</h2>
        <div class="cert-no">CERTIFICATE REF: CERT-INT-{{ str_pad($v->integrity_verification_id, 6, '0', STR_PAD_LEFT) }} · ISSUED: {{ $v->performed_at->format('Y-m-d H:i:s T') }}</div>
    </div>

    <div class="outcome-banner outcome-{{ $v->result }}">
        <div class="outcome-title">STATUS: {{ $v->result }}</div>
        <div class="outcome-desc">{{ $v->remarks }}</div>
    </div>

    <table class="meta-table">
        <tr>
            <th>Verified Target Scope</th>
            <td>
                <strong>{{ $anchor->scope_type }}</strong>
                @if ($anchor->scope_type === 'RUN' && $anchor->run)
                    — Payroll Run #{{ $anchor->payroll_run_id }} (Period: {{ $anchor->run->period?->payroll_year }}-{{ $anchor->run->period?->period_no }})
                @elseif ($anchor->reversalRecord)
                    — Reversal Record #{{ $anchor->reversal_record_id }} for Run #{{ $anchor->reversalRecord->payroll_run_id }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Chain Position</th>
            <td>#{{ $anchor->chain_position }} (Monotonically Ordered Anchor Ledger)</td>
        </tr>
        <tr>
            <th>External Ledger Platform</th>
            <td>Hyperledger Besu (QBFT Consensus Protocol, 4-Validator Cluster)</td>
        </tr>
        <tr>
            <th>Ledger Transaction Hash</th>
            <td style="font-family: monospace; font-size: 8pt;">{{ $anchor->ledger_tx_ref ?? 'PENDING_TRANSMISSION' }}</td>
        </tr>
        <tr>
            <th>Ledger Block Reference</th>
            <td>#{{ $anchor->ledger_block_ref ?? 'Awaiting Block Inclusion' }}</td>
        </tr>
        <tr>
            <th>Anchor Queued At</th>
            <td>{{ $anchor->queued_at->format('Y-m-d H:i:s') }}</td>
        </tr>
        <tr>
            <th>Anchor Confirmed At</th>
            <td>{{ $anchor->confirmed_at ? $anchor->confirmed_at->format('Y-m-d H:i:s') : 'Unconfirmed / Pending' }}</td>
        </tr>
        <tr>
            <th>Verified By Officer</th>
            <td>{{ $v->performer?->username }} (Role: {{ $v->performer?->role?->role_name ?? 'Auditor' }})</td>
        </tr>
    </table>

    <div style="margin-top: 15px;">
        <strong style="font-size: 8.5pt; color: #334155;">Live Recomputed Payload Hash (Current Database State):</strong>
        <div class="hash-box">{{ $v->recomputed_hash }}</div>
    </div>

    <div style="margin-top: 10px;">
        <strong style="font-size: 8.5pt; color: #334155;">Original Anchored Payload Hash (Finalization Fingerprint):</strong>
        <div class="hash-box">{{ $anchor->payload_hash }}</div>
    </div>

    <div class="signatures">
        <div class="sig-col" style="margin-right: 3%;">
            <div class="sig-line">{{ $v->performer?->username ?? 'Verifying Officer' }}</div>
            <div class="sig-title">Verifying Officer / Internal Audit</div>
            <div class="sig-title">{{ $v->performer?->role?->role_name ?? 'Audit Personnel' }}</div>
        </div>
        <div class="sig-col">
            <div class="sig-line">MUNICIPAL ACCOUNTANT / ADMINISTRATOR</div>
            <div class="sig-title">Municipal Accounting Office</div>
            <div class="sig-title">Attestation of Cryptographic Proof</div>
        </div>
    </div>

    <div class="footer">
        <strong>Guaranteed Audit Standard (AC-6.3.6):</strong> This certificate proves that the stored payroll records remain byte-for-byte identical to the state finalized by the Approver. No personal wages or confidential employee data are contained in the ledger or external proofs. Tampering detection is mathematically provable via SHA-256 canonical hashing.
    </div>
</body>
</html>
