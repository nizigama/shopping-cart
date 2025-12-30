<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Low Stock Alert</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #e74c3c;">Low Stock Alert</h1>

    <p>This is an automated notification to inform you that the following product has low stock:</p>

    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>Product:</strong> {{ $product->name }}</p>
        <p><strong>Current Stock:</strong> {{ $product->stock?->quantity ?? 0 }} units</p>
    </div>

    <p style="color: #e74c3c;"><strong>Action Required:</strong> Please consider restocking this product soon.</p>

    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

    <p style="color: #666; font-size: 12px;">This is an automated message from the Shopping Cart system.</p>
</body>
</html>

