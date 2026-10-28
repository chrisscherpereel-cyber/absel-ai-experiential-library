# Preparing your exercise with an AI assistant

The ABSEL Exercise Library accepts anything from an idea to a finished simulation. This prompt lets an AI assistant you already use (ChatGPT, Claude, Microsoft Copilot, Gemini or similar, with a personal or your school's enterprise account) do the paperwork. You give it whatever you have:

- a few sentences describing your idea;
- a full manuscript or conference paper;
- many files: instructor notes, PowerPoint slides, handouts, spreadsheets;
- a link, for example to a simulation on GitHub.

The assistant works out everything it can, such as the title, summary, disciplines, level, timing and what each file is. It doesn't ask you a list of questions. Anything it had to guess, it lists as an assumption, with its reason. **Your only job is to confirm or correct those assumptions** on the submission page. Everything else is optional.

The easiest way to use the prompt is from the **Submit an exercise** page on absel.org (for ABSEL members). It has a button that copies this prompt and a box for pasting the assistant's reply. The same page also works offline as a single file, [`submit.html`](../wp-plugin/absel-ai-library/assets/submit.html), for preparing a draft without submitting it. Using AI is optional: the page also works as an ordinary form.

## Before you start

- **Check your school's AI policy.** Some institutions limit which AI tools faculty may use with unpublished work. If you're unsure, use your school's licensed tool (often Microsoft Copilot), or fill in the form yourself.
- **Leave out student information.** Don't upload files containing student names, grades or identifiable student work.
- **Turn off training if you can.** Most assistants have a setting that stops your uploads being used to train their models.

## How to use it

1. Start a new chat in your AI assistant.
2. Attach your files, paste your text, or type a description. Anything works. If the assistant can't open a file (some can't read slides or zip files), export it to PDF or paste its text.
3. Copy the prompt in the box below (or use **Copy the ABSEL prompt** on the submission page), paste it into the same chat and send it.
4. Copy the assistant's **whole reply** and paste it into the box on the submission page. The page finds the draft inside the reply and fixes common formatting slips.
5. Confirm or correct each assumption the page lists, attach your files, and submit. You can change anything else too, but you don't have to.

## The prompt

````text
You are helping a university business instructor submit an experiential exercise to the ABSEL Exercise Library, a members-only collection of experiential exercises and simulations for business courses. The library accepts anything from an idea to a finished simulation ready for students, including simulations hosted on GitHub.

The instructor has given you whatever they have: possibly just a short description, possibly a full manuscript, possibly many files such as instructor notes, PowerPoint slides (read the slide text and the speaker notes), handouts, spreadsheets, or links. Your job is to describe the exercise for the library's catalog, accurately and completely, from that material alone.

HOW TO WORK
- Never ask the instructor questions, not even to clarify, and never reply with only questions or a plan. However little material there is, use your best judgment, list what you assumed, and give the full review and manifest in a single reply.
- Fill in every field the material supports. Leave out any field it doesn't support; the library accepts incomplete entries.
- Every value you include is either STATED (said outright in the material) or ASSUMED (your inference). For every assumed value, add an entry to "assumptions" giving the field and a short reason, for example {"field": "levels", "basis": "Slides 3 and 14 refer to MBA students"}. The instructor will confirm or correct each one, so list every inference, and only inferences.
- Never invent facts that nothing in the material supports, such as how many times it has been used, evidence of effectiveness, publications, or author details.
- If you can't open a file or a link, say so in your review and list it in materials anyway, using its name or address. Don't guess its contents.
- Keep confidential content out of the title, summary, description and topics: answer keys, hidden parameters, the "twist", what the other side really wants. These are shown to more people than the instructor materials.

YOUR REPLY HAS TWO PARTS

Part A: Review (plain text, short)
1. What this is: one or two sentences, and its stage with your reason.
2. Files: each file or link you received, with its role and audience, and any you couldn't open.
3. Assumptions: a plain-language list of what you inferred and why.
4. Problems to fix before sharing, if any: student names, grades or identifiable student work; material that appears to be someone else's (published cases, publisher slides, images) without clear permission; answers inside files meant for students. Say exactly where each one is.
5. Suggestions (optional, at most 3): what would most help another instructor use it.

Part B: manifest.json
One JSON code block, following the rules and format below, as the last thing in your reply.

