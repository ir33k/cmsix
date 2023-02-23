<?php

/** Password hashed with password_hash() function. */
const PASSWORD_HASH = '$2y$10$yIAuLHVoKXn8sczLwI27he0ELLMEQi3BxYtWVPESlR/lbh4TYxKsW';

/** Key name of active session in $_SESSION array. */
const SESSION_KEY = 'PASS_HASH';

/** Name of browser session cookie. */
const SESSION_COOKIE = 'CMSIX_SESSION_ID';

/** Expiry session after 5 min. */
define('SESSION_EXPIRY', time() + 60*5);

enum Page {
    case Hash;
    case Login;
    case Logout;
    case Admin;
}

session_start();

$tmp = explode(basename(__FILE__), $_SERVER['PHP_SELF']);
$url_args = array_pop($tmp);
$url_root = array_pop($tmp);

unset($tmp);

if (!isset($page) and preg_match('/^\/hash/', $url_args)) {
    $page = Page::Hash;
    if (isset($_GET['password'])) {
	echo password_hash($_GET['password'], PASSWORD_DEFAULT);
	exit(0);
    }
}

if (!isset($page) and preg_match('/^\/login/', $url_args)) {
    $page = Page::Login;
    if (isset($_POST['password'])) {
	if (password_verify($_POST['password'], PASSWORD_HASH)) {
	    // Login
            $session = uniqid();
            $_SESSION[SESSION_KEY] = $session;
            setcookie(SESSION_COOKIE, $session, SESSION_EXPIRY);
            header('Location: '.$url_root);
            exit;
	} else {
            $msg = 'Invalid password!';
	}
    }
}

if (!isset($page) and preg_match('/^\/logout/', $url_args)) {
    $page = Page::Logout;
    $_SESSION[SESSION_KEY] = null;
    setcookie(SESSION_COOKIE, '', time() -1);
}

// Default to main admin page.
if (!isset($page)) {
    $page = Page::Admin;
    $session = $_SESSION[SESSION_KEY] ?? null;
    $cookie = $_COOKIE[SESSION_COOKIE] ?? null;
    // Redirect to login page if not authorized.
    if (!$session or !$cookie or $session !== $cookie) {
        header('Location: '.$url_root.'login');
        exit;
    }
    // Refresh session cookie.
    setcookie(SESSION_COOKIE, $cookie, SESSION_EXPIRY);
    //
    require './cmsix.php';
    $data = cmsix\read('./db.txt');
    if (isset($_POST[cmsix\PREFIX.'_update'])) {
        foreach ($_POST as $k => $v) {
            if (isset($data['texts'][$k])) {
                $data['texts'][$k] = $v;
            }
        }
	cmsix\write($data, './db.txt');
    }
    if (isset($_GET['filter'])) {
	$data = cmsix\get($data, '/'.$_GET['filter'].'/');
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
<meta name="theme-color" content="#000000">
<meta name="msapplication-navbutton-color" content="#000000">
<meta name="apple-mobile-web-app-status-bar-style" content="#000000">
<title>cmsix</title>
<style>
</style>
</head>
<body>
<h1>cmsix</h1>

<!-- Pages -->

<?php if ($page == Page::Hash): ?>
    <h2>Hash</h2>
    <p>Create password hash.</p>
    <form>
	<input type=password name=password placeholder=password required>
	<input type=submit>
    </form>
<?php endif ?>

<?php if ($page == Page::Login): ?>
    <h2>Login</h2>
    <?php if (isset($msg)) echo "<p><strong>$msg</strong></p>" ?>
    <form method=post>
	<input type=password name=password placeholder=password required>
	<input type=submit value=login>
    </form>
<?php endif ?>

<?php if ($page == Page::Logout): ?>
    <p>You have been logged out. Go back to:</p>
    <ul>
	<li><a href="/">home page</a></li>
	<li><a href="<?=$url_root.'login'?>">login page</a></li>
    </ul>
<?php endif ?>

<?php if ($page == Page::Admin): ?>
    <h2>Admin page</h2>
    <a href="<?=$url_root.'logout'?>">logout</a>
    <form>
	<input type=text name=filter placeholder="filter, submit empty to clear">
	<input type=submit value=filter>
    </form>
    <ul>
	<?php foreach (array_keys($data['lines']) as $k): ?>
	    <li><a href=#<?=$k?>><?=$k?></a>
	<?php endforeach ?>
    </ul>
    <form method=post>
	<?php foreach ($data['texts'] as $k => $v): ?>
	    <article id=<?=$k?>>
		<h3><?=$k?></h3>
		<textarea cols=80 rows=10 name=<?=$k?>><?=$v?></textarea>
	    </article>
	<?php endforeach ?>
	<input type=submit name=<?=cmsix\PREFIX?>_update value=update>
    </form>
<?php endif ?>

</body>
</html>
