# SELAR: Simulation & Experiential Learning Activities Repository

Source for SELAR, ABSEL's members-only repository of simulations and experiential learning activities for business courses, served from absel.org behind the existing Simple Membership paywall, with member submissions and editor review.

```
activities/              one folder per activity (see activities/_template/README.md)
  negotiation-supplier/  working example: negotiate with a rule-based supplier agent
schema/                  indexing rules: exercise.schema.json + INDEXING-RULES.md
author-kit/
  author-prompt.md       prompt authors run in their own AI assistant to draft a manifest
.github/workflows/
  release.yml            publishes the plugin (and its Playground test page) as a GitHub release when a version tag is pushed
  playground.yml         builds the Playground test page on demand (Actions tab)
tools/
  check_library.py       validates manifests and packages; keeps rules, prompt and plugin copies in sync
  build_form.py          copies the schema and prompt into the plugin (run after changing either)
  build_playground.py    builds dist/playground.html, a one-click test site in WordPress Playground
wp-plugin/
  absel-ai-library/      WordPress plugin that publishes and protects the library
    assets/submit.html   submission page: copy the prompt, paste the AI's reply, edit, submit
```

Every activity has a `manifest.json` describing it (see [the indexing rules](schema/INDEXING-RULES.md)). After changing an activity, the schema, the rules or the prompt, run:

```
pip install jsonschema
py tools/check_library.py
```

Simulations are self-contained web pages that run in the browser. Nothing calls an outside AI service, so there's no API key, no usage cost and no student data leaving the page. The earlier version, with a live Claude-powered simulation, is kept under the git tag `ai-version-0.1.0`.

## What the plugin does

