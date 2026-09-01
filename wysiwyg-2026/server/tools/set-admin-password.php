<?php
/**
 * Set the admin password. Writes only a hash, to data/admin.hash.
 *
 * Requires a terminal, so run it with ssh -t:
 *   ssh -t michaeln@michaelneff.com "cd /home3/michaeln/wysiwyg.michaelneff.com && php tools/set-admin-password.php"
 *
 * Without -t there is no TTY, `stty -echo` fails, and the password would be
 * echoed in the clear as you type it — so this refuses to run rather than
 * leaking it. (It also no longer rewrites config.php: doing that with
 * preg_replace silently ate the '$2' of the bcrypt hash as a backreference.)
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib.php';

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

if (!stream_isatty(STDIN)) {
    fwrite(STDERR,
        "No TTY — your password would be echoed in the clear.\n\n" .
        "Re-run with ssh -t:\n" .
        "  ssh -t michaeln@michaelneff.com \"cd " . dirname(__DIR__) . " && php tools/set-admin-password.php\"\n");
    exit(1);
}

function prompt_hidden(string $label): string {
    echo $label;
    system('stty -echo');
    $v = rtrim((string) fgets(STDIN), "\r\n");
    system('stty echo');
    echo "\n";
    return $v;
}

$p1 = prompt_hidden('New admin password: ');
$p2 = prompt_hidden('Again: ');

if ($p1 === '' || $p1 !== $p2)  { fwrite(STDERR, "Empty or mismatched.\n"); exit(1); }
if (strlen($p1) < 10)           { fwrite(STDERR, "Use at least 10 characters.\n"); exit(1); }

$file = data_path('admin.hash');
if (file_put_contents($file, password_hash($p1, PASSWORD_DEFAULT) . "\n", LOCK_EX) === false) {
    fwrite(STDERR, "Could not write {$file}\n"); exit(1);
}
@chmod($file, 0600);

// Prove it round-trips, so a corrupted write can never go unnoticed again.
echo password_verify($p1, admin_hash())
    ? "Password set and verified. Log in at https://wysiwyg.michaelneff.com/admin/\n"
    : "WROTE BUT VERIFY FAILED — do not rely on this; tell Claude.\n";
