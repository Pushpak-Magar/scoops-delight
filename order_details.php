<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "scoops_delight";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get order ID from URL
if (isset($_GET['id'])) {
    $order_id = $_GET['id'];
} else {
    die("Order ID not specified.");
}

// Fetch order details
$order = [];
$result = $conn->query("SELECT o.id, o.order_date, o.total_amount, c.name as customer_name 
                        FROM orders o
                        JOIN customers c ON o.customer_id = c.id
                        WHERE o.id = $order_id");
if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();
} else {
    die("Order not found.");
}

// Fetch order items
$order_items = [];
$result = $conn->query("SELECT i.name, oi.quantity, oi.price 
                        FROM order_items oi
                        JOIN ice_creams i ON oi.ice_cream_id = i.id
                        WHERE oi.order_id = $order_id");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $order_items[] = $row;
    }
}

// Calculate subtotal from order items
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['quantity'] * $item['price'];
}

// Fetch additional charges (e.g., CGST, SGST, Discount)
$additional_charges = [];
$result = $conn->query("SELECT description, amount FROM order_charges WHERE order_id = $order_id");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $additional_charges[] = $row;
    }
}

// Calculate total amount including additional charges
$total_amount = $subtotal;
foreach ($additional_charges as $charge) {
    $total_amount += $charge['amount'];
}

// Check if CGST and SGST already exist in order_charges
$cgst_exists = false;
$sgst_exists = false;
$discount_exists = false;

foreach ($additional_charges as $charge) {
    if ($charge['description'] == 'CGST') {
        $cgst_exists = true;
    } elseif ($charge['description'] == 'SGST') {
        $sgst_exists = true;
    } elseif ($charge['description'] == 'Discount') {
        $discount_exists = true;
    }
}

// Insert CGST and SGST only if they don't already exist
if (!$cgst_exists) {
    $cgst_rate = 0.09; // 9%
    $cgst_amount = $subtotal * $cgst_rate;
    $conn->query("INSERT INTO order_charges (order_id, description, amount) VALUES ($order_id, 'CGST', $cgst_amount)");
    $additional_charges[] = ['description' => 'CGST', 'amount' => $cgst_amount]; // Add to array for display
    $total_amount += $cgst_amount; // Update total amount
}

if (!$sgst_exists) {
    $sgst_rate = 0.09; // 9%
    $sgst_amount = $subtotal * $sgst_rate;
    $conn->query("INSERT INTO order_charges (order_id, description, amount) VALUES ($order_id, 'SGST', $sgst_amount)");
    $additional_charges[] = ['description' => 'SGST', 'amount' => $sgst_amount]; // Add to array for display
    $total_amount += $sgst_amount; // Update total amount
}

if (!$discount_exists) {
    $discount_amount = 50; // Example: ₹50 discount
    $conn->query("INSERT INTO order_charges (order_id, description, amount) VALUES ($order_id, 'Discount', -$discount_amount)");
    $additional_charges[] = ['description' => 'Discount', 'amount' => -$discount_amount]; // Add to array for display
    $total_amount -= $discount_amount; // Update total amount
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f5f5f5;
            padding: 20px;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #ff6b6b;
            margin-bottom: 20px;
            text-align: center;
        }

        p {
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }

        p strong {
            color: #555;
        }

        h2 {
            color: #ff6b6b;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #ff6b6b;
            color: white;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .total-row td {
            color: #ff6b6b;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #ff6b6b;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <h1>Invoice for Order #<?php echo $order['id']; ?></h1>
        <p><strong>Customer:</strong> <?php echo $order['customer_name']; ?></p>
        <p><strong>Order Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></p>
        <p><strong>Total Amount:</strong> ₹<?php echo number_format($total_amount, 2); ?></p>
        
        <h2>Ordered Items</h2>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td><?php echo $item['name']; ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>₹<?php echo number_format($item['price'], 2); ?></td>
                        <td>₹<?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                    <td>₹<?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <?php if (!empty($additional_charges)): ?>
                    <?php foreach ($additional_charges as $charge): ?>
                        <tr>
                            <td colspan="3" style="text-align: right;"><strong><?php echo $charge['description']; ?>:</strong></td>
                            <td>₹<?php echo number_format($charge['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;"><strong>Grand Total:</strong></td>
                    <td>₹<?php echo number_format($total_amount, 2); ?></td>
                </tr>
            </tbody>
        </table>
        <a href="admin.php" class="back-link">← Back to Admin Panel</a>
    </div>
</body>
</html>