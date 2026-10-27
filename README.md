# ABSEL Experiential Library

Source for ABSEL's members-only library of experiential exercises and simulations for business courses, served from absel.org behind the existing Simple Membership paywall, with member submissions and editor review.

```
activities/              one folder per activity (see activities/_template/README.md)
  negotiation-supplier/  working example: negotiate with a rule-based supplier agent
schema/                  indexing rules: exercise.schema.json + INDEXING-RULES.md
author-kit/
  author-prompt.md       prompt authors run in their own AI assistant to draft a manifest
tools/
  check_library.py       validates manifests and packages; keeps rules, prompt and plugin copies in sync
  build_form.py          copies the schema and prompt into the plugin (run after changing either)
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

- **AI Activities** post type (`/ai-activities/`) with AI Topic, Format and Discipline filters, plus duration and group-size fields.
- **Protected files.** Activity folders live in a directory the web server will not serve directly. Members reach them through `/ail-files/<folder>/…`, which checks Simple Membership first. Expired and inactive accounts are refused.
- **Student links.** Each activity page gives members a link to post in their LMS. It opens only the simulation (not instructor materials) for 14 days, needs no student account, and stops working if the member's account lapses.
- **Member submissions.** The `[absel_submit_exercise]` shortcode puts the submission page on any WordPress page. Anything from an idea to a finished simulation (including one on GitHub) is welcome. Members give their own AI assistant whatever they have (a description, a manuscript, slides or files) along with the built-in prompt, and paste its reply into the page. The assistant fills in everything it can and lists what it had to guess. **Confirming or correcting those guesses is the only thing the author has to do**; every other field is optional, and listed files that aren't attached don't block a submission. Files upload one at a time with progress, so large packages stay under PHP's request limits. The server checks every manifest against the schema again. Members see their submissions' status and editors' messages on the same page, and can revise a returned submission without re-uploading unchanged files.
- **Review queue.** **AI Activities → Submissions** lists submissions by status, with a count of those waiting. Each one shows its details, automatic checks (third-party material needing permission, web files that will run on the site, folder name clashes, untested exercises) and its files, which always download rather than open. Editors can:
  - **Accept.** The files and `manifest.json` are copied into the library and a *draft* activity is created for the editor to check and publish. A revision of an accepted exercise updates its files instead.
  - **Return for changes**, with a message.
  - **Decline**, with a message.

  Editors and authors are emailed at each step. Unfinished uploads are deleted after two days. Submitted files are kept under `_submissions/` in the library directory, which is never served.

## Setup (staging copy first)

Use Bluehost's staging tool (in the Bluehost dashboard, or the Bluehost plugin inside WordPress) to make a copy of the site, and do everything below there first.

1. **Install the plugin.** Download `absel-ai-library.zip` (or zip the `wp-plugin/absel-ai-library` folder). Go to **Plugins → Add New Plugin → Upload Plugin**, install it and activate it.
2. **Keep files outside the web root (recommended).** Bluehost serves some static files through a cache layer that may not honor `.htaccess`. Create a folder next to `public_html` (for example `/home/<account>/absel-ai-library`) and add this to `wp-config.php`, above the "That's all, stop editing" line:
   ```php
   define( 'ABSEL_AIL_CONTENT_DIR', '/home/<account>/absel-ai-library' );
   ```
   Without this line, the plugin uses `wp-content/absel-ai-library` and blocks direct access with `.htaccess`.
3. **Configure** under **Settings → AI Library**: check the status items, choose the membership levels allowed in (leave empty for all active members), and enter the editors' email addresses for new submissions.
4. **Create the submission page.** Add a page (for example "Submit an exercise") containing the shortcode `[absel_submit_exercise]`, publish it and view it once so the plugin records its address for emails. Link to it from the members' menu.
5. **Allow large enough uploads.** The status list shows the largest file members can upload. Bluehost's default is often too small for videos and slide decks: in **cPanel → MultiPHP INI Editor**, set `upload_max_filesize` to `100M` and `post_max_size` to `110M`.
6. **Editors** need the WordPress Editor (or Administrator) role to see **AI Activities → Submissions**.
7. **Upload an activity** (or submit it through the new page). Copy `activities/negotiation-supplier/` into the library directory (SFTP or File Manager). Export `facilitator-guide.md` to PDF first and upload the PDF; `.md` files are not served.
8. **Create the Activity entry** (accepting a submission does this for you). Go to **AI Activities → Add New**, fill in the description from `manifest.json`, and set **Activity folder** to `negotiation-supplier`. Optionally tick the Simple Membership protection box to hide the description itself from non-members.
9. **Flush permalinks** if `/ail-files/` or `/ail-submit/` return "not found": **Settings → Permalinks → Save Changes**.

## Test checklist

- [ ] Logged out: the activity page shows "Log in / become a member", and `/ail-files/negotiation-supplier/index.html` redirects to the login page.
- [ ] Direct file URL is refused: `https://<site>/wp-content/absel-ai-library/negotiation-supplier/index.html` returns 403, or 404 if the files are outside the web root.
- [ ] As an active member: **Launch simulation** works and the PDF downloads.
- [ ] As an expired member: refused.
- [ ] Student link in a private window (not logged in): the simulation works, and the facilitator PDF URL returns 403.
- [ ] If a caching plugin is active (Bluehost's included), check that a logged-out visitor never sees a member's view of an activity page or the submission page.
- [ ] Submission page logged out: "Log in or become a member". As a member: the form appears inside the page, and **Your submissions** appears after a first submission.
- [ ] Submit a small test exercise as a member: a confirmation email arrives, the editors' address gets a notification, and it appears under **AI Activities → Submissions**.
- [ ] Return it with a message: the member sees the message on the submission page and can revise it, keeping unchanged files.
- [ ] Accept the revision: a draft activity appears with the files in place. Publish it and check that members can download the files.
- [ ] Try a file larger than the upload limit: the form says it's too large before uploading.

## Security notes

- Accepted HTML and JavaScript files run on absel.org for every member who opens them. Editors should review simulation code before accepting; the review screen flags these files.
- Submitted files are never shown in the browser inside WordPress admin; they always download.

## Privacy

Simulations run entirely in the student's browser. Nothing they type is sent to the site or any other service, and progress is kept only in that browser tab. Students download their own transcripts.
