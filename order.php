<?php
// Database connection
$conn = mysqli_connect("localhost", "root", "", "scoops_delight");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
}
$cart = $_SESSION['cart'];

// Add to cart functionality
if (isset($_POST['add_to_cart'])) {
    $ice_cream_id = $_POST['ice_cream_id'];
    $price = $_POST['price'];
    $name = $_POST['name'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    // Check if item already exists in cart
    if (isset($cart[$ice_cream_id])) {
        $cart[$ice_cream_id]['quantity'] += $quantity;
    } else {
        $cart[$ice_cream_id] = array(
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity
        );
    }
    
    $_SESSION['cart'] = $cart;
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Remove from cart functionality
if (isset($_POST['remove_from_cart'])) {
    $ice_cream_id = $_POST['ice_cream_id'];
    if (isset($cart[$ice_cream_id])) {
        unset($cart[$ice_cream_id]);
        $_SESSION['cart'] = $cart;
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Update cart quantity
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $id => $qty) {
        if ($qty > 0 && isset($cart[$id])) {
            $cart[$id]['quantity'] = (int)$qty;
        }
    }
    $_SESSION['cart'] = $cart;
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Get ice creams from database
$query = "SELECT * FROM ice_creams WHERE stock > 0";
$result = mysqli_query($conn, $query);

// Process order
if (isset($_POST['place_order'])) {
    $name = $_POST['customer_name'];
    $email = $_POST['customer_email'];
    $phone = $_POST['customer_phone'];
    
    // Insert customer details
    $name = mysqli_real_escape_string($conn, $name);
    $email = mysqli_real_escape_string($conn, $email);
    $phone = mysqli_real_escape_string($conn, $phone);
    
    $customer_query = "INSERT INTO customers (name, email, phone) VALUES ('$name', '$email', '$phone')";
    mysqli_query($conn, $customer_query);
    $customer_id = mysqli_insert_id($conn);
    
    // Calculate totals
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $cgst = $subtotal * 0.09;
    $sgst = $subtotal * 0.09;
    $total = $subtotal + $cgst + $sgst;
    
    // Insert order
    $order_query = "INSERT INTO orders (customer_id, order_date, subtotal, cgst, sgst, total) 
                    VALUES ($customer_id, NOW(), $subtotal, $cgst, $sgst, $total)";
    mysqli_query($conn, $order_query);
    $order_id = mysqli_insert_id($conn);
    
    // Insert order items
    foreach ($cart as $id => $item) {
        $item_query = "INSERT INTO order_items (order_id, ice_cream_id, quantity, price) 
                       VALUES ($order_id, $id, {$item['quantity']}, {$item['price']})";
        mysqli_query($conn, $item_query);
        
        // Update stock
        $update_stock = "UPDATE ice_creams SET stock = stock - {$item['quantity']} WHERE id = $id";
        mysqli_query($conn, $update_stock);
    }
    
    // Clear cart
    $_SESSION['cart'] = array();
    $cart = array();
    
    // Show invoice
    $show_invoice = true;
    $invoice_id = $order_id;
}

// Calculate cart totals
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$cgst = $subtotal * 0.09;
$sgst = $subtotal * 0.09;
$total = $subtotal + $cgst + $sgst;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scoops Delight</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet"  href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=account_circle" />
    <style>
        /* Existing CSS styles */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f8f8;
        }
        header {
            background-color: #ff6b6b;
            color: white;
            text-align: center;
            padding: 1rem;
        }
        .hero-section {
            position: relative;
            height: 300px;
            overflow: hidden;
        }
        .slider {
            display: flex;
            width: 300%;
            animation: slide 30s infinite;
        }
        .slide {
            width: 100%;
            height: 400px;
            background-size: cover;
            background-position: center;
        }
        @keyframes slide {
            0% { transform: translateX(0); }
            33% { transform: translateX(-33.33%); }
            66% { transform: translateX(-66.66%); }
            100% { transform: translateX(0); }
        }
        .ice-cream-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
            background-color: #ffd8d8;
        }
        .ice-cream-item {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            padding: 15px;
            transition: transform 0.3s ease;
        }
        .ice-cream-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .ice-cream-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
        }
        .quantity-selector {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 10px 0;
        }
        .quantity-selector button {
            background-color: #ff6b6b;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 4px;
        }
        .quantity-selector input {
            width: 40px;
            height: 30px;
            text-align: center;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .cart-btn {
            display: block;
            margin: 10px auto;
            padding: 10px 20px;
            background-color: #ff6b6b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .cart-btn:hover {
            background-color: #ff5252;
        }
        .cart-section {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cart-table th, .cart-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .cart-table th {
            background-color: #f2f2f2;
        }
        .customer-form {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .form-row {
            display: flex;
            margin-bottom: 15px;
        }
        .form-row input {
            flex: 1;
            margin-right: 10px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .invoice {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .thank-you {
            text-align: center;
            padding: 20px;
            background-color: #e0f7fa;
            margin-top: 20px;
            border-radius: 8px;
        }
        .cart-icon {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #ff6b6b;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 100;
        }
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #333;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
        }
        .nav-tabs {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 20px auto;
            max-width: 800px;
            border-bottom: 1px solid #ddd;
        }
        .nav-tabs li {
            margin-right: 10px;
        }
        .nav-tabs a {
            display: block;
            padding: 10px 20px;
            text-decoration: none;
            color: #333;
            background-color: #f2f2f2;
            border-radius: 5px 5px 0 0;
        }
        .nav-tabs a.active {
            background-color: #ff6b6b;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .remove-btn {
            background-color: #ff3333;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        /* Footer Styles */
        footer {
            background-color: #ff6b6b;
            color: white;
            padding: 40px 20px;
            margin-top: 40px;
            text-align: center;
        }
        footer .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        footer .footer-section {
            flex: 1;
            margin: 10px;
            min-width: 200px;
        }
        footer h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }
        footer p {
            font-size: 14px;
            margin: 5px 0;
        }
        footer a {
            color: white;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        footer a:hover {
            color: #ffd8d8;
        }
        footer .social-icons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 10px;
        }
        footer .social-icons a {
            font-size: 20px;
            color: white;
            transition: color 0.3s ease;
        }
        footer .social-icons a:hover {
            color: #ffd8d8;
        }
        footer .footer-bottom {
            margin-top: 20px;
            border-top: 1px solid #ff5252;
            padding-top: 10px;
            font-size: 12px;
        }
          .material-symbols-outlined {
  font-variation-settings:
  'FILL' 0,
  'wght' 400,
  'GRAD' 0,
  'opsz' 24
}
    </style>
</head>
<body>
    <header>
        <h1>Scoops Delight</h1>
        <p>Delicious Ice Creams For My Sweet Tooth Friends!</p>
    </header>
    
    <div class="hero-section">
        <div class="slider">
            <div class="slide" style="background-image: url('https://png.pngtree.com/background/20230517/original/pngtree-four-different-colored-cones-of-ice-cream-on-top-of-black-picture-image_2640095.jpg')"></div>
            <div class="slide" style="background-image: url('https://media.istockphoto.com/id/1335564975/photo/assorted-of-ice-cream-scoops-on-white-background-colorful-set-of-ice-cream-scoops-of.jpg?s=612x612&w=0&k=20&c=unE5EeR2uszlKyZ7OCCiNxmclnHt8tLq-Hh5SU_xUKk=')"></div>
            <div class="slide" style="background-image: url('https://media.istockphoto.com/id/936205772/photo/chocolate-ice-cream-in-a-glass-cup.jpg?s=612x612&w=0&k=20&c=xBDPxGzIgWcE8tFZ4azKm1P_OoxP8H22XkyHguZlVhw=')"></div>
            <div class="slide" style="background-image: url('https://png.pngtree.com/thumb_back/fh260/background/20230527/pngtree-the-best-ice-cream-flavors-for-men-image_2688221.jpg')"></div>
        </div>
    </div>
    <br>
    <ul class="nav-tabs">
        <li><a href="#flavors" class="tab-link active" data-tab="flavors-tab">Our Flavors</a></li>
        <li><a href="#cart" class="tab-link" data-tab="cart-tab">Your Cart (<?php echo array_sum(array_column($cart, 'quantity')); ?>)</a></li>
        <?php if (count($cart) > 0) { ?>
        <li><a href="#checkout" class="tab-link" data-tab="checkout-tab">Checkout</a></li>
        <?php } ?>
    </ul>
    
    <div id="flavors-tab" class="tab-content active">
        <h2 style="text-align: center; margin: 20px 0;">Our Delicious Flavors</h2>
        
        <div class="ice-cream-grid">
            <?php 
            // Reset the result pointer
            mysqli_data_seek($result, 0);
            while ($row = mysqli_fetch_assoc($result)) { 
            ?>
                <div class="ice-cream-item">
                <img src="<?php echo $row['image']; ?>" alt="<?php echo $row['name']; ?>">
                    <h3><?php echo $row['name']; ?></h3>
                    <p>₹<?php echo $row['price']; ?></p>
                    
                    <form method="post" class="add-to-cart-form">
                        <div class="quantity-selector">
                            <button type="button" class="decrease-qty">-</button>
                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $row['stock']; ?>">
                            <button type="button" class="increase-qty">+</button>
                        </div>
                        <input type="hidden" name="ice_cream_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="price" value="<?php echo $row['price']; ?>">
                        <input type="hidden" name="name" value="<?php echo $row['name']; ?>">
                        <button type="submit" name="add_to_cart" class="cart-btn">Add to Cart</button>
                        </form>
                </div>
            <?php } ?>
        </div>
    </div>
    
    <div id="cart-tab" class="tab-content">
        <div class="cart-section">
            <h2>Your Cart</h2>
            <?php if (count($cart) > 0) { ?>
                <form method="post">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $id => $item) { ?>
                                <tr>
                                    <td><?php echo $item['name']; ?></td>
                                    <td>₹<?php echo $item['price']; ?></td>
                                    <td>
                                        <div class="quantity-selector">
                                            <button type="button" class="decrease-qty">-</button>
                                            <input type="number" name="quantities[<?php echo $id; ?>]" value="<?php echo $item['quantity']; ?>" min="1">
                                            <button type="button" class="increase-qty">+</button>
                                        </div>
                                    </td>
                                    <td>₹<?php echo $item['price'] * $item['quantity']; ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="ice_cream_id" value="<?php echo $id; ?>">
                                            <button type="submit" name="remove_from_cart" class="remove-btn">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                                <td>₹<?php echo number_format($subtotal, 2); ?></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>CGST (9%):</strong></td>
                                <td>₹<?php echo number_format($cgst, 2); ?></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>SGST (9%):</strong></td>
                                <td>₹<?php echo number_format($sgst, 2); ?></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                                <td>₹<?php echo number_format($total, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" name="update_cart" class="cart-btn">Update Cart</button>
                        <center><a href="#checkout" class="cart-btn tab-link" data-tab="checkout-tab">Proceed to Checkout</a></center>
                    </div>
                </form>
            <?php } else { ?>
                <p>Your cart is empty. Please add some items to continue.</p>
                <a href="#flavors" class="cart-btn tab-link" data-tab="flavors-tab">Browse Flavors</a>
            <?php } ?>
        </div>
    </div>
    
    <div id="checkout-tab" class="tab-content">
        <?php if (count($cart) > 0) { ?>
            <div class="customer-form">
                <h2>Checkout Information</h2>
                <form method="post">
                    <div class="form-row">
                        <input type="text" name="customer_name" placeholder="Your Name" required>
                    </div>
                    <div class="form-row">
                        <input type="email" name="customer_email" placeholder="Your Email" required>
                    </div>
                    <div class="form-row">
                        <input type="tel" name="customer_phone" placeholder="Your Phone Number" required>
                    </div>
                    <div class="form-row">
                        <input type="text" name="customer_address" placeholder=" Address" required>
                    </div>
                    
                    <h3>Order Summary</h3>
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $id => $item) { ?>
                                <tr>
                                    <td><?php echo $item['name']; ?></td>
                                    <td>₹<?php echo $item['price']; ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>₹<?php echo $item['price'] * $item['quantity']; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                                <td>₹<?php echo number_format($subtotal, 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>CGST (9%):</strong></td>
                                <td>₹<?php echo number_format($cgst, 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>SGST (9%):</strong></td>
                                <td>₹<?php echo number_format($sgst, 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                                <td>₹<?php echo number_format($total, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" name="place_order" class="cart-btn">Place Order</button>
                    </div>
                </form>
            </div>
        <?php } else { ?>
            <div class="customer-form">
                <p>Your cart is empty. Please add some items to continue.</p>
                <a href="#flavors" class="cart-btn tab-link" data-tab="flavors-tab">Browse Flavors</a>
            </div>
        <?php } ?>
    </div>
    
    <?php if (isset($show_invoice) && $show_invoice) { 
        // Get order details
        $order_query = "SELECT o.*, c.name, c.email, c.phone FROM orders o 
                        JOIN customers c ON o.customer_id = c.id 
                        WHERE o.id = $invoice_id";
        $order_result = mysqli_query($conn, $order_query);
        $order = mysqli_fetch_assoc($order_result);
        
        // Get order items
        $items_query = "SELECT oi.*, ic.name FROM order_items oi 
                        JOIN ice_creams ic ON oi.ice_cream_id = ic.id 
                        WHERE oi.order_id = $invoice_id";
        $items_result = mysqli_query($conn, $items_query);
    ?>
    <div class="invoice">
        <h2>Order Invoice #<?php echo $invoice_id; ?></h2>
        <p><strong>Date:</strong> <?php echo date('d-m-Y', strtotime($order['order_date'])); ?></p>
        <p><strong>Customer:</strong> <?php echo $order['name']; ?></p>
        <p><strong>Email:</strong> <?php echo $order['email']; ?></p>
        <p><strong>Phone:</strong> <?php echo $order['phone']; ?></p>
        
        <h3>Order Items</h3>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = mysqli_fetch_assoc($items_result)) { ?>
                    <tr>
                        <td><?php echo $item['name']; ?></td>
                        <td>₹<?php echo $item['price']; ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>₹<?php echo $item['price'] * $item['quantity']; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Subtotal:</strong></td>
                    <td>₹<?php echo number_format($order['subtotal'], 2); ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>CGST (9%):</strong></td>
                    <td>₹<?php echo number_format($order['cgst'], 2); ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>SGST (9%):</strong></td>
                    <td>₹<?php echo number_format($order['sgst'], 2); ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                    <td>₹<?php echo number_format($order['total'], 2); ?></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="thank-you">
            <h3>Thank You for Your Order!</h3>
            <p>Visit again .</p>
            <a href="order.php" class="cart-btn">Continue Shopping</a>
        </div>
    </div>
    <?php } ?>
    
    <div class="cart-icon">
        <a href="#cart" class="tab-link" data-tab="cart-tab" style="color: white;">
            🛒
            <span class="cart-count"><?php echo array_sum(array_column($cart, 'quantity')); ?></span>
        </a>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Scoops Delight is your go-to place for delicious ice creams. We serve happiness in every scoop!</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <p><a href="#flavors" class="cart-btn tab-link" data-tab="flavors-tab">Our Flavors</a></p>
                <p><a href="#cart" class="cart-btn tab-link" data-tab="cart-tab">Your Cart</a></p>
                <p><a href="#checkout" class="cart-btn tab-link" data-tab="checkout-tab">Checkout</a></p>
            </div>
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><i class="fas fa-map-marker-alt"></i> Mirkwood  Ice Cream Street, Scoop-Delight</p>
                <p><i class="fas fa-phone"></i> +91 8788608183</p>
                <p><i class="fas fa-envelope"></i> info@scoopsdelight.com</p>
            </div>
            <div class="footer-section">
                <h3>Follow Us</h3>
                <div class="social-icons">
                    <a href="https://www.instagram.com/pushpak_363/" target="_blank"><span class="material-symbols-outlined">
account_circle
</span></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Scoops Delight. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab functionality
            const tabLinks = document.querySelectorAll('.tab-link');
            tabLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    document.querySelectorAll('.tab-link').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Quantity selector functionality
            const decreaseButtons = document.querySelectorAll('.decrease-qty');
            const increaseButtons = document.querySelectorAll('.increase-qty');
            
            decreaseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.nextElementSibling;
                    if (input.value > 1) {
                        input.value = parseInt(input.value) - 1;
                    }
                });
            });
            
            increaseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    input.value = parseInt(input.value) + 1;
                });
            });
        });
    </script>
</body>
</html>