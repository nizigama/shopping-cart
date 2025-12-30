<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Sales Report</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Daily Sales Report</h1>

    <p style="color: #666;">Report for <strong>{{ $reportDate->format('l, F j, Y') }}</strong></p>

    @if($salesData->isEmpty())
        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 5px; text-align: center; margin: 20px 0;">
            <p style="color: #666; margin: 0;">No products were added to carts today.</p>
        </div>
    @else
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <thead>
                <tr style="background-color: #3498db; color: white;">
                    <th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Product</th>
                    <th style="padding: 12px; text-align: center; border: 1px solid #ddd;">Quantity</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Unit Price</th>
                    <th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($salesData as $item)
                    <tr style="background-color: {{ $loop->even ? '#f8f9fa' : '#ffffff' }};">
                        <td style="padding: 12px; border: 1px solid #ddd;">{{ $item['product']->name }}</td>
                        <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">{{ $item['quantity'] }}</td>
                        <td style="padding: 12px; text-align: right; border: 1px solid #ddd;">${{ number_format($item['product']->price, 2) }}</td>
                        <td style="padding: 12px; text-align: right; border: 1px solid #ddd;">${{ number_format($item['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #2c3e50; color: white; font-weight: bold;">
                    <td colspan="3" style="padding: 12px; text-align: right; border: 1px solid #ddd;">Grand Total:</td>
                    <td style="padding: 12px; text-align: right; border: 1px solid #ddd;">${{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <p style="color: #666;">Total products: <strong>{{ $salesData->count() }}</strong></p>
    @endif

    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

    <p style="color: #666; font-size: 12px;">This is an automated daily report from the Shopping Cart system.</p>
</body>
</html>

