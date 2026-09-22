<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslips — Batch</title>
    @include('payslips.pdf._payslip-styles')
</head>
<body>
    @foreach ($payslips as $payslip)
        <div class="page">
            @include('payslips.pdf._payslip', ['payslip' => $payslip])
        </div>
    @endforeach
</body>
</html>