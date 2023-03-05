<?php
require './cmsix.php';

// Password SALT and HASH can be generated on /hash page.
// Salt is just a string with random characters.
// Hash is value returned by password_hash(), salt + password.
//
const PASS_HASH = '$2y$10$sj7Q4h.T6iOlh1K24ZEOe.sytdfkUCgFt1n9/nxOLlxLpYeEXN9Gi';
const PASS_SALT = 'batHMwtcn/HgAT86DpFvNjs5Zl57N0TMJ8K50B4TKdU=';

const S_KEY    = 'SID';                 // $_SESSION key
const S_COOKIE = 'CMSIX_SID';           // Browser cookie name
const S_EXPIRY = 60*60;                 // 60m, cookie expire time
const FPATH    = cmsix\FPATH;           // Path to file with data
const DPATH    = 'db/';                 // Path to files dir

enum Page {             // Each possible page, used to set $page var
	case Hash;      // Page for creating password hash and salt
	case Login;     // Login page, default when not authorized
	case Logout;    // Upon visiting logout from active session
	                // NEXT PAGES REQUIRE AUTHENTICATION
	case Text;      // Main page for editing values in DB file
	case File;      // View, add and remove files
}

$data = [];             // Data required to render given page
$msg  = [];             // Messages to show on page, if any

$tmp = explode(basename(__FILE__), $_SERVER['PHP_SELF']);
$url_args  = array_pop($tmp);           // Path beyond this file path
$url_root  = array_pop($tmp);           // Path to this file
$url_dpath = $url_root.DPATH;           // Path to files dir

unset($tmp);
session_start();

