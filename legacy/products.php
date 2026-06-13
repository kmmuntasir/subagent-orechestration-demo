<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html>
<head><title>Products</title></head>
<body>
<h1>Product List</h1>

<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            $category_id = intval($_POST['category_id']);
            $sql = "INSERT INTO products (name, description, price, stock, category_id, created_at)
                    VALUES ('$name', '$description', $price, $stock, $category_id, NOW())";
            mysqli_query($conn, $sql);
        }

        if ($_POST['action'] === 'update') {
            $id = intval($_POST['id']);
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $price = floatval($_POST['price']);
            $stock = intval($_POST['stock']);
            $category_id = intval($_POST['category_id']);
            $sql = "UPDATE products SET name='$name', description='$description',
                    price=$price, stock=$stock, category_id=$category_id
                    WHERE id=$id";
            mysqli_query($conn, $sql);
        }

        if ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $sql = "DELETE FROM products WHERE id=$id";
            mysqli_query($conn, $sql);
        }
    }
}

// Search filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
}
if ($category_filter) {
    $where .= " AND p.category_id = $category_filter";
}

// List products with category name
$result = mysqli_query($conn, "
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $where
    ORDER BY p.created_at DESC
");
?>

<table border="1" cellpadding="5">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Description</th>
        <th>Price</th>
        <th>Stock</th>
        <th>Category</th>
        <th>Created</th>
        <th>Actions</th>
    </tr>
    <?php if (mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr>
        <td><?php echo $row['id']; ?></td>
        <td><?php echo htmlspecialchars($row['name']); ?></td>
        <td><?php echo htmlspecialchars($row['description']); ?></td>
        <td>$<?php echo number_format($row['price'], 2); ?></td>
        <td><?php echo $row['stock']; ?></td>
        <td><?php echo $row['category_name'] ?? 'Uncategorized'; ?></td>
        <td><?php echo $row['created_at']; ?></td>
        <td>
            <a href="edit_product.php?id=<?php echo $row['id']; ?>">Edit</a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <button type="submit">Delete</button>
            </form>
        </td>
    </tr>
        <?php endwhile; ?>
    <?php else: ?>
    <tr><td colspan="8">No products found.</td></tr>
    <?php endif; ?>
</table>

<h2>Add Product</h2>
<form method="POST">
    <input type="hidden" name="action" value="create">
    <label>Name: <input type="text" name="name" required></label><br>
    <label>Description: <textarea name="description"></textarea></label><br>
    <label>Price: <input type="number" name="price" step="0.01" required></label><br>
    <label>Stock: <input type="number" name="stock" required></label><br>
    <label>Category ID: <input type="number" name="category_id"></label><br>
    <button type="submit">Create</button>
</form>

<?php mysqli_close($conn); ?>
</body>
</html>
