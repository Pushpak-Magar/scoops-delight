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

// Initialize variables
$message = "";
$error = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Update ice cream stock
    if (isset($_POST['update_stock'])) {
        $ice_cream_id = $_POST['ice_cream_id'];
        $stock_quantity = $_POST['stock_quantity'];
        
        // Validate input
        if (empty($ice_cream_id) || empty($stock_quantity) || !is_numeric($stock_quantity)) {
            $error = "Please select an ice cream and enter a valid quantity.";
        } else {
            // Update the stock
            $sql = "UPDATE ice_creams SET stock = stock + ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $stock_quantity, $ice_cream_id);
            
            if ($stmt->execute()) {
                $message = "Stock updated successfully!";
            } else {
                $error = "Error updating stock: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    // Add new ice cream
    if (isset($_POST['add_ice_cream'])) {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $stock = $_POST['initial_stock'];
        $image = $_POST['image_url'];
        
        // Validate input
        if (empty($name) || empty($price) || empty($stock) || empty($image)) {
            $error = "All fields are required to add a new ice cream.";
        } else {
            // Insert new ice cream
            $sql = "INSERT INTO ice_creams (name, price, stock, image) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sdis", $name, $price, $stock, $image);
            
            if ($stmt->execute()) {
                $message = "New ice cream added successfully!";
            } else {
                $error = "Error adding new ice cream: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Fetch ice creams for dropdown
$ice_creams = [];
$result = $conn->query("SELECT id, name, stock FROM ice_creams");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $ice_creams[] = $row;
    }
}

// Fetch recent orders
$orders = [];
$result = $conn->query("SELECT o.id, o.order_date, o.total_amount, c.name as customer_name 
                        FROM orders o
                        JOIN customers c ON o.customer_id = c.id
                        ORDER BY o.order_date DESC LIMIT 10");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Scoops Delight</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
        }
        
        .admin-container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .admin-header {
            background-color: #ff6b6b;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        
        .admin-content {
            background-color: white;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        h2 {
            color: #ff6b6b;
            margin-bottom: 15px;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        select, input[type="number"], input[type="text"], input[type="submit"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        input[type="submit"] {
            background-color: #ff6b6b;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        input[type="submit"]:hover {
            background-color: #ff5252;
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
        
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .inventory-item {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .inventory-item h3 {
            color: #ff6b6b;
            margin-bottom: 10px;
        }
        
        .stock-info {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .low-stock {
            color: #dc3545;
        }

        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #ff6b6b;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .back-button:hover {
            background-color: #ff5252;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Scoops Delight Admin Panel</h1>
        </div>
        
        <div class="admin-content">
            <?php if ($message): ?>
                <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="section">
                <h2>Update Inventory</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="ice_cream_id">Select Ice Cream:</label>
                        <select name="ice_cream_id" id="ice_cream_id" required>
                            <option value="">-- Select Ice Cream --</option>
                            <?php foreach ($ice_creams as $ice_cream): ?>
                                <option value="<?php echo $ice_cream['id']; ?>">
                                    <?php echo $ice_cream['name']; ?> (Current Stock: <?php echo $ice_cream['stock']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_quantity">Quantity to Add:</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" name="update_stock" value="Update Stock">
                    </div>
                </form>
            </div>
            
            <div class="section">
                <h2>Add New Ice Cream</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="name">Ice Cream Name:</label>
                        <input type="text" name="name" id="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Price (₹):</label>
                        <input type="number" name="price" id="price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="initial_stock">Initial Stock:</label>
                        <input type="number" name="initial_stock" id="initial_stock" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="image_url">Image URL:</label>
                        <input type="text" name="image_url" id="image_url" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" name="add_ice_cream" value="Add Ice Cream">
                    </div>
                </form>
            </div>
            
            <div class="section">
                <h2>Current Inventory</h2>
                <div class="inventory-grid">
                    <?php foreach ($ice_creams as $ice_cream): ?>
                        <div class="inventory-item">
                            <h3><?php echo $ice_cream['name']; ?></h3>
                            <div class="stock-info <?php echo $ice_cream['stock'] < 10 ? 'low-stock' : ''; ?>">
                                Stock: <?php echo $ice_cream['stock']; ?> 
                                <?php if ($ice_cream['stock'] < 10): ?>
                                    (Low stock!)
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="section">
                <h2>Recent Orders</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo $order['customer_name']; ?></td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></td>
                                    <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" 
                                           style="color: #ff6b6b; text-decoration: none;">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <!-- Back Button to Main Order Page -->
               <center> <a href="order.php" class="back-button">← Back to Counter</a></center>
            </div>
        </div>
    </div>
</body>
</html>