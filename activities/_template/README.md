# Activity template

Copy this folder, rename it to the activity's slug (lowercase letters, digits and hyphens, e.g. `ai-hiring-bias-audit`), and replace the contents.

| File | Purpose | Served to |
|---|---|---|
| `meta.json` | Title, summary, topics, discipline, duration, license. Used to fill in the WordPress Activity entry. Keep it free of confidential details. | Members and student links |
| `facilitator-guide.md` | Objectives, timing, confidential info, debrief questions. Export to `facilitator-guide.pdf` before uploading. | Members (as PDF) |
| `index.html` | The simulation, if there is one. Self-contained HTML/CSS/JS that runs in the browser; relative links to other files in the folder work. | Members and student links |
| `*.pdf`, `*.docx`, `*.pptx`, `*.xlsx` | Handouts and slides. Listed automatically on the activity page. | Members only |
| `_anything` | Private notes or source files kept with the activity. | Nobody |

What gets served:
- **Web files** (`.html`, `.css`, `.js`, `.json`, images, `.mp4`) go to members and to student links.
- **Documents** (`.pdf`, `.docx`, `.pptx`, `.xlsx`, `.zip`) go to members only.
- **Everything else,** including `.md`, is never served. Neither is any file or folder whose name starts with `_` or `.`.

Remember that anything in a simulation's HTML or JavaScript can be read by a determined student. Keep answer keys in the facilitator PDF, not in the simulation code.

## Building a simulation

Simulations are plain web pages: no server code, no accounts, no external services. Keep everything in the activity folder. Store progress in `sessionStorage` so a page reload doesn't lose work, and offer a transcript or results download for debriefs. `negotiation-supplier/index.html` is a complete example of a rule-based simulation with hidden parameters, scoring and a downloadable transcript.