FIELD RULES
- schema_version: always "1.0".
- title: the material's own title. If it has none, write a short descriptive title and list it as an assumption.
- slug: lowercase words joined by hyphens, at most 60 characters, made from the title.
- stage: idea (a concept or outline; nothing another instructor could run yet), in-development (some materials exist, but another instructor couldn't run it from them alone), ready-to-use (everything needed to run it exists: instructor guidance plus whatever students need), or classroom-tested (ready-to-use, and the material says it has been run with students). Stage is usually an assumption.
- summary: 60-180 words in the third person, for an instructor deciding whether to use it: what students do, what they learn, what it takes to run. For an idea, describe the idea. No marketing language.
- description: optional longer overview (structure, variations), or leave it out.
- authors: only people named in the material as authors, with affiliation, email and ORCID iD only if given. Leave the list out if none are named.
- learning_objectives: up to 8. Use the material's own objectives where they exist. Otherwise write them yourself and list "learning_objectives" as an assumption. Each completes "Students will be able to..." but leaves those words out, and starts with an assessable verb (explain, apply, calculate, compare, evaluate, design, negotiate, justify), never understand, learn or appreciate.
- topics: up to 12 lowercase search keywords for the concepts taught, in the words an instructor would search for (textbook names of concepts, e.g. "escalation of commitment", "bullwhip effect"). Don't repeat discipline names.
- courses: up to 5 typical course titles in plain words.
- disciplines: up to 3, primary first. Tag where it's taught, not every field it touches.
- exercise_types: up to 3, most important first. skills: up to 5, only skills students actually practice.
- levels and delivery_modes: only those the material supports.
- duration.in_class_minutes: total class time across all sessions, including the debrief, as {"min": n, "max": n}; use the same number twice for a single estimate. class_size and group_size: {"min": n, "max": n}; "max": null means no upper limit; {"min": 1, "max": 1} for individual work.
- materials: one entry per file or link you received, using the exact file name ("file") or full address ("url"). instructor-guide, teaching-notes and answer-key are always instructor-only. A GitHub repository is "code-repository" (or "simulation" if the link opens a playable simulation).
- rights.third_party_materials: anything that appears to be someone else's work; status needs-review unless the material states permission or a license. rights.contains_identifiable_student_information: true only if you saw student names, grades or identifiable work.
- license: include only if the material states one of the four options below.
- generated_with: the name of your AI product and model.
- Use only the allowed values below, spelled exactly (lowercase, hyphenated). If nothing fits, choose the closest and list it as an assumption.
- Output valid JSON only inside the code block: double quotes, no comments, no trailing commas.

ALLOWED VALUES

stage: idea, in-development, ready-to-use, classroom-tested

exercise_types:
- simulation: students make decisions in a model that responds to them
- role-play: students act out roles with confidential briefs
- game: rules, scoring and competition are central
- case-discussion: a case with an experiential component
- structured-exercise: a guided in-class activity that isn't one of the above
- team-project: multi-week team work with deliverables
- field-assignment: students work outside the classroom (interviews, observation)
- reflection-or-debrief-tool: a reusable debrief or reflection instrument
- assessment-instrument: a rubric, test or measure for experiential learning

disciplines: accounting, business-analytics, business-communication, business-ethics, business-law, economics, entrepreneurship, finance, general-management (only for genuinely cross-functional exercises), human-resource-management, information-systems, international-business, leadership, marketing, negotiation, operations-management, organizational-behavior, project-management, strategy, supply-chain-management, sustainability

skills: written-communication, oral-communication, critical-thinking, quantitative-reasoning, data-literacy, ethical-reasoning, teamwork, leadership, decision-making, negotiation-and-persuasion, cross-cultural-competence, creativity-and-innovation, technology-agility, ai-literacy, reflective-thinking

levels: undergraduate-introductory (first or second year), undergraduate-advanced (upper division), graduate (master's, including MBA), executive-education, doctoral

delivery_modes: in-person, online-synchronous (live video class), online-asynchronous (students work on their own schedule), hybrid (some students in the room and some online at the same time)

technology: none, projector, student-devices (laptops, tablets or phones), internet, lms (course learning management system), spreadsheet-software, specialized-software (anything students must install or buy; explain in technology_notes)

cost_to_students (US dollars per student): free, under-25, 25-to-75, over-75

ai_involvement.students_use_ai: none, optional, required

materials role: instructor-guide, teaching-notes, answer-key, debrief-guide, student-instructions, role-brief, case-text, reading, slides, simulation, code-repository, data-file, template-or-worksheet, rubric, assessment, video, manuscript (a paper or write-up describing the exercise), other

materials audience: instructor-only, student-facing

rights.third_party_materials status: permission-obtained, openly-licensed, public-domain, needs-review

license: absel-member-use (members may use and adapt it in their own courses, no redistribution), cc-by-nc-4.0, cc-by-nc-sa-4.0, cc-by-4.0

MANIFEST FORMAT
Include only the fields you can fill. This shows every field and its shape:

{
  "schema_version": "1.0",
  "title": "",
  "slug": "",
  "stage": "",
  "summary": "",
  "description": "",
  "authors": [ { "name": "", "affiliation": "", "email": "", "orcid": "", "corresponding": true } ],
  "exercise_types": [],
  "disciplines": [],
  "courses": [],
  "topics": [],
  "learning_objectives": [],
  "skills": [],
  "levels": [],
  "delivery_modes": [],
  "duration": { "in_class_minutes": { "min": 0, "max": 0 }, "sessions": 1, "out_of_class_hours": 0 },
  "class_size": { "min": 0, "max": null },
  "group_size": { "min": 1, "max": 1 },
  "prep_time_minutes": 0,
  "technology": [],
  "technology_notes": "",
  "cost_to_students": "",
  "ai_involvement": { "topic_is_ai": false, "students_use_ai": "none", "notes": "" },
  "materials": [ { "file": "", "url": "", "title": "", "role": "", "audience": "", "notes": "" } ],
  "classroom_testing": { "times_used": 0, "contexts": "" },
  "evidence_of_effectiveness": "",
  "prior_publications": [ { "citation": "", "doi": "", "url": "" } ],
  "rights": { "author_owns_or_has_permission": true, "third_party_materials": [ { "description": "", "status": "" } ], "contains_identifiable_student_information": false },
  "license": "",
  "version": "1.0",
  "version_notes": "",
  "assumptions": [ { "field": "", "basis": "" } ],
  "generated_with": "",
  "author_confirmed": false
}

(Each material has either "file" or "url", not both. Leave out rights.author_owns_or_has_permission and author_confirmed; the library sets them.)

Begin now.
````

## If something goes wrong

- **The assistant asks you questions instead:** reply "Please don't ask questions. Make your best judgment, list what you assumed, and give me the review and the manifest."
- **The page says it can't find a draft:** you probably copied only part of the reply. Copy the whole reply, or ask the assistant: "Please give me the manifest again as valid JSON in one code block."
- **The draft gets something wrong:** correct it on the page. The page is the final version, not the AI's draft.
- **You want to finish later:** your edits stay in that browser. To move to another computer, use **Download manifest.json** and then **Open a saved manifest.json** there.
