# Preparing your exercise with an AI assistant

This prompt helps you get an exercise ready for the ABSEL Exercise Library. You run it in an AI assistant you already use (ChatGPT, Claude, Microsoft Copilot, Gemini or similar). The assistant reads your materials, asks you a few questions, then gives you:

1. **A submission review:** which file is which, what's missing, and problems to fix before you submit.
2. **A draft of the exercise's details:** the indexing information the library needs, which the submission page loads so you don't have to type it in.

The easiest way to use it is from the **Submit an exercise** page on absel.org (for ABSEL members), which has a button that copies this prompt and a box for pasting the assistant's reply. The same page also works offline as a single file, [`submit.html`](../wp-plugin/absel-ai-library/assets/submit.html), for preparing a manifest without submitting it. This document is the same prompt with fuller instructions. Using AI is optional: the submission page also works as an ordinary form.

## Before you start

- **Check your school's AI policy.** Some institutions limit which AI tools faculty may use with unpublished work. If you're unsure, use your school's licensed tool (often Microsoft Copilot) or fill in the form by hand.
- **Remove student information first.** Don't upload files containing student names, grades or identifiable student work, to the assistant or to ABSEL.
- **Turn off training if you can.** Most assistants have a setting that stops your uploads being used to train their models.

## How to use it

1. Start a new chat in your AI assistant. A personal account or your school's enterprise account (such as Microsoft Copilot) both work.
2. Attach all the files for the exercise: instructor guide, student handouts, slides, spreadsheets, and so on. If the assistant can't open a file type (some can't read slides or zip files), export it to PDF, or paste its text into the chat with a heading naming the file.
3. Copy everything in the box below (or use **Copy the ABSEL prompt** on the submission page), paste it into the chat and send it.
4. Answer the assistant's questions.
5. Read the submission review and fix anything it flags in your files.
6. Copy the assistant's **whole final reply** and paste it into the box on the submission page. You don't need to tidy it: the page finds the draft inside the reply, fixes common formatting slips, and shows the review alongside the form.
7. Check each section of the form. Pick-lists are buttons, problems are marked in red with plain explanations, and yellow notes show where the page changed or couldn't use one of the assistant's values. Your edits are saved in your browser as you go. **You are responsible for what's submitted**, and AI assistants make mistakes.

## The prompt

````text
You are helping a university business instructor prepare an experiential exercise for the ABSEL Exercise Library, a members-only collection of simulations, role-plays and other experiential exercises for business courses. The instructor has attached the exercise's files (or pasted their text). Your job is to index the exercise accurately and check the package. You are not rewriting the exercise.

Work in three steps.

STEP 1: READ AND INVENTORY
Read every file. If you can't open a file, say so and ask me to paste its text or export it to PDF. Don't guess at its contents.

STEP 2: ASK ME QUESTIONS, THEN WAIT
Ask me, in one numbered list, about everything you need that the materials don't clearly state. Keep it to 12 questions or fewer, and group related items. Always ask about the following unless the materials answer it:
- author names, affiliations, emails and ORCID iDs, and who is the contact author;
- how many times I've used the exercise with real students, and in which courses, levels and class sizes;
- any evidence it works (student feedback, assessment results, publications);
- prior publications of the exercise, such as ABSEL Developments proceedings;
- whether anything in the materials was created by someone else (published cases, publisher slides, images, articles, software) and whether I have permission;
- which license I want (list the four options below);
- anything about class size, timing, prep time or technology that the materials leave unclear.
Then stop and wait for my answers. Do not produce Step 3 until I reply.

STEP 3: PRODUCE TWO THINGS

Part A: Submission review (plain text, not JSON)
1. Files: a table with each file's exact name, a short title, its role and its audience, using the allowed values below.
2. Missing: anything a complete package should have but doesn't. A complete package has an instructor guide covering learning objectives, timing, setup, how to run it and debrief questions, plus everything students need.
3. Must fix before submitting:
   - any student names, grades or identifiable student work;
   - third-party material without clear permission;
   - answers, solutions or hidden information inside a file meant for students;
   - inconsistencies between files (for example, different timings or numbers in the guide and the handout).
   Quote or locate each problem precisely. If there are none, say so.
4. Suggestions (optional, at most 5): improvements that would make the exercise easier for another instructor to adopt.
5. Still needed: any manifest field you left null because neither the materials nor I provided it.

Part B: manifest.json
One JSON code block that follows the format and rules below exactly.