if (isset($_GET['msg'])) {              // Get page message from query
	array_push($msg, $_GET['msg']);
}
if (!isset($page) and str_starts_with($url_args, '/hash')) {
	$page = Page::Hash;
	if (isset($_GET['pass'])) {
		$salt = base64_encode(random_bytes(32));
		$hash = password_hash($salt.$_GET['pass'], PASSWORD_DEFAULT);
		echo '<pre>';
		echo "const PASS_HASH = '{$hash}';\n";
		echo "const PASS_SALT = '{$salt}';\n";
		echo '</pre>';
		exit(0);                // End here, else render page
	}
}
if (!isset($page) and str_starts_with($url_args, '/login')) {
	$page = Page::Login;
	if (isset($_POST['pass'])) {
		if (password_verify(PASS_SALT.$_POST['pass'], PASS_HASH)) {
			$session = uniqid();
			$_SESSION[S_KEY] = $session;
			setcookie(S_COOKIE, $session, [
				'expires'  => time() + S_EXPIRY,
				'samesite' => 'Strict',
			]);
			header('Location: '.$url_root); // Redirect
			exit;           // End here, successfull login
		}
		array_push($msg, 'Invalid password!');
	}
}
if (!isset($page) and str_starts_with($url_args, '/logout')) {
	$page = Page::Logout;
	$_SESSION[S_KEY] = null;                // Not necessary
	setcookie(S_COOKIE, '', time() -1);     // Remove cookie
	session_destroy();
	array_push($msg, "You have been loged out successfully.");
}
if (!isset($page)) {                            // Authorize next pages
	$session = $_SESSION[S_KEY]   ?? null;
	$cookie  = $_COOKIE[S_COOKIE] ?? null;
	// Redirect to login page if not authorized.
	if (!$session or !$cookie or $session !== $cookie) {
		header('Location: '.$url_root.'login?msg=Unauthorized!');
		exit;                   // End here if not authorized
	}
	// Refresh session cookie expire time.
	setcookie(S_COOKIE, $cookie, [
		'expires'  => time() + S_EXPIRY,
		'samesite' => 'Strict',
	]);
}
if (!isset($page) and str_starts_with($url_args, '/file')) {
	$page = Page::File;
	$data = ['files' => [], 'sets' => []];
	if (!is_dir(DPATH) and !mkdir(DPATH)) {
		array_push($msg, "ERROR: Can't make ".DPATH);
		goto skip;
	}
	if (isset($_FILES['files'])) {
		$files = $_FILES['files'];
		// $files = [
		//     [name]      => [ 'img01.jpeg' , 'img02.jpeg' ]
		//     [full_path] => [ 'img01.jpeg' , 'img02.jpeg' ]
		//     [type]      => [ 'image/jpeg' , 'image/jpeg' ]
		//     [tmp_name]  => [ '/tmp/php1L' , '/tmp/phpvX' ]
		//     [error]     => [ 0            , 0            ]
		//     [size]      => [ 308725       , 161037       ]
		// ]
		// TODO(irek): Investigate size limits.
		// TODO(irek): Check for errors in files.
		// TODO(irek): There is no check if file already
		// exists.  I want to give possiblity to overwrite
		// existing files.  But this might not be the best
		// way to do it.
		$count = count($files['name']);
		for ($i = 0; $i < $count; $i++) {
			$src = $files['tmp_name'][$i];
			$dst = DPATH.$files['name'][$i];
			move_uploaded_file($src, $dst);
		}
		array_push($msg, "Uploade complete.");
	}
	// This error should not be possible but let's handle it.
	if (!($dir = opendir(DPATH))) {
		array_push($msg, "ERROR: Can't open ".DPATH);
		goto skip;
	}
	while ($f = readdir($dir)) {
		if (str_starts_with($f, '.')   ||       // Skip hidden
		    str_ends_with($f, '.html') ||       // Skip .html
		    str_ends_with($f, '.php')  ||       // Skip .php
		    is_dir($f)) {                       // Skip dirs
			continue;
		}
		if (str_contains($f, cmsix\PREFIX)) {   // Cmsix file
			[$k] = explode(cmsix\PREFIX, $f);
			if (!isset($data['sets'][$k])) {
				$data['sets'][$k] = [];
			}
			array_push($data['sets'][$k], $f);
			continue;
		}
		array_push($data['files'], $f);          // Normal file
	}
	unset($f);
	closedir($dir);
	unset($dir);
	sort($data['files']);
	ksort($data['sets']);
	array_map('sort', $data['sets']);
}
if (!isset($page)) {                                    // Default page
	$page = Page::Text;
	$data = cmsix\read(FPATH);
	if (count($_POST)) {
		foreach ($_POST as $k => $v) {
			if (isset($data['texts'][$k])) {
				$data['texts'][$k] = $v;
			}
		}
		if (!is_resource($file = fopen(FPATH, 'wb'))) {
			array_push($msg, "ERROR: Can't open ".FPATH);
			goto skip;
		}
		foreach ($data['texts'] as $k => $v) {
			fwrite($file, $k);
			$v = str_replace('', '', $v);
			$v = rtrim($v) . "\n";
			// Append END indicator.  Default is single
			// empty line but if $v value contains empty
			// lines then generate unique id to mark end.
			if (str_contains($v, "\n\n")) {
				$id = "END-".uniqid();
				fwrite($file, "\t".$id);
				$v .= $id."\n";
			}
			fwrite($file, "\n".$v."\n");
		}
		fclose($file);
		array_push($msg, "Texts have been updated.");
	}
}
skip:           // Goto label to skip code in case of fatal error
                // but when you still want to render the website.
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
</head>
<body>
<style>
body {
	background-color: #fff;
	padding: min(4vw, 4vh);
	max-width: 38em;
	margin: 0 auto;
	line-height: 1.3;
	font-family:     /* Source: http://web.mit.edu/jmorzins/www */
		"Bookman Old Style",    /* Windows, MacOS           */
		"Serifa BT",            /* Windows XP               */
		"URW Bookman L",        /* Unix+X+FontConfig        */
		"itc bookman",          /* Unix+X                   */
		times, serif;           /* Fallback options         */
}
a { color: #012fe2 }
a:hover { text-decoration: none }
img { display: block; max-width: 100%; border: 1px solid #000 }
.msg { padding: .5em 1em; outline: 1px solid }
@media (prefers-color-scheme: dark) {
	body { background-color: #000; color: #fff }
	a { color: #ffef6e }
}
</style>
<?php foreach ($msg as $m): ?>
	<p class=msg><?=$m?></p>
<?php endforeach ?>
<?php // Render pages that DON'T REQUIRE authorization ------------ ?>
<?php if ($page == Page::Hash): ?>
	<h1>Hash</h1>
	<p>Create password hash and salt.</p>
	<form>
		<input type=password name=pass placeholder=password required autofocus>
		<input type=submit>
	</form>
<?php endif ?>
<?php if ($page == Page::Login): ?>
	<h1>Login</h1>
	<p>Password should be given to you by website administrator.</p>
	<form method=post>
		<input type=password name=pass placeholder=password required autofocus>
		<input type=submit value=login>
	</form>
<?php endif ?>
<?php if ($page == Page::Logout): ?>
	<h1>Logout</h1>
	<ul>
		<li><a href=/>home page</a></li>
		<li><a href=<?=$url_root.'login'?>>login page</a></li>
	</ul>
<?php endif ?>
<?php // Render pages that REQUIRE authorization ------------------ ?>
<?php if (in_array($page, [Page::Text, Page::File])):   // Nav menu ?>
	<style>
	menu {
		list-style-type: none;
		display: flex;
		justify-content: flex-end;
		gap: 1em;
	}
	</style>
	<menu>
		<li><a href=<?=$url_root?>>texts</a>
		<li><a href=<?=$url_root.'file'?>>files</a>
		<li><a href=<?=$url_root.'logout'?>>logout</a>
	</menu>
<?php endif ?>
<?php if ($page == Page::Text): ?>
	<style>
	textarea {
		min-width: 100%;
		max-width: 100%;
		box-sizing: border-box;
		border: solid 1px currentColor;
	}
	@media (prefers-color-scheme: dark) {
		textarea {
			background-color: #000;
			color: #fff;
		}
	}
	</style>
	<h1>Texts</h1>
	<p>Modify at least one field and submit to make a change.</p>
	<form method=post>
		<?php foreach ($data['texts'] as $k => $v): ?>
			<h3 id=<?=$k?>><code><?=$k?></code></h3>
			<textarea cols=80 rows=<?=substr_count($v,"\n")+1?> name=<?=$k?>><?=$v?></textarea>
		<?php endforeach ?>
		<p><input type=submit></p>
	</form>
<?php endif ?>
<?php if ($page == Page::File): ?>
	<h1>Files</h1>
	<h2>Upload</h2>
	<form method=post enctype=multipart/form-data>
		<input type=file name=files[] multiple required>
		<input type=submit value=upload>
	</form>
	<h2>View and remove</h2>
	<?php foreach ($data['files'] as $f): ?>
		<h3><?=$f?></h3>
		<?php if (in_array(pathinfo($f)['extension'], ['jpg', 'jpeg', 'gif', 'png'])): ?>
			<a href=<?=$url_dpath.$f?>>
				<img src=<?=$url_dpath.$f?> loading=lazy>
			</a>
		<?php endif ?>
		<p><a href=<?=$url_dpath.$f?>><?=$url_dpath.$f?></a>
		<?php if (isset($data['sets'][$f])): ?>
			<?php foreach ($data['sets'][$f] as $s): ?>
				<br><a href=<?=$url_dpath.$s?>><?=$url_dpath.$s?></a>
			<?php endforeach ?>
		<?php endif ?>
		</p>
	<?php endforeach ?>
<?php endif ?>
</body>
</html>
