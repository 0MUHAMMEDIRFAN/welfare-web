<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login($phone, $mpin, $pdo)
{
    $query = "SELECT * FROM users WHERE phone = ? AND is_active = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    //     $mandalam_id = $_SESSION['user_level_id'];

    //     // Get mandalam and district details  
    //     $stmt = $pdo->prepare("  
    //   SELECT m.name as mandalam_name, d.name as district_name   
    //   FROM mandalams m   
    //   JOIN districts d ON m.district_id = d.id   
    //   WHERE m.id = ?  
    //   ");
    //     $stmt->execute([$mandalam_id]);
    //     $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($mpin, $user['mpin'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['level'] = '';

        // Set the correct dashboard path based on role  
        switch ($user['role']) {
            case 'state_admin':
                $_SESSION['level'] = 'state';
                $_SESSION['user_level_id'] = 1;
                header('Location: dashboard/state.php');
                break;
            case 'district_admin':
                $_SESSION['level'] = 'district';
                $_SESSION['user_level_id'] = $user['district_id'];
                header('Location: dashboard/district.php');
                break;
            case 'mandalam_admin':
                $_SESSION['level'] = 'mandalam';
                $_SESSION['user_level_id'] = $user['mandalam_id'];
                header('Location: dashboard/mandalam.php');
                break;
            case 'localbody_admin':
                $_SESSION['level'] = 'localbody';
                $_SESSION['user_level_id'] = $user['localbody_id'];
                header('Location: dashboard/localbody.php');
                break;
            case 'unit_admin':
                $_SESSION['level'] = 'unit';
                $_SESSION['user_level_id'] = $user['unit_id'];
                header('Location: dashboard/unit.php');
                break;
            default:
                header('Location: index.php');
        }
        exit();
    }
    return false;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function logout()
{
    session_destroy();
    header('Location: index.php');
    exit();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ../index.php');  // Added ../ to fix path  
        exit();
    }
}
