<?php
session_start(['cookie_lifetime' => 0]);

require_once __DIR__ . '/../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    //recaptcha
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    
    //recaptcha_secret
    $verify_url = "https://www.google.com/recaptcha/api/siteverify?secret=" . RECAPTCHA_SECRET . "&response=" . $recaptcha_response;
    $response = file_get_contents($verify_url);
    $response_keys = json_decode($response, true);

    if (!$response_keys["success"]) {
        die("<h2 style='color:red;font-family:sans-serif'>Chyba: Potvrďte, že nejste robot (reCAPTCHA).</h2>");
    }

    //priprava prihlaseni
    $user_input = $_POST['username'] ?? '';
    $pass_input = $_POST['password'] ?? '';

    //pripraveny dotaz
    $stmt = $conn->prepare("SELECT heslo, role FROM `uzivatele` WHERE login = ?");
    //string
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        //overeni hesla
        if (password_verify($pass_input, $row['heslo']) || $pass_input === $row['heslo']) {
            
            $_SESSION['user'] = $user_input;
            $_SESSION['role'] = $row['role']; 
            $_SESSION['last_activity'] = time();
            
            echo "<h2 style='color:green;font-family:sans-serif'>Přihlášení úspěšné....</h2>";
            header("Refresh: 1; url=index.php");
        } else {
            echo "<h2 style='color:red;font-family:sans-serif'>Chybné jméno nebo heslo.</h2>";
            header("Refresh: 2; url=index.php");
        }
    } else {
        echo "<h2 style='color:red;font-family:sans-serif'>Chybné jméno nebo heslo.</h2>";
        header("Refresh: 2; url=index.php");
    }
    
    $stmt->close();
}
?>