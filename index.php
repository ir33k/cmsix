<?php
require './cmsix.php';

// Password SALT and HASH can be generated on /hash page.
// Salt is just a string with random characters.
// Hash is value returned by password_hash().

const PASS_HASH = '$2y$10$sj7Q4h.T6iOlh1K24ZEOe.sytdfkUCgFt1n9/nxOLlxLpYeEXN9Gi';
const PASS_SALT = 'batHMwtcn/HgAT86DpFvNjs5Zl57N0TMJ8K50B4TKdU=';

const S_KEY     = 'SID';          // $_SESSION key for session id
const S_COOKIE  = 'CMSIX_SID';    // Browser cookie name
const S_EXPIRY  = 60*60;          // 60m, cookie expire time
const FPATH     = './db.txt';     // Path to file with data

// Enums for each possible page used to set $page var.
enum Page {
	case Hash;      // Page for creating password hash and salt
	case Login;     // Login page, default when not authorized
	case Logout;    // Upon visiting logout from active session
	                // Pages below require authentication
	case Text;      // Main page for editing values in DB file
	case Img;       // View, add and remove images
}

function set_auth_cookie(?string $value = ''): bool
{
	return setcookie(S_COOKIE, $value, [
		'expires' => time() + S_EXPIRY,
		'samesite' => 'Strict',
	]);
}

$tmp = explode(basename(__FILE__), $_SERVER['PHP_SELF']);
$url_args = array_pop($tmp);
$url_root = array_pop($tmp);
unset($tmp);
session_start();

if (!isset($page) and str_starts_with($url_args, '/hash')) {
	$page = Page::Hash;
	if (isset($_GET['pass'])) {
		$salt = base64_encode(random_bytes(32));
		$hash = password_hash($salt.$_GET['pass'], PASSWORD_DEFAULT);
		echo '<pre>';
		echo 'const PASS_HASH = \''.$hash.'\';'.PHP_EOL;
		echo 'const PASS_SALT = \''.$salt.'\';'.PHP_EOL;
		echo '</pre>';
		exit(0);
	}
}
if (!isset($page) and str_starts_with($url_args, '/login')) {
	$page = Page::Login;
	if (isset($_POST['pass'])) {
		if (password_verify(PASS_SALT.$_POST['pass'], PASS_HASH)) {
			// Login
			$session = uniqid();
			$_SESSION[S_KEY] = $session;
			set_auth_cookie($session);
			header('Location: '.$url_root);
			exit;
		} else {
			$msg = 'Invalid password';
		}
	}
}
if (!isset($page) and str_starts_with($url_args, '/logout')) {
	$page = Page::Logout;
	$_SESSION[S_KEY] = null;
	setcookie(S_COOKIE, '', time() -1);
	session_destroy();
}
if (!isset($page)) {                    // Authorize next pages
	$session = $_SESSION[S_KEY]   ?? null;
	$cookie  = $_COOKIE[S_COOKIE] ?? null;
	// Redirect to login page if not authorized.
	if (!$session or !$cookie or $session !== $cookie) {
		header('Location: '.$url_root.'login');
		exit;                   // End here if not authorized
	}
	set_auth_cookie($cookie);       // Refresh session cookie.
	$authorized = true;
}
if (!isset($page) and str_starts_with($url_args, '/img')) {
	$page = Page::Img;
}
if (!isset($page)) {
	$page = Page::Text;             // Default page when authorized
	$data = cmsix\read(FPATH);
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
<html lang=en>
<head>
<meta charset=utf-8 />
<meta name=author content=irek />
<meta name=keywords content=cms,cmsix,admin,php />
<meta name=description content=cmsix_cms_admin_page />
<meta name=apple-mobile-web-app-capable content=yes />
<meta name=mobile-web-app-capable content=yes />
<meta name=viewport content=width=device-width,initial-scale=1,maximum-scale=1 />
<meta name=theme-color content=#000000 />
<meta name=msapplication-navbutton-color content=#000000 />
<meta name=apple-mobile-web-app-status-bar-style content=#000000 />
<title>cmsix</title>
<style>
body {
	background-color: #efefef;
	padding: min(4vw, 4vh);
	max-width: 38em;
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
a { color: #012fe2 }
footer {
	text-align: right;
}
@media (prefers-color-scheme: dark) {
	body { background-color: #000; color: #fff }
	a { color: #ffef6e }
}
</style>
</head>
<body>
<h1 hidden>cmsix</h1>

<!-- Render pages that DON'T REQUIRE authorization -->

<?php if ($page == Page::Hash): ?>
	<h2>Hash</h2>
	<p>Create password hash.</p>
	<form>
		<input type=password name=pass placeholder=password required autofocus>
		<input type=submit>
	</form>
<?php endif ?>

<?php if ($page == Page::Login): ?>
	<h2>Login</h2>
	<p>Password should be given to you by website administrator.</p>
	<?php if (isset($msg)) echo "<p><mark>$msg</mark></p>" ?>
	<form method=post>
		<input type=password name=pass placeholder=password required autofocus>
		<input type=submit>
p	</form>
<?php endif ?>

<?php if ($page == Page::Logout): ?>
	<p>You have been logged out. Go back to:</p>
	<ul>
		<li><a href=/>home page</a></li>
		<li><a href=<?=$url_root.'login'?>>login page</a></li>
	</ul>
<?php endif ?>

<!-- Render pages that REQUIRE authorization -->

<?php if (isset($authorized)): ?>
	<!-- Navigation menu for all authorized pages -->
	<style>
	menu {
		list-style-type: none;
		display: flex;
		justify-content: flex-end;
		padding: 0;
		margin-bottom: 0;
		gap: 1em;
	}
	</style>
	<menu>
		<li><a href=<?=$url_root?>>texts</a>
		<li><a href=<?=$url_root.'img'?>>images</a>
		<li><a href=<?=$url_root.'logout'?>>logout</a>
	</menu>
<?php endif ?>

<?php if ($page == Page::Img): ?>
	<h2>Images</h2>
	<p>View, add and remove images.</p>
<?php endif ?>

<?php if ($page == Page::Text): ?>
	<style>
	textarea {
		min-width: 100%;
		max-width: 100%;
		box-sizing: border-box;
	}
	</style>
	<h2>Texts</h2>
	<p>Modify at least one field and submit to make a change.</p>
	<form method=post>
		<?php foreach ($data['texts'] as $k => $v): ?>
			<h3 id=<?=$k?>><code><?=$k?></code></h3>
			<textarea cols=80 rows=<?=substr_count($v,"\n")+1?> width=100% name=<?=$k?>><?=$v?></textarea>
		<?php endforeach ?>
		<p><input type=submit name=<?=cmsix\PREFIX?>_update></p>
	</form>
<?php endif ?>

<footer id=contact>
	<a href=https://github.com/ir33k/cmsix>cmsix cms</a>
	v<?=cmsix\VERSION?>
</footer>
</body>
</html>
