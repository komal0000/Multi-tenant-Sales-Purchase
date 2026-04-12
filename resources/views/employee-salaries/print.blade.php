<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - {{ $salary->employee_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
        .container { max-width: 760px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .title { font-size: 26px; margin: 0; }
        .muted { color: #6b7280; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #d1d5db; padding: 10px 12px; text-align: left; }
        td.amount { text-align: right; font-family: monospace; }
        .total td { font-weight: 700; background: #f3f4f6; }
        .remarks { margin-top: 16px; border: 1px solid #d1d5db; padding: 10px 12px; }
        .actions { margin-top: 16px; }
        @media print {
            .actions { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 class="title">Employee Salary Slip</h1>
                <p class="muted">{{ $salary->party?->name ?? $salary->employee_name }}{{ ($salary->party?->phone ?? $salary->employee_code) ? ' • ' . ($salary->party?->phone ?? $salary->employee_code) : '' }}</p>
                <p class="muted">Month: {{ $salary->salary_month }} | BS {{ $salary->salary_date_bs }} | AD {{ $salary->salary_date->format('d M Y') }}</p>
                <p class="muted">Party: {{ $salary->party?->name ?? '-' }}</p>
            </div>
            <div class="muted">Generated: {{ now()->format('d M Y h:i A') }}</div>
        </div>

        <table>
            <tbody>
                <tr>
                    <td>Basic Salary</td>
                    <td class="amount">{{ number_format((float) $salary->basic_salary, 2) }}</td>
                </tr>
                <tr>
                    <td>Allowance</td>
                    <td class="amount">{{ number_format((float) $salary->allowance, 2) }}</td>
                </tr>
                <tr>
                    <td>Deduction</td>
                    <td class="amount">{{ number_format((float) $salary->deduction, 2) }}</td>
                </tr>
                <tr>
                    <td>Leave Days</td>
                    <td class="amount">{{ number_format((float) $salary->leave_days, 2) }}</td>
                </tr>
                <tr>
                    <td>Overtime Days</td>
                    <td class="amount">{{ number_format((float) $salary->overtime_days, 2) }}</td>
                </tr>
                <tr class="total">
                    <td>Net Salary</td>
                    <td class="amount">{{ number_format((float) $salary->net_salary, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="remarks">
            <strong>Expense Status:</strong>
            <div>
                @if ($salary->expense_payment_id)
                    Posted to {{ $salary->account?->name ?? '-' }} (Payment ID: {{ $salary->expense_payment_id }})
                @else
                    Not posted as expense
                @endif
            </div>
        </div>

        @if ($salary->remarks)
            <div class="remarks">
                <strong>Remarks:</strong>
                <div>{{ $salary->remarks }}</div>
            </div>
        @endif

        <div class="actions">
            <button onclick="window.print()">Print</button>
        </div>
    </div>
</body>
</html>
