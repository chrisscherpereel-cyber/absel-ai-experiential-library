# Indexing rules (schema 1.0)

Every exercise in the library has a `manifest.json` in its folder. The manifest is what the catalog displays, what the search filters and ranks on, and what tells the site which files are for instructors only. These rules apply to authors (usually through the [author prompt](../author-kit/author-prompt.md)), to editors reviewing submissions, and to anyone loading exercises.

The machine-readable version is [`exercise.schema.json`](exercise.schema.json). If this document and the schema disagree, the schema wins; fix this document. `py tools/check_library.py` checks every manifest against the schema and checks that this document and the author prompt list every allowed value.

## How the fields are used

| Use | Fields |
|---|---|
| **Filters** (checkboxes in the catalog) | `disciplines`, `exercise_types`, `levels`, `delivery_modes`, `duration`, `class_size`, `group_size`, `technology`, `cost_to_students`, `ai_involvement`, `skills` |
| **Keyword search, ranked by relevance** | `title` (highest weight), `topics`, `learning_objectives`, `summary`, `courses`, `description`, author names (lowest) |
| **Public catalog page** | `title`, `summary`, `authors` (name and affiliation), `disciplines`, `exercise_types`, `levels`, `duration`, `class_size`, the list of material titles |
| **Members only** | `description`, the files themselves, `evidence_of_effectiveness` |
| **Editors only** | author `email`, `rights`, `generated_with`, `author_confirmed` |
| **File protection** | `materials[].audience` and `role` |

Filters only work if everyone uses the same words, so those fields accept only the values listed below. Free-text fields are for search, so write them in the words an instructor would type.

## General rules

1. **Don't invent anything.** If the materials don't say it and the author doesn't know, leave the optional field out or set it to `null`. A missing class size is better than a wrong one.
2. **Nothing confidential in metadata.** The summary, description and topics are visible to more people than the instructor guide. Never put answer keys, hidden parameters or the "trick" of an exercise in them.
3. **No student information anywhere.** `rights.contains_identifiable_student_information` must be `false`. Remove names, grades and identifiable student work from all files before submitting.
4. **Allowed values are written exactly as listed:** lowercase, hyphenated, no synonyms.

## Field by field

### Identity

| Field | Required | Rule |
|---|---|---|
| `schema_version` | yes | `"1.0"` |
| `title` | yes | 5–120 characters. Descriptive, not just a brand name. Good: "Negotiating with an Algorithmic Supplier". Weak: "DealMaker". |
| `slug` | yes | Lowercase words joined by hyphens, at most 60 characters. Becomes the folder name and URL. Never changes after publication. |
| `summary` | yes | 60–180 words (300–1,400 characters). Third person. Say what students do, what they learn, and what it takes to run. No marketing language, no confidential details. Shown publicly. |
| `description` | no | Longer overview for members: structure, variations, adaptations. |
| `version` | yes | `1.0` for a first submission. Raise the minor number (1.1) for corrections and the major number (2.0) when the exercise changes enough that old debrief notes no longer apply. |
| `version_notes` | no | What changed in this version. |

### People

`authors` is a list, in citation order. Each author has `name` (required), `affiliation`, `email`, `orcid` (format `0000-0002-1825-0097`) and `corresponding` (true for the contact author). Emails are never displayed.

### Classification

**`exercise_types`** (1–3, most important first)

| Value | Use when |
|---|---|
| `simulation` | Students make decisions in a model (computer-based or not) that responds to them. |
| `role-play` | Students act out assigned roles with confidential briefs. |
| `game` | Rules, scoring and competition are central, e.g. card, board or classroom games. |
| `case-discussion` | Structured analysis and discussion of a case. Use only if the exercise is more than a case, e.g. a case with an experiential component. |
| `structured-exercise` | A guided in-class activity that isn't a simulation, role-play or game, e.g. a ranking task or a build-and-test exercise. |
| `team-project` | Multi-week team work with deliverables. |
| `field-assignment` | Students work outside the classroom, e.g. interviewing managers or observing a business. |
| `reflection-or-debrief-tool` | A reusable debrief, reflection or feedback instrument. |
| `assessment-instrument` | A rubric, test or measure for assessing experiential learning. |

