<?php
session_start();

$admin_user = "admin";
$admin_pass = "1234";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST["username"] === $admin_user && $_POST["password"] === $admin_pass) {
        $_SESSION["loggedin"] = true;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Verkeerde login!";
    }
}
?>

<form method="post">
    <h2>Admin login</h2>

    <input name="username" placeholder="Gebruikersnaam"><br><br>
    <input name="password" type="password" placeholder="Wachtwoord"><br><br>

    <button type="submit">Login</button>

    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
</form>