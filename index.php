<?php

require './cmsix.php';

/** Password hashed with password_hash() function. */
define('PASSWORD_HASH', '$2y$10$yIAuLHVoKXn8sczLwI27he0ELLMEQi3BxYtWVPESlR/lbh4TYxKsW');

/** Path to file used to read and write data. */
define('FPATH', './db.txt');

/** Key name of active session in $_SESSION array. */
define('SESSION_KEY', 'PASS_HASH');

/** Name of browser session cookie. */
define('SESSION_COOKIE', 'CMSIX_SESSION_ID');

/** Expiry session after 60 min. */
define('SESSION_EXPIRY', time() + 60*60);

/** Enum for each possible page. */
enum Page {
    case Hash;
    case Login;
    case Logout;
    case Admin;
}

function set_auth_cookie(?string $value = ''): bool
{
    return setcookie(SESSION_COOKIE, $value, [
        'expires' => SESSION_EXPIRY,
        'samesite' => 'Strict',
    ]);
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
            set_auth_cookie($session);
            header('Location: '.$url_root);
            exit;
        } else {
            $msg = 'Invalid password';
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
    set_auth_cookie($cookie);
    //
    $data = cmsix\read(FPATH)['texts'];
    if (isset($_POST[cmsix\PREFIX.'_update'])) {
        foreach ($_POST as $k => $v) {
            if (isset($data['texts'][$k])) {
                $data['texts'][$k] = $v;
            }
        }
        cmsix\write($data, FPATH);
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
body {
    background-color: #fff;
    padding: min(4vw, 4vh);
    max-width: 42em;
    margin: 0 auto;
    line-height: 1.3;

    /* source: http://web.mit.edu/jmorzins/www/ */
    font-family:
        "Bookman Old Style",    /* Windows, MacOS */
        "Serifa BT",            /* Windows XP */
        "URW Bookman L",        /* Unix+X+FontConfig */
        "itc bookman",          /* Unix+X */
        times, serif;           /* Fallback options */
}
a { color: #430cbd }
a:visited { color: #066c16 }
textarea {
    min-width: 100%;
    max-width: 100%;
}
footer {
    text-align: right;
}
@media (prefers-color-scheme: dark) {
    body { background-color: #000; color: #fff }
    a { color: #ffef6e }
    a:visited { color: #6eff77 }
}
</style>
</head>
<body>
<h1 hidden>cmsix</h1>

<?php if ($page == Page::Hash): ?>
    <h2>Hash</h2>
    <p>Create password hash.</p>
    <form>
        <input type=password name=password placeholder=password required autofocus>
        <input type=submit>
    </form>
<?php endif ?>

<?php if ($page == Page::Login): ?>
    <h2>Login</h2>
    <p>Password should be given to you by website administrator.</p>
    <?php if (isset($msg)) echo "<p><mark>$msg</mark></p>" ?>
    <form method=post>
        <input type=password name=password placeholder=password required autofocus>
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
    <p><a href="<?=$url_root.'logout'?>">logout</a></p>
    <h2>Admin page</h2>
    <p>Modify at least one field and submit to make a change.</p>
    <form method=post>
        <?php foreach ($data as $k => $v): ?>
            <article id=<?=$k?>>
                <h3><code><?=$k?></code></h3>
                <textarea cols=80 rows=10 width=100% name=<?=$k?>><?=$v?></textarea>
            </article>
        <?php endforeach ?>
        <p><input type=submit name=<?=cmsix\PREFIX?>_update></p>
    </form>
<?php endif ?>

<footer>
    <p><address id="contact"><a href="https://github.com/ir33k/cmsix">cmsix cms</a> v<?=cmsix\VERSION?></address></p>
</footer>
</body>
</html>