**`disciplines`** (1–3, primary first): `accounting`, `business-analytics`, `business-communication`, `business-ethics`, `business-law`, `economics`, `entrepreneurship`, `finance`, `general-management`, `human-resource-management`, `information-systems`, `international-business`, `leadership`, `marketing`, `negotiation`, `operations-management`, `organizational-behavior`, `project-management`, `strategy`, `supply-chain-management`, `sustainability`.

Tag where the exercise is taught, not every field it touches. A pricing game used in marketing courses is `marketing`, even though it involves economics. Use `general-management` only for exercises that are genuinely cross-functional, such as a capstone simulation.

**`courses`** (optional, up to 5): typical course titles in plain words, e.g. "Principles of Marketing", "Operations Management", "MBA Capstone".

**`topics`** (3–12): lowercase search keywords for the concepts taught, e.g. `anchoring`, `bullwhip effect`, `escalation of commitment`, `net present value`. Use the term an instructor would search for, and the textbook name of a concept rather than the exercise's internal names. Don't repeat a discipline already tagged. Spell out an abbreviation unless the abbreviation is what people search for (`batna` is fine; `npv` should be `net present value`).

**`learning_objectives`** (2–8): each completes "Students will be able to…" and starts with a verb that can be assessed: *explain, apply, calculate, compare, evaluate, design, negotiate, justify*. Avoid *understand, learn, appreciate, be exposed to*. Leave out "Students will be able to" itself.

**`skills`** (optional, up to 5, most important first). These support AACSB assurance-of-learning reporting. Tag only skills students actually practice and could be assessed on.

| Value | Students practice |
|---|---|
| `written-communication` | Writing for a business audience |
| `oral-communication` | Presenting, pitching, speaking |
| `critical-thinking` | Analyzing arguments, evidence and assumptions |
| `quantitative-reasoning` | Calculation, modeling, numerical judgment |
| `data-literacy` | Working with and interpreting data |
| `ethical-reasoning` | Identifying and resolving ethical issues |
| `teamwork` | Coordinating and contributing in teams |
| `leadership` | Leading others, taking a leadership role |
| `decision-making` | Choosing under uncertainty or with trade-offs |
| `negotiation-and-persuasion` | Bargaining, influencing |
| `cross-cultural-competence` | Working across cultures |
| `creativity-and-innovation` | Generating and developing new ideas |
| `technology-agility` | Learning and using business technology |
| `ai-literacy` | Understanding, using or evaluating AI systems |
| `reflective-thinking` | Examining their own behavior and learning |

### Logistics

