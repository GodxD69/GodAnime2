<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
setcookie('userID', '', time() - 3600, '/');
unset($_COOKIE['userID']);
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
<script>
try { localStorage.removeItem("project_r_auth"); } catch(e){}
window.location.replace("/home");
</script>
</head>
<body>
Logging out of GodAnime...
</body>
</html>
