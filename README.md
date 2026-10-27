# ABSEL AI Experiential Library

Source for ABSEL's members-only library of AI experiential learning activities and simulations, served from absel.org behind the existing Simple Membership paywall.

```
activities/              one folder per activity (see activities/_template/README.md)
  negotiation-supplier/  working example: negotiate with a rule-based supplier agent
wp-plugin/
  absel-ai-library/      WordPress plugin that publishes and protects the library
```

Simulations are self-contained web pages that run in the browser. Nothing calls an outside AI service, so there's no API key, no usage cost and no student data leaving the page. The earlier version, with a live Claude-powered simulation, is kept under the git tag `ai-version-0.1.0`.

## What the plugin does

- **AI Activities** post type (`/ai-activities/`) with AI Topic, Format and Discipline filters, plus duration and group-size fields.
- **Protected files.** Activity folders live in a directory the web server will not serve directly. Members reach them through `/ail-files/<folder>/…`, which checks Simple Membership first. Expired and inactive accounts are refused.
- **Student links.** Each activity page gives members a link to post in their LMS. It opens only the simulation (not instructor materials) for 14 days, needs no student account, and stops working if the member's account lapses.

## Setup (staging copy first)

Use Bluehost's staging tool (in the Bluehost dashboard, or the Bluehost plugin inside WordPress) to make a copy of the site, and do everything below there first.

1. **Install the plugin.** Download `absel-ai-library.zip` (or zip the `wp-plugin/absel-ai-library` folder). Go to **Plugins → Add New Plugin → Upload Plugin**, install it and activate it.
2. **Keep files outside the web root (recommended).** Bluehost serves some static files through a cache layer that may not honor `.htaccess`. Create a folder next to `public_html` (for example `/home/<account>/absel-ai-library`) and add this to `wp-config.php`, above the "That's all, stop editing" line:
   ```php
   define( 'ABSEL_AIL_CONTENT_DIR', '/home/<account>/absel-ai-library' );
   ```
   Without this line, the plugin uses `wp-content/absel-ai-library` and blocks direct access with `.htaccess`.
3. **Configure** under **Settings → AI Library**: check that both status items are green, and choose the membership levels allowed in (leave empty for all active members).
4. **Upload an activity.** Copy `activities/negotiation-supplier/` into the library directory (SFTP or File Manager). Export `facilitator-guide.md` to PDF first and upload the PDF; `.md` files are not served.
5. **Create the Activity entry.** Go to **AI Activities → Add New**, fill in the description from `meta.json`, and set **Activity folder** to `negotiation-supplier`. Optionally tick the Simple Membership protection box to hide the description itself from non-members.
6. **Flush permalinks** once: **Settings → Permalinks → Save Changes**.

## Test checklist

- [ ] Logged out: the activity page shows "Log in / become a member", and `/ail-files/negotiation-supplier/index.html` redirects to the login page.
- [ ] Direct file URL is refused: `https://<site>/wp-content/absel-ai-library/negotiation-supplier/index.html` returns 403, or 404 if the files are outside the web root.
- [ ] As an active member: **Launch simulation** works and the PDF downloads.
- [ ] As an expired member: refused.
- [ ] Student link in a private window (not logged in): the simulation works, and the facilitator PDF URL returns 403.
- [ ] If a caching plugin is active (Bluehost's included), check that a logged-out visitor never sees a member's view of an activity page.

## Privacy

Simulations run entirely in the student's browser. Nothing they type is sent to the site or any other service, and progress is kept only in that browser tab. Students download their own transcripts.