RULES FOR THE MANIFEST
- Never invent facts. Use only what the materials or I state. If an optional value is unknown, use null or leave the field out. If a required value is unknown, use null and list it under "Still needed".
- For list fields with allowed values, use only the values listed below, spelled exactly (lowercase, hyphenated). Never make up a new value. If nothing fits well, choose the closest and mention it in the review.
- Keep everything confidential out of the title, summary, description and topics: answer keys, hidden parameters, the "twist", what the other side really wants. These fields are shown to more people than the instructor guide.
- title: 5-120 characters, descriptive (not just a product name).
- slug: lowercase words joined by hyphens, at most 60 characters, based on the title.
- summary: 60-180 words, in the third person, for an instructor deciding whether to use the exercise. Say what students do, what they learn and what it takes to run. No marketing language.
- description: optional longer overview for members (structure, variations), or null.
- learning_objectives: 2-8 items. Each completes "Students will be able to..." but leaves those words out, and starts with an assessable verb (explain, apply, calculate, compare, evaluate, design, negotiate, justify). Never use understand, learn, appreciate or be exposed to. Base them on the materials' own objectives where they exist.
- topics: 3-12 lowercase search keywords for the concepts taught, in the terms an instructor would search for (textbook names of concepts, e.g. "escalation of commitment", "bullwhip effect"). Don't repeat discipline names. Spell out abbreviations unless the abbreviation is the common search term.
- courses: up to 5 typical course titles in plain words.
- disciplines: 1-3, primary first. Tag where it's taught, not every field it touches.
- exercise_types: 1-3, most important first.
- skills: up to 5, only skills students actually practice.
- levels and delivery_modes: only those the exercise was designed for or used with, not ones it could in theory be adapted to.
- duration.in_class_minutes: total class time across all sessions, including the debrief, as {"min": n, "max": n}. Use 0 for a fully out-of-class exercise.
- class_size and group_size: {"min": n, "max": n}. Use "max": null for no upper limit. Use {"min": 1, "max": 1} for individual work.
- materials: one entry per file, using the exact file name as given. instructor-guide, teaching-notes and answer-key are always instructor-only. Use "url" instead of "file" for something hosted elsewhere.
- rights.contains_identifiable_student_information must be false. If the files contain student information, set it to false anyway, and put removing it at the top of "Must fix before submitting".
- version: "1.0" unless I say otherwise.
- generated_with: the name of your AI product and model.
- author_confirmed: always false. The instructor confirms on the submission form.
- schema_version: always "1.0".
- Output valid JSON only inside the code block: double quotes, no comments, no trailing commas.

ALLOWED VALUES

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

materials role:
- instructor-guide: main guide (always instructor-only)
- teaching-notes: supplementary notes, variations, tips (always instructor-only)
- answer-key: solutions, scoring keys, hidden parameters (always instructor-only)
- debrief-guide: debrief questions and discussion plan
- student-instructions: instructions for all students
- role-brief: confidential instructions for one role
- case-text: case or scenario text
- reading: background reading
- slides
- simulation: the simulation itself, such as an HTML file
- data-file
- template-or-worksheet: forms students fill in
- rubric
- assessment: quiz, test or survey
- video
- other: explain in notes

materials audience: instructor-only, student-facing

rights.third_party_materials status: permission-obtained, openly-licensed, public-domain, needs-review (use needs-review whenever permission is unclear)

license:
- absel-member-use: members may use and adapt it in their own courses, no redistribution (default)
- cc-by-nc-4.0: anyone may share and adapt it non-commercially, with credit
- cc-by-nc-sa-4.0: as above, and adaptations must use the same license
- cc-by-4.0: anyone may share and adapt it for any purpose, with credit

MANIFEST FORMAT
Fill in this structure. Optional fields are marked; keep them (with null or an empty list) if unknown.

{
  "schema_version": "1.0",
  "title": "",
  "slug": "",
  "summary": "",
  "description": null,                       // optional
  "authors": [
    { "name": "", "affiliation": "", "email": "", "orcid": null, "corresponding": true }
  ],
  "exercise_types": [],
  "disciplines": [],
  "courses": [],                             // optional
  "topics": [],
  "learning_objectives": [],
  "skills": [],                              // optional
  "levels": [],
  "delivery_modes": [],
  "duration": { "in_class_minutes": { "min": 0, "max": 0 }, "sessions": 1, "out_of_class_hours": null },
  "class_size": { "min": 0, "max": null },
  "group_size": { "min": 1, "max": 1 },      // optional
  "prep_time_minutes": null,                 // optional
  "technology": [],
  "technology_notes": null,                  // optional
  "cost_to_students": "free",                // optional
  "ai_involvement": { "topic_is_ai": false, "students_use_ai": "none", "notes": null },   // optional
  "materials": [
    { "file": "", "title": "", "role": "", "audience": "", "notes": null }
  ],
  "classroom_testing": { "times_used": 0, "contexts": null },   // optional
  "evidence_of_effectiveness": null,         // optional
  "prior_publications": [],                  // optional; items: { "citation": "", "doi": null, "url": null }
  "rights": {
    "author_owns_or_has_permission": true,
    "third_party_materials": [],             // items: { "description": "", "status": "" }
    "contains_identifiable_student_information": false
  },
  "license": "absel-member-use",
  "version": "1.0",
  "version_notes": null,                     // optional
  "generated_with": "",
  "author_confirmed": false
}

(The // notes above are for you. Leave them out of your output.)

Begin with Step 1 now.
````

## If something goes wrong

- **The assistant skips the questions** and goes straight to the manifest: reply "Please go back to Step 2 and ask your questions first."
- **The page says it can't find a draft:** you probably copied only part of the reply. Copy the whole final reply, or ask the assistant: "Please give me the manifest again as valid JSON in one code block."
- **The draft gets something wrong:** correct it in the form. The form is the final version, not the AI's draft.
- **You want to finish later:** your edits stay in that browser. To move to another computer, use **Download manifest.json** and then **Open a saved manifest.json** there.
