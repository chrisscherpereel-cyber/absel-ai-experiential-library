"""Build a one-click WordPress Playground link for trying the plugin.

Playground (playground.wordpress.net) runs a throwaway WordPress site entirely in
the browser. The link carries a blueprint that:

- installs and activates Simple Membership from wordpress.org;
- installs and activates this plugin from the working copy of wp-plugin/;
- turns on pretty permalinks and flushes the rewrite rules;
- creates a membership level and an active test member;
- creates the "Submit an exercise" page with [absel_submit_exercise];
- opens that page, logged in as the Playground administrator.

The repository is private, so Playground can't download the plugin from GitHub.
Instead the plugin is zipped and embedded in the link itself. The part after "#"
is never sent to any server, so the code stays in the browser. Nothing persists:
closing or reloading the tab discards the site (Playground's "Save" menu can keep
it in that browser).

The test member's login is below (TEST_MEMBER). It exists only in Playground.

Usage: py tools/build_playground.py
Writes dist/playground.html (open it and click the link) and prints the link's size.
"""
import base64
import io
import json
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = ROOT / "wp-plugin" / "absel-ai-library"
OUT = ROOT / "dist" / "playground.html"

# Test-only account inside Playground; never use these values on a real site.
TEST_MEMBER = {"user_name": "testmember", "password": "absel-playground-test", "email": "testmember@example.com",
               "first_name": "Test", "last_name": "Member"}
SUBMIT_SLUG = "submit-an-exercise"


def plugin_zip():
    """The plugin folder as a zip, laid out like the release zip (absel-ai-library/...)."""
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for path in sorted(PLUGIN.rglob("*")):
            if path.is_file():
                z.write(path, "absel-ai-library/" + path.relative_to(PLUGIN).as_posix())
    return buf.getvalue()


SETUP_PHP = r"""<?php
require_once '/wordpress/wp-load.php';

// Pretty permalinks, so /ail-files/ and /ail-submit/ resolve.
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );

// Fill a Simple Membership row, giving NOT NULL columns without a default a neutral value.
function absel_pg_row( $table, $values ) {
	global $wpdb;
	foreach ( $wpdb->get_results( "SHOW COLUMNS FROM $table" ) as $col ) {
		if ( array_key_exists( $col->Field, $values ) || 'auto_increment' === strtolower( (string) $col->Extra ) ) {
			continue;
		}
		if ( 'NO' === $col->Null && null === $col->Default ) {
			$values[ $col->Field ] = preg_match( '/int|decimal|float|double/i', $col->Type ) ? 0 : '';
		}
	}
	$wpdb->insert( $table, $values );
	return (int) $wpdb->insert_id;
}

global $wpdb;
$levels  = $wpdb->prefix . 'swpm_membership_tbl';
$members = $wpdb->prefix . 'swpm_members_tbl';

// A level that never expires (level 1 is Simple Membership's own protection record).
$level = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $levels WHERE alias = %s", 'ABSEL Member' ) );
if ( ! $level ) {
	$level = absel_pg_row( $levels, array(
		'alias'                      => 'ABSEL Member',
		'role'                       => 'subscriber',
		'subscription_period'        => '0',
		'subscription_duration_type' => 0,
	) );
}

$m = json_decode( '__MEMBER__', true );
if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT member_id FROM $members WHERE user_name = %s", $m['user_name'] ) ) ) {
	$hash = class_exists( 'SwpmUtils' ) && method_exists( 'SwpmUtils', 'encrypt_password' )
		? SwpmUtils::encrypt_password( $m['password'] )
		: wp_hash_password( $m['password'] );
	absel_pg_row( $members, array(
		'user_name'           => $m['user_name'],
		'first_name'          => $m['first_name'],
		'last_name'           => $m['last_name'],
		'password'            => $hash,
		'email'               => $m['email'],
		'membership_level'    => $level,
		'account_state'       => 'active',
		'member_since'        => gmdate( 'Y-m-d' ),
		'subscription_starts' => gmdate( 'Y-m-d' ),
	) );
}

// The submission page, recorded as the plugin would on its first view.
$page = get_page_by_path( '__SLUG__' );
if ( ! $page ) {
	$id = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Submit an exercise',
		'post_name'    => '__SLUG__',
		'post_content' => '[absel_submit_exercise]',
	) );
	update_option( 'absel_ail_submit_page', get_permalink( $id ) );
}

flush_rewrite_rules();
"""