**`levels`** (1 or more): `undergraduate-introductory` (first- or second-year courses), `undergraduate-advanced` (upper-division courses), `graduate` (master's programs, including the MBA), `executive-education`, `doctoral`. Tag only levels it has been used with or was designed for.

**`delivery_modes`** (1 or more): `in-person`, `online-synchronous` (live video class), `online-asynchronous` (students work on their own schedule), `hybrid` (some students in the room and some online at the same time). Tag a mode only if the materials support it as written, not if it could in theory be adapted.

**`duration`**
- `in_class_minutes`: `{ "min": …, "max": … }`, total class time across all sessions, **including the debrief**. Use 0 for an exercise done entirely outside class.
- `sessions`: number of class meetings.
- `out_of_class_hours`: student time outside class, or `null`.

**`class_size`**: `{ "min": …, "max": … }`, students in the whole class. Use `"max": null` for no upper limit. Use the range the exercise has worked with, not a guess.

**`group_size`** (optional): `{ "min": …, "max": … }`, students per team. `{ "min": 1, "max": 1 }` means individual work.

**`prep_time_minutes`** (optional): instructor preparation the first time, including reading the guide and setting up.

**`technology`** (1 or more): `none`, `projector`, `student-devices` (laptops, tablets or phones), `internet`, `lms` (the course learning management system), `spreadsheet-software`, `specialized-software` (anything students must install or buy; explain in `technology_notes`).

**`cost_to_students`** (optional), in US dollars per student: `free`, `under-25`, `25-to-75`, `over-75`.

**`ai_involvement`** (optional)
- `topic_is_ai`: true if the exercise teaches about AI.
- `students_use_ai`: whether students use AI tools during the exercise. `none`, `optional` or `required`.
- `notes`: which tools, and any policy considerations.

### Materials

`materials` lists every file in the package, plus any external links. Each entry has:

- `file`: the exact file name in the folder (or `url`, an `https://` link, for something hosted elsewhere).
- `title`: a human-readable name, e.g. "Instructor guide".
- `role`: one of the values below.
- `audience`: `instructor-only` or `student-facing`.
- `notes` (optional).

| Role | What it is | Audience |
|---|---|---|
| `instructor-guide` | Main guide: objectives, setup, running the exercise | always instructor-only |
| `teaching-notes` | Supplementary notes, variations, tips | always instructor-only |
| `answer-key` | Solutions, scoring keys, hidden parameters, "what's really going on" | always instructor-only |
| `debrief-guide` | Debrief questions and discussion plan | usually instructor-only |
| `student-instructions` | Instructions given to all students | student-facing |
| `role-brief` | Confidential instructions for one role | student-facing (to that role only) |
| `case-text` | Case or scenario text | student-facing |
| `reading` | Background reading | student-facing |
| `slides` | Presentation slides | either |
| `simulation` | The simulation itself, e.g. `index.html` | student-facing |
| `data-file` | Data students or instructors work with | either |
| `template-or-worksheet` | Forms students fill in | student-facing |
| `rubric` | Grading rubric | either |
| `assessment` | Quiz, test or survey | either |
| `video` | Video file or link | either |
| `other` | Anything else; explain in `notes` | either |

Package rules:
- **Answer keys go in their own file.** If a student-facing file contains answers, split it.
- **Use the formats the site serves:** PDF for documents people only read, and DOCX, PPTX or XLSX where instructors will want to edit. Export Markdown and other formats to PDF. Simulations are self-contained HTML. Files over 100 MB go in as a `url`.
- **Every package needs an `instructor-guide`.** It should cover objectives, timing, setup, running the exercise and debrief questions.
- "Student-facing" means the instructor may give the file to students. The site still serves documents only to members; student links open only the simulation.

### Evidence and record

| Field | Rule |
|---|---|
| `classroom_testing.times_used` | Number of times the author has run it with real students. 0 is acceptable; editors may ask for a pilot first. |
| `classroom_testing.contexts` | Courses, levels and class sizes it was used with. |
| `evidence_of_effectiveness` | Student feedback, assessment results, or published research. Summarize; don't paste raw student comments. |
| `prior_publications` | Earlier publications of the exercise, e.g. ABSEL *Developments* proceedings. `citation` is required; `doi` and `url` are optional. |

### Rights

- `author_owns_or_has_permission`: the authors created the materials or have permission to share them.
- `third_party_materials`: anything the authors didn't create, such as a published case, publisher slides, images, articles or a commercial simulation. Give each item a `description` and a `status`: `permission-obtained`, `openly-licensed`, `public-domain` or `needs-review`. Editors resolve every `needs-review` item before publication. Material that can't be cleared is replaced with a citation ("Students read Smith (2021), chapter 4").
- `contains_identifiable_student_information`: must be `false`.

**`license`**

| Value | Members may |
|---|---|
| `absel-member-use` (default) | Use and adapt in their own courses. No redistribution. |
| `cc-by-nc-4.0` | Share and adapt for non-commercial use, with credit. |
| `cc-by-nc-sa-4.0` | Same as above; adaptations must use the same license. |
| `cc-by-4.0` | Share and adapt for any purpose, with credit. |

The Creative Commons licenses let members pass materials on to non-members. That is the author's choice; ABSEL still hosts the exercise behind the membership.

### Provenance

- `generated_with`: name of the AI tool that drafted the manifest, or `null`.
- `author_confirmed`: always `false` in a drafted manifest. The submission form sets it to `true` after the author has checked every field.

## Editor checklist

- [ ] The manifest passes `py tools/check_library.py`.
- [ ] `author_confirmed` is true, and the summary reads well to an instructor who has never seen the exercise.
- [ ] Nothing confidential appears in the title, summary, description or topics.
- [ ] Every file in the folder is listed in `materials`, and every listed file exists.
- [ ] Answer keys, teaching notes and the instructor guide are marked instructor-only, and no student-facing file contains answers.
- [ ] No third-party item is still `needs-review`.
- [ ] No student names, grades or identifiable student work appear in any file.
- [ ] Tags are honest: disciplines where it's taught, levels and delivery modes it actually supports.

## Changing the rules

Allowed values are added by editors, not by authors. Propose a new value (for example a missing discipline) by opening a GitHub issue. Adding a value is a minor change (schema 1.1). Renaming or removing one is a major change (2.0) and needs a script that updates existing manifests. Update the schema, this document and the author prompt together; `tools/check_library.py` fails until all three agree.
