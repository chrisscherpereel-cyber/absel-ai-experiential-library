# Activity template

Copy this folder, rename it to the activity's slug (lowercase letters, digits and hyphens, e.g. `ai-hiring-bias-audit`), and replace the contents.

| File | Purpose | Served to |
|---|---|---|
| `meta.json` | Title, summary, topics, discipline, duration, license. Used to fill in the WordPress Activity entry. | Members |
| `facilitator-guide.md` | Objectives, timing, confidential info, debrief questions. Export to `facilitator-guide.pdf` before uploading. | Members (as PDF) |
| `index.html` | The simulation, if there is one. Self-contained HTML/CSS/JS; relative links to other files in the folder work. | Members and student links |
| `_system-prompt.md` | The AI's instructions, for simulations that talk to Claude. | Nobody (server only) |
| `*.pdf`, `*.docx`, `*.pptx`, `*.xlsx` | Handouts and slides. Listed automatically on the activity page. | Members only |

Files or folders whose names start with `_` or `.` are never served.

## Calling the AI from a simulation

When the plugin serves `index.html`, it adds `window.ABSEL_AIL` with `endpoint`, `activity`, `token`, `classPass` and `restNonce`. POST to the endpoint:

```js
fetch(ABSEL_AIL.endpoint, {
  method: 'POST',
  credentials: 'same-origin',
  headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': ABSEL_AIL.restNonce },
  body: JSON.stringify({
    activity: ABSEL_AIL.activity,
    token: ABSEL_AIL.token,
    classPass: ABSEL_AIL.classPass,
    messages: [{ role: 'user', content: 'Hello' }] // alternating user/assistant, ending with user
  })
}).then(r => r.json()); // { reply, remaining }
```

`negotiation-supplier/index.html` is a complete working example, including a preview mode for opening the file locally without the server.