def blueprint():
    setup = SETUP_PHP.replace("__MEMBER__", json.dumps(TEST_MEMBER)).replace("__SLUG__", SUBMIT_SLUG)
    zip_b64 = base64.b64encode(plugin_zip()).decode("ascii")
    return {
        "$schema": "https://playground.wordpress.net/blueprint-schema.json",
        "landingPage": f"/{SUBMIT_SLUG}/",
        "preferredVersions": {"php": "8.3", "wp": "latest"},
        "features": {"networking": True},
        "steps": [
            {"step": "login", "username": "admin", "password": "password"},
            {"step": "setSiteOptions", "options": {"blogname": "ABSEL Library (Playground test)"}},
            {"step": "installPlugin", "pluginData": {"resource": "wordpress.org/plugins", "slug": "simple-membership"},
             "options": {"activate": True}},
            {"step": "runPHP", "code": "<?php file_put_contents( '/tmp/absel-ai-library.zip', base64_decode( '"
             + zip_b64 + "' ) );"},
            {"step": "unzip", "zipFile": {"resource": "vfs", "path": "/tmp/absel-ai-library.zip"},
             "extractToPath": "/wordpress/wp-content/plugins"},
            {"step": "activatePlugin", "pluginName": "ABSEL AI Experiential Library",
             "pluginPath": "absel-ai-library/absel-ai-library.php"},
            {"step": "runPHP", "code": setup},
        ],
    }


def main():
    data = json.dumps(blueprint(), separators=(",", ":"))
    link = "https://playground.wordpress.net/#" + base64.b64encode(data.encode("utf-8")).decode("ascii")
    OUT.parent.mkdir(exist_ok=True)
    m = TEST_MEMBER
    version = re.search(r"define\( 'ABSEL_AIL_VERSION', '([^']+)'", (PLUGIN / "absel-ai-library.php").read_text(encoding="utf-8")).group(1)
    page = f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ABSEL Playground</title>
<style>body{{font:16px/1.5 system-ui,sans-serif;max-width:680px;margin:40px auto;padding:0 16px;color:#1d2327;background:#fff}}
a.go{{display:inline-block;background:#2271b1;color:#fff;padding:10px 18px;border-radius:4px;text-decoration:none;font-weight:600}}
code{{background:#f0f0f1;padding:1px 4px;border-radius:3px}}</style></head>
<body>
<h1>Try the ABSEL plugin in WordPress Playground</h1>
<p>Plugin version {version}.</p>
<p><a class="go" href="{link}" target="_blank" rel="noopener">Open the test site</a></p>
<p>A temporary WordPress site starts in your browser (about a minute) with Simple Membership, this plugin,
the <em>Submit an exercise</em> page and pretty permalinks. It opens on the submission page, logged in as the administrator.
Nothing is sent to absel.org, and the site disappears when you close or reload the tab.</p>
<h2>Testing as a member</h2>
<ol>
<li>Log out of WordPress (top-right menu).</li>
<li>Go to <code>/membership-login/</code> and log in as <code>{m['user_name']}</code> with the password <code>{m['password']}</code>
(a test account that exists only in Playground).</li>
<li>Open <code>/{SUBMIT_SLUG}/</code> and submit an exercise.</li>
<li>Log back in as the administrator at <code>/wp-login.php</code> (<code>admin</code> / <code>password</code>) and review it under
<strong>AI Activities → Submissions</strong>. Emails aren't sent in Playground.</li>
</ol>
<p><small>Built from wp-plugin/absel-ai-library by tools/build_playground.py. Get a current copy from the latest release or the Actions tab (Build Playground link), or rebuild it after changing the plugin.</small></p>
</body></html>
"""
    with open(OUT, "w", encoding="utf-8", newline="\n") as f:
        f.write(page)
    print(f"wrote {OUT.relative_to(ROOT)} (link: {len(link):,} characters)")


if __name__ == "__main__":
    main()
