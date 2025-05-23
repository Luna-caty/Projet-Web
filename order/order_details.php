<?php
require_once '../register/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../register/SignIn.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_confirmed = false;
$order_id = 0;

if (isset($_POST['confirm_order'])) {
    try {
        $stmt = mysqli_prepare($connection, "CALL FinalizeOrder(?, @order_id)");
        mysqli_stmt_bind_param($stmt, "i", $user_id);

        if (!mysqli_stmt_execute($stmt)) {
            if (strpos(mysqli_error($connection), 'Stock insuffisant') !== false) {
                $error_message = "Stock insuffisant pour certains produits de votre panier. Veuillez ajuster vos quantités.";
            } else {
                $error_message = "Une erreur est survenue lors de la finalisation de votre commande.";
            }
        } else {
            mysqli_stmt_close($stmt);

            $result = mysqli_query($connection, "SELECT @order_id as order_id");
            $row = mysqli_fetch_assoc($result);
            $order_id = $row['order_id'];

            if ($order_id > 0) {

                header("Location: order_confirmation.php?order_id=" . $order_id);
                exit();
            } else if ($order_id == 0) {
                $error_message = "Votre panier est vide.";
            } else if ($order_id == -1) {
                $error_message = "Stock insuffisant pour certains produits de votre panier. Veuillez ajuster vos quantités.";
            } else {
                $error_message = "Une erreur est survenue lors de la finalisation de votre commande.";
            }
        }
    } catch (Exception $e) {
        $error_message = "Une erreur est survenue: " . $e->getMessage();
    }
}

if ($order_confirmed) {
    header("Location: order_confirmation.php?order_id=" . $order_id);
    exit();
}



$stmt = mysqli_prepare($connection, "CALL GetOrderDetails(?)");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$items_result = mysqli_stmt_get_result($stmt);
$items = array();
while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = $item;
}

$total = 0;
foreach ($items as $item) {
    $total += $item['subtotal'];
}

if (mysqli_more_results($connection)) {
    mysqli_next_result($connection);
    $total_result = mysqli_store_result($connection);

    if ($total_result) {
        $total_row = mysqli_fetch_assoc($total_result);
        if (isset($total_row['total_amount']) && $total_row['total_amount'] > 0) {
            $total = $total_row['total_amount'];
        }
    }
}

$total_formatted = number_format($total, 2);

while (mysqli_more_results($connection)) {
    mysqli_next_result($connection);
    mysqli_store_result($connection);
}

$user_query = "SELECT * FROM Users WHERE id_user = $user_id";
$user_result = mysqli_query($connection, $user_query);
$user = mysqli_fetch_assoc($user_result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="order_details.css">
    <link rel="icon" href="../assets/icon2.png" type="image/png">
</head>

<body>
    <?php include '../reusables/navbar.php'; ?>
    <div class="order-container">
        <div class="order-header">
            <h1>Détails de la commande</h1>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <div class="customer-info">
            <h2>Informations client</h2>
            <p><strong>Nom:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        </div>

        <div class="order-details">
            <h2>Articles commandés</h2>
            <table class="order-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Prix unitaire</th>
                        <th>Quantité</th>
                        <th>Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo number_format($item['prix'], 2); ?>€</td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['subtotal'], 2); ?>€</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="total-label">Total</td>
                        <td class="total-amount"><?php echo $total_formatted; ?>€</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="order-actions">
            <a href="../cart/cart.php" class="back-btn">Retour au panier</a>
            <form method="post" action="order_details.php">
                <button type="submit" name="confirm_order" class="confirm-btn">Confirmer la commande</button>
            </form>
        </div>
    </div>
</body>

</html>