- **SELAR** activities (the **SELAR** admin menu; pages at `/ai-activities/`) with AI Topic, Format and Discipline filters, plus duration and group-size fields.
- **Protected files.** Activity folders live in a directory the web server will not serve directly. Members reach them through `/ail-files/<folder>/…`, which checks Simple Membership first. Expired and inactive accounts are refused.
- **Student links.** Each activity page gives members a link to post in their LMS. It opens only the simulation (not instructor materials) for 14 days, needs no student account, and stops working if the member's account lapses.
- **Member submissions.** The `[absel_submit_exercise]` shortcode puts the submission page on any WordPress page. Anything from an idea to a finished simulation (including one on GitHub) is welcome. Members give their own AI assistant whatever they have (a description, a manuscript, slides or files) along with the built-in prompt, and paste its reply into the page. The assistant fills in everything it can and lists what it had to guess. **Confirming or correcting those guesses is the only thing the author has to do**; every other field is optional, and listed files that aren't attached don't block a submission. Files upload one at a time with progress, so large packages stay under PHP's request limits. The server checks every manifest against the schema again. Members see their submissions' status and editors' messages on the same page, and can revise a returned submission without re-uploading unchanged files.
- **Review queue.** **SELAR → Submissions** lists submissions by status, with a count of those waiting. Each one shows its details (including its stage and the assistant's guesses the author checked), automatic checks (files listed but not received, possible student information, third-party material needing permission, web files that will run on the site, folder name clashes) and its files, which always download rather than open. Editors can:
  - **Accept.** The files and `manifest.json` are copied into the library and a *draft* activity is created for the editor to check and publish. A revision of an accepted exercise updates its files instead.
  - **Return for changes**, with a message.
  - **Decline**, with a message.

  Editors and authors are emailed at each step. Unfinished uploads are deleted after two days. Submitted files are kept under `_submissions/` in the library directory, which is never served.

## Trying it in WordPress Playground

To try the plugin without touching absel.org, get the one-click test page, `playground.html`, in any of these ways:

- **From a release:** each [release](../../releases/latest) has `playground.html` next to `absel-ai-library.zip`, built from that version.
- **From the latest code:** on the **Actions** tab, choose **Build Playground link → Run workflow**. When the run finishes (about a minute), open it and download **playground.html** under *Artifacts*. Downloads are kept for 30 days.
- **On your computer:** `py tools/build_playground.py` writes `dist/playground.html`.

Open `playground.html` and click **Open the test site**. A temporary WordPress site starts in the browser (WordPress Playground) with Simple Membership, this plugin, a membership level, an active test member, the *Submit an exercise* page and pretty permalinks. It opens on the submission page, logged in as the administrator (`admin` / `password`). The test member's login is in `TEST_MEMBER` at the top of `tools/build_playground.py`, and `dist/playground.html` shows it with the steps for testing as a member. These accounts exist only inside Playground.

The plugin is carried inside the link, because Playground can't download from this private repository. The part of a link after `#` isn't sent to any server. Keep the page private: anyone who has it has a copy of the plugin's code. The site is thrown away when the tab is closed or reloaded, and Playground doesn't send emails.

## Setup (staging copy first)

Use Bluehost's staging tool (in the Bluehost dashboard, or the Bluehost plugin inside WordPress) to make a copy of the site, and do everything below there first.

1. **Install the plugin (once).** Download `absel-ai-library.zip` from the repository's [latest release](../../releases/latest). Go to **Plugins → Add New Plugin → Upload Plugin**, install it and activate it. After this, updates come through **Dashboard → Updates** (see [Updating the plugin](#updating-the-plugin)).
2. **Keep files outside the web root (recommended).** Bluehost serves some static files through a cache layer that may not honor `.htaccess`. Create a folder next to `public_html` (for example `/home/<account>/absel-ai-library`) and add this to `wp-config.php`, above the "That's all, stop editing" line:
   ```php
   define( 'ABSEL_AIL_CONTENT_DIR', '/home/<account>/absel-ai-library' );
   ```
   Without this line, the plugin uses `wp-content/absel-ai-library` and blocks direct access with `.htaccess`.
3. **Configure** under **Settings → SELAR**: check the status items, choose the membership levels allowed in (leave empty for all active members), and enter the editors' email addresses for new submissions.
4. **Create the submission page.** Add a page (for example "Submit an exercise") containing the shortcode `[absel_submit_exercise]`, publish it and view it once so the plugin records its address for emails. Link to it from the members' menu.
5. **Allow large enough uploads.** The status list shows the largest file members can upload. Bluehost's default is often too small for videos and slide decks: in **cPanel → MultiPHP INI Editor**, set `upload_max_filesize` to `100M` and `post_max_size` to `110M`.
6. **Editors** need the WordPress Editor (or Administrator) role to see **SELAR → Submissions**.
7. **Upload an activity** (or submit it through the new page). Copy `activities/negotiation-supplier/` into the library directory (SFTP or File Manager). Export `facilitator-guide.md` to PDF first and upload the PDF; `.md` files are not served.
8. **Create the Activity entry** (accepting a submission does this for you). Go to **SELAR → Add New Activity**, fill in the description from `manifest.json`, and set **Activity folder** to `negotiation-supplier`. Optionally tick the Simple Membership protection box to hide the description itself from non-members.
9. **Flush permalinks** if `/ail-files/` or `/ail-submit/` return "not found": **Settings → Permalinks → Save Changes**.

## Updating the plugin

The site updates the plugin from this repository's GitHub releases, through **Dashboard → Updates**, like any other plugin. No zip uploads are needed after the first install.

**One-time setup on each site** (staging and live). The repository is private, so the site needs a read-only access token:

1. On GitHub, go to **your profile picture → Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token**.
2. Name it, for example "absel.org plugin updates", and pick an expiration date (one year is sensible; set a reminder).
3. Under **Repository access**, choose **Only select repositories** and pick `absel-ai-experiential-library`.
4. Under **Permissions → Repository permissions**, set **Contents** to **Read-only**. Leave everything else as is.
5. Generate the token and copy it.
6. Add this line to the site's `wp-config.php` (Bluehost File Manager), above "That's all, stop editing":
   ```php
   define( 'ABSEL_AIL_GITHUB_TOKEN', 'github_pat_…' );
   ```
7. Open **Settings → SELAR**. The updates line should say "Up to date" or offer a new version. If it shows an error, it explains what's wrong, for example a mistyped or expired token.

The token can only read this one repository. Keep it in `wp-config.php` only, never in the code or on GitHub. When it expires, generate a new one and replace the line.

**Publishing a new version:**

1. Change the version in both places at the top of `wp-plugin/absel-ai-library/absel-ai-library.php`: the `Version:` line and `ABSEL_AIL_VERSION`. Use a higher number, for example `0.5.0`.
2. Commit, then push the commit and a matching tag:
   ```
   git tag v0.5.0
   git push origin main v0.5.0
   ```
3. GitHub Actions (`.github/workflows/release.yml`) checks the library, builds `absel-ai-library.zip` and publishes the release. Follow it under the repository's **Actions** tab. It refuses to publish if the tag and the version in the file don't match.
4. The site notices within six hours. To check straight away, use **Check for updates now** on **Settings → SELAR**. Then update from **Dashboard → Updates**. Test on staging first.

To roll back, download an earlier release's zip and upload it with **Plugins → Add New Plugin → Upload Plugin → Replace current with uploaded**.

## Test checklist

- [ ] Logged out: the activity page shows "Log in / become a member", and `/ail-files/negotiation-supplier/index.html` redirects to the login page.
- [ ] Direct file URL is refused: `https://<site>/wp-content/absel-ai-library/negotiation-supplier/index.html` returns 403, or 404 if the files are outside the web root.
- [ ] As an active member: **Launch simulation** works and the PDF downloads.
- [ ] As an expired member: refused.
- [ ] Student link in a private window (not logged in): the simulation works, and the facilitator PDF URL returns 403.
- [ ] If a caching plugin is active (Bluehost's included), check that a logged-out visitor never sees a member's view of an activity page or the submission page.
- [ ] Submission page logged out: "Log in or become a member". As a member: the form appears inside the page, and **Your submissions** appears after a first submission.
- [ ] Submit a small test exercise as a member: a confirmation email arrives, the editors' address gets a notification, and it appears under **SELAR → Submissions**.
- [ ] Return it with a message: the member sees the message on the submission page and can revise it, keeping unchanged files.
- [ ] Accept the revision: a draft activity appears with the files in place. Publish it and check that members can download the files.
- [ ] Try a file larger than the upload limit: the form says it's too large before uploading.

## Security notes

- Accepted HTML and JavaScript files run on absel.org for every member who opens them. Editors should review simulation code before accepting; the review screen flags these files.
- Submitted files are never shown in the browser inside WordPress admin; they always download.

## Privacy

Simulations run entirely in the student's browser. Nothing they type is sent to the site or any other service, and progress is kept only in that browser tab. Students download their own transcripts.
