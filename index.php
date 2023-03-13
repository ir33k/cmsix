<?php
/**
 * Administration panel for CMSIX CMS.
 *
 * You start at /login page that requires password to authorize.
 * There is only one user so only password is needed, no usernames here.
 * To setup password go to /hash page, provide password and submit.
 * You will get values for PASS_HASH and PASS_SALT to paste in this file.
 * Default values are few lines below, generated for password "12345".
 * USING DEFAULT PASSWORD IS NOT RECOMMENDED!
 *
 * After setting password and authorization ou can get to main pages.
 * Default /text page allowes to modify values in database text file.
 * Page /file is used to upload, view and delete files.
 * Uploaded images are resized to multiple sizes, defined in cmsix.php.
 * To logout visit /logout page.
 */

require './cmsix.php';

/**#@+
 * Password SALT and HASH can be generated on /hash page.
 * Salt is just a string with random characters.
 * Hash is value returned by password_hash(), salt + password.
 */
const PASS_HASH = '$2y$10$sj7Q4h.T6iOlh1K24ZEOe.sytdfkUCgFt1n9/nxOLlxLpYeEXN9Gi';
const PASS_SALT = 'batHMwtcn/HgAT86DpFvNjs5Zl57N0TMJ8K50B4TKdU=';

/**#@-*/

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
	case File;      // Page to view, add and remove files
}

/** Return true if $file name has bitmap image extension. */
function file_has_img_ext(string $file): bool
{
	$ext = pathinfo($file)['extension'] ?? '';
	return in_array($ext, ['jpg', 'jpeg', 'gif', 'png']);
}

/** Return true if given $file name should be ignored. */
function file_ignore(string $file): bool
{
	return str_starts_with($file, '.')       ||     // Is hidden
	       str_ends_with($file, '.html')     ||     // Is HTML
	       str_ends_with($file, '.php')      ||     // Is PHP
	       str_contains($file, cmsix\PREFIX) ||     // Is internal
	       is_dir($file);                           // Is dir
}

/** List all file names from $path dir. */
function ls(string $path): array
{
	$file_names = [];
	if (!($dir = opendir($path))) {
		return $file_names;
	}
	while ($file_name = readdir($dir)) {
		array_push($file_names, $file_name);
	}
	closedir($dir);
	sort($file_names);
	return $file_names;
}

/** Url path to root of admin page (this file). */
$url_root = str_replace('index.php', '', $_SERVER['SCRIPT_NAME']);

// TODO(irek): Handle errors by creating log files.
// https://www.php.net/manual/en/errorfunc.examples.php
// https://www.php.net/manual/en/errorfunc.constants.php
/** Messages to show on page, if any. */
$msg = isset($_GET['msg']) ? [$_GET['msg']] : [];

/** Pages that require authorization. */
$auth_pages = [Page::Text, Page::File];

/** Page to handle and render, default to Page::Text. */
$page = Page::Text;

switch (preg_split('/[\/\?\#]/', $_SERVER['PATH_INFO'] ?? '/')[1]) {
case 'hash'   : $page = Page::Hash   ; break;
case 'login'  : $page = Page::Login  ; break;
case 'logout' : $page = Page::Logout ; break;
case 'file'   : $page = Page::File   ; break;
}

session_start();

