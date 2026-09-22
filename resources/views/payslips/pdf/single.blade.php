<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip — {{ $payslip['employee']['no'] }}</title>
    @include('payslips.pdf._payslip-styles')
</head>
<body>
    <div class="page">
        @include('payslips.pdf._payslip', ['payslip' => $payslip])
    </div>
</body>
</html>