# ABSEL AI Experiential Library

Source for ABSEL's members-only library of AI experiential learning activities and simulations, served from absel.org behind the existing Simple Membership paywall.

```
activities/              one folder per activity (see activities/_template/README.md)
  negotiation-supplier/  working example: negotiate with an AI supplier
wp-plugin/
  absel-ai-library/      WordPress plugin that publishes and protects the library
```

## What the plugin does

- **AI Activities** post type (`/ai-activities/`) with AI Topic, Format and Discipline filters, plus duration and group-size fields.
- **Protected files.** Activity folders live in a directory the web server will not serve directly. Members reach them through `/ail-files/<folder>/…`, which checks Simple Membership first. Expired and inactive accounts are refused.
- **Student links.** Each activity page gives members a link to post in their LMS. It opens only the simulation (not instructor materials) for 14 days, stops working if the member's account lapses, and has its own daily AI allowance.
- **AI proxy.** Simulations call `/wp-json/absel-ail/v1/chat`. The server adds the Claude API key and the activity's private system prompt, enforces per-member and per-link daily limits, and records monthly token totals under **Settings → AI Library**.

## Setup (staging copy first)

Use Bluehost's staging tool (in the Bluehost dashboard, or the Bluehost plugin inside WordPress) to make a copy of the site, and do everything below there first.

1. **Install the plugin.** Zip the `wp-plugin/absel-ai-library` folder, then go to **Plugins → Add New → Upload Plugin** and activate it. Activation creates the library directory and its access-blocking `.htaccess`.
2. **Add the API key** to `wp-config.php` (Bluehost File Manager), above the "That's all, stop editing" line. Create the key at console.anthropic.com and set a monthly spend limit there:
   ```php
   define( 'ABSEL_AIL_ANTHROPIC_KEY', 'sk-ant-...' );
   ```
3. **Recommended: keep files outside the web root.** Bluehost serves some static files through a cache layer that may not honor `.htaccess`. To remove that risk, create a folder next to `public_html` (for example `/home/<account>/absel-ai-library`) and add this to `wp-config.php`:
   ```php
   define( 'ABSEL_AIL_CONTENT_DIR', '/home/<account>/absel-ai-library' );
   ```
4. **Configure** under **Settings → AI Library**: the membership levels allowed in (leave empty for all active members), the model, and the daily limits.
5. **Upload an activity.** Copy `activities/negotiation-supplier/` into the library directory (SFTP or File Manager). Export `facilitator-guide.md` to PDF first and upload the PDF; `.md` files are not served.
6. **Create the Activity entry.** Go to **AI Activities → Add New**, fill in the description from `meta.json`, and set **Activity folder** to `negotiation-supplier`. Optionally tick the Simple Membership protection box to hide the description itself from non-members.
7. **Flush permalinks** once: **Settings → Permalinks → Save**.

## Test checklist

- [ ] Logged out: the activity page shows "Log in / become a member", and `/ail-files/negotiation-supplier/index.html` redirects to the login page.
- [ ] Direct file URL is refused: `https://<site>/wp-content/absel-ai-library/negotiation-supplier/index.html` returns 403, or 404 if the files are outside the web root.
- [ ] `/ail-files/negotiation-supplier/_system-prompt.md` returns 404, even for an administrator.
- [ ] As an active member: **Launch simulation** works and Dana replies; the PDF downloads.
- [ ] As an expired member: refused.
- [ ] Student link in a private window (not logged in): the simulation works, and the facilitator PDF URL returns 403.
- [ ] Set the daily limit to 2 and confirm the third reply is refused with a friendly message, then restore it.
- [ ] If a caching plugin is active (Bluehost's included), check that a logged-out visitor never sees a member's view of an activity page.

## Cost

Settings → AI Library shows replies and tokens by month. One 25-turn negotiation re-sends the growing conversation each turn, roughly 60–100K input tokens and 3–5K output tokens in total. On the default `claude-opus-5-5` ($4 / $20 per million input / output tokens) that is about $0.35–0.55 per student, or $15–20 for a class of 40. To cut cost, switch the model to `claude-sonnet-5-5` (about half the price of the default `claude-opus-5-5`) or `claude-haiku-5-5` (much cheaper; try the role-play quality first). The spend limit you set in the Anthropic console is the hard ceiling.

## Privacy

Conversations are sent to Anthropic to generate replies and are not stored by the site. Students download their own transcripts. Every simulation should tell students not to enter personal information.