if (in_array($page, $auth_pages)) {             // Authorize
	$session = $_SESSION[S_KEY]   ?? null;
	$cookie  = $_COOKIE[S_COOKIE] ?? null;
	// Redirect to login page if not authorized.
	if (!$session or !$cookie or $session !== $cookie) {
		header('Location: '.$url_root.'login?msg=Unauthorized!');
		exit;                           // Unauthorized, redirect
	}
	// Refresh session cookie expire time.
	setcookie(S_COOKIE, $cookie, [
		'expires'  => time() + S_EXPIRY,
		'samesite' => 'Strict',
	]);                                     // Authorized
}
switch ($page) {
case Page::Hash:
	if (isset($_GET['pass'])) {             // Create hash & salt
		$salt = base64_encode(random_bytes(32));
		$hash = password_hash($salt.$_GET['pass'], PASSWORD_DEFAULT);
		header('Content-Type: text/plain; charset=utf-8');
		echo "// Use in index.php:\n\n";
		echo "const PASS_HASH = '{$hash}';\n";
		echo "const PASS_SALT = '{$salt}';\n";
		exit;                           // End here
	}                                       // Else render page
	break;
case Page::Login:
	if (isset($_POST['pass'])) {
		if (password_verify(PASS_SALT.$_POST['pass'], PASS_HASH)) {
			$session = uniqid();
			$_SESSION[S_KEY] = $session;
			setcookie(S_COOKIE, $session, [
				'expires'  => time() + S_EXPIRY,
				'samesite' => 'Strict',
			]);
			header('Location: '.$url_root);
			exit;                           // Loged in
		}
		array_push($msg, 'Invalid password!');  // Wrong pass
	}
	break;
case Page::Logout:
	$_SESSION[S_KEY] = null;                        // Unnecessary
	setcookie(S_COOKIE, '', time()-1);              // Del cookie
	session_destroy();
	break;
case Page::File:
	if (!is_dir(DPATH) and !mkdir(DPATH)) {
		array_push($msg, "ERROR: Can't make ".DPATH);
		goto skip;
	}
	if (isset($_FILES['files'])) {
		$files = $_FILES['files'];
		// $files = [
		//   name      : ['img01.jpeg', 'img02.jpeg']
		//   full_path : ['img01.jpeg', 'img02.jpeg']
		//   type      : ['image/jpeg', 'image/jpeg']
		//   tmp_name  : ['/tmp/php1L', '/tmp/phpvX']
		//   error     : [0, 0]
		//   size      : [308725, 161037]
		// ]
		// TODO(irek): Investigate size limits.
		// TODO(irek): Check for errors in files.
		// TODO(irek): There is no check if file already exists.  I want to give possiblity to overwrite existing files.  But this might not be the best way to do it.
		$count = count($files['name']);
		$imgs = [];             // Images to optimize
		$img_supports = [];     // Types supported to optimise
		if (extension_loaded('gd')) {
			$info = gd_info();
			if ($info['GIF Read Support'] && $info['GIF Create Support']) {
				array_push($img_supports, 'image/gif');
			} else {
				array_push($msg, 'GIF optimisation is not supported');
			}
			if ($info['JPEG Support']) {
				array_push($img_supports, 'image/jpeg');
			} else {
				array_push($msg, 'JPEG optimisation is not supported');
			}
			if ($info['PNG Support']) {
				array_push($img_supports, 'image/png');
			} else {
				array_push($msg, 'PNG optimisation is not supported');
			}
			if ($info['WBMP Support']) {
				array_push($img_supports, 'image/bmp');
			} else {
				array_push($msg, 'BMP optimisation is not supported');
			}
		} else {
			array_push($msg, 'Image optimisation is not supported.');
		}
		for ($i = 0; $i < $count; $i++) {
			$name = $files['name'][$i];
			$ignored = [];
			if (file_ignore($name)) {
			    	array_push($ignored, $name);
				continue;
			}
			$src  = $files['tmp_name'][$i];
			$dst  = DPATH.$name;
			move_uploaded_file($src, $dst);
			if (in_array($files['type'][$i], $img_supports)) {
				array_push($imgs, $dst);
			}
		}
		array_push($msg, 'Uploade complete.');
		if (count($ignored)) {
			array_push($msg, sprintf('<b>Ignored files: %s.</b><br>Files that starts with dot, ends with .html or .php, contains "%s" string, or are existing directories will be skipped.', implode(', ', $ignored), cmsix\PREFIX));
		}
		if (count($imgs)) {
			array_push($msg, sprintf('Images to optimise: %s', implode(', ', $imgs)));
		}
	}
	$data = ls(DPATH);
	if (isset($_GET['delete']) and isset($_GET['files'])) {
		$to_delete = [];
		$ok  = [];
		$err = [];
		foreach ($_GET['files'] as $v) {
			if (in_array($v, $data)) {
				array_push($to_delete, $v);
			}
			foreach ($data as $file) {
				if (str_starts_with($file, $v.cmsix\Prefix)) {
					array_push($to_delete, $file);
				}
			}
		}
		foreach ($to_delete as $v) {
			// TODO(irek): Handle PHP errors, hide them or use them for my own errors?
			// https://www.php.net/manual/en/function.error-reporting.php
			if (unlink(DPATH.$v)) {
				array_push($ok, $v);
			} else {
				array_push($err, $v);
			}
		}
		if (count($ok)) {
			array_push($msg, 'Deleted files: '.implode(', ', $ok));
		}
		if (count($err)) {
			array_push($msg, '<b>Failed to delete files:</b> '.implode(', ', $err));
		}
	}
	$data = array_filter($data, fn($v) => !file_ignore($v));
	break;
case Page::Text:
	$data = cmsix\read(FPATH);
	if (count($_POST)) {
		foreach ($_POST as $k => $v) {
			if (isset($data[$k])) {
				$data[$k] = $v;
			}
		}
		if (!is_resource($file = fopen(FPATH, 'wb'))) {
			array_push($msg, "ERROR: Can't open ".FPATH);
			goto skip;
		}
		foreach ($data as $k => $v) {
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
	break;
}
skip:           // GOTO label to skip code in case of fatal error
                // but you still want to show website with message.
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
.msg { padding: .5em 1em; outline: 1px solid }
@media (prefers-color-scheme: dark) {
	body { background-color: #000; color: #fff }
	a { color: #ffef6e }
}
</style>
<?php foreach ($msg as $m): ?>
	<p class=msg><?=$m?></p>
<?php endforeach ?>
<?php if (in_array($page, $auth_pages)):                // Nav menu ?>
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
<?php switch($page): ?>
<?php case Page::Hash: ?>
	<h1>Hash</h1>
	<p>Create hash and salt for given password. Use created values in <code>index.php</code>.</p>
	<form>
		<input type=password name=pass placeholder=password required autofocus>
		<input type=submit>
	</form>
<?php break ?>
<?php case Page::Login: ?>
	<h1>Login</h1>
	<p>Password should be given to you by website administrator.</p>
	<form method=post>
		<input type=password name=pass placeholder=password required autofocus>
		<input type=submit value=login>
	</form>
<?php break ?>
<?php case Page::Logout: ?>
	<h1>Logout</h1>
	<p>You have been loged out.</p>
	<ul>
		<li><a href=/>home page</a></li>
		<li><a href=<?=$url_root.'login'?>>login page</a></li>
	</ul>
<?php break ?>
<?php case Page::Text: ?>
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
		<?php foreach ($data as $k => $v): ?>
			<h3 id=<?=$k?>><code><?=$k?></code></h3>
			<textarea cols=80 rows=<?=substr_count($v,"\n")+1?> name=<?=$k?>><?=$v?></textarea>
		<?php endforeach ?>
		<p><input type=submit></p>
	</form>
<?php break ?>
<?php case Page::File: ?>
	<style>
	img { display: block; max-width: 30%; border: 1px solid #000 }
	li { margin-bottom: 2em }
	</style>
	<h1>Files</h1>
	<p>Upload new files.</p>
	<form method=post enctype=multipart/form-data>
		<input type=file name=files[] multiple required>
		<input type=submit value=upload>
	</form>
	<h2>View and remove</h2>
	<form>
		<?php foreach ($data as $k => $f): ?>
			<p>
				<a href=<?=$url_root.DPATH.$f?>>
					<?=$url_root.DPATH.$f?>
					<?php if (file_has_img_ext($f)): ?>
						<?=cmsix\img(DPATH.$f, [ "sizes" => "20vw" ])?>
					<?php endif ?>
				</a>
				<label for=delete<?=$k?>>Delete</label>
				<input id=delete<?=$k?> type=checkbox name=files[] value=<?=$f?> />
			</p>
		<?php endforeach ?>
		<input type=submit name=delete value=delete />
	</form>
<?php break ?>
<?php endswitch ?>
</body>
</html>
