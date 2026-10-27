# Indexing rules (schema 1.0)

Every exercise in the library has a `manifest.json` describing it. The manifest is what the catalog displays, what search filters and ranks on, and what tells the site which files are for instructors only. These rules apply to the AI assistant drafting a manifest (through the [author prompt](../author-kit/author-prompt.md)), to editors reviewing submissions, and to anyone loading exercises.

The machine-readable version is [`exercise.schema.json`](exercise.schema.json). If this document and the schema disagree, the schema wins; fix this document. `py tools/check_library.py` checks every manifest against the schema and checks that this document and the author prompt list every allowed value.

## The approach: nothing required, assumptions confirmed

The library accepts anything from **an idea** to **a finished simulation ready for students**, including simulations hosted on GitHub. Authors submit whatever they have: a few sentences, a full manuscript, slides, handouts, spreadsheets or a link.

- **Only the title is required**, and the assistant or the submission form always provides one. Every other field is filled in when the materials support it and left out when they don't.
- **The assistant decides.** It reads everything the author provides and fills in what it can. It never asks the author a list of questions.
- **Stated or assumed.** Every value is either stated in the materials or inferred by the assistant. Each inferred value is listed in `assumptions` with the reason for it.
- **The author's one job** is to confirm or correct each assumption. The submission form won't submit until every assumption is confirmed or changed. Nothing else blocks a submission.
- **Editors judge completeness.** The `stage` field tells them what to expect, and the review screen shows what's present and what isn't.

## How the fields are used

| Use | Fields |
|---|---|
| **Filters** (checkboxes in the catalog) | `stage`, `disciplines`, `exercise_types`, `levels`, `delivery_modes`, `duration`, `class_size`, `group_size`, `technology`, `cost_to_students`, `ai_involvement`, `skills` |
| **Keyword search, ranked by relevance** | `title` (highest weight), `topics`, `learning_objectives`, `summary`, `courses`, `description`, author names (lowest) |
| **Public catalog page** | `title`, `stage`, `summary`, `authors` (name and affiliation), `disciplines`, `exercise_types`, `levels`, `duration`, `class_size`, the list of material titles |
| **Members only** | `description`, the files themselves, `evidence_of_effectiveness` |
| **Editors only** | author `email`, `rights`, `assumptions`, `generated_with`, `author_confirmed` |
| **File protection** | `materials[].audience` and `role` |

Filters only work if everyone uses the same words, so those fields accept only the values listed below. A missing value simply means the exercise doesn't show up under that filter.

## General rules

1. **Never invent.** Fill a field only when the materials state it or clearly support it. A missing class size is better than a wrong one.
2. **Mark every inference.** If a value isn't stated outright (for example, the level is inferred because the slides mention "MBA students"), add an entry to `assumptions` saying so and why.
3. **Nothing confidential in metadata.** The title, summary, description and topics are visible to more people than the instructor materials. Never put answer keys, hidden parameters or the "twist" of an exercise in them.
4. **Allowed values are written exactly as listed:** lowercase, hyphenated, no synonyms.

## Field by field

### Identity and stage

| Field | Rule |
|---|---|
| `schema_version` | `"1.0"` (required) |
| `title` | Required, up to 120 characters. Use the materials' own title. If there isn't one, write a descriptive title and list it as an assumption. |
| `slug` | Lowercase words joined by hyphens, at most 60 characters. Becomes the folder name and URL. Made from the title if missing. |
| `stage` | How far along it is; see below. |
| `summary` | Ideally 60–180 words, in the third person: what students do, what they learn, and what it takes to run. For an idea, describe the idea. No marketing language. Shown publicly. |
| `description` | Longer overview for members: structure, variations, adaptations. |
| `version` | `1.0` for a first submission; raise it for later versions. |
| `version_notes` | What changed in this version. |

**`stage`**

| Value | Meaning |
|---|---|
| `idea` | A concept, description or outline. No materials another instructor could run yet. |
| `in-development` | Some materials exist, but another instructor couldn't yet run it from them alone. |
| `ready-to-use` | Everything needed to run it exists: instructor guidance plus whatever students need (handouts, slides, a working simulation). |
| `classroom-tested` | Ready to use, and the author has run it with students. |

### People

`authors` is a list, in citation order. Each author has `name` (required for each author listed), `affiliation`, `email`, `orcid` (format `0000-0002-1825-0097`) and `corresponding` (true for the contact author). Emails are never displayed. On absel.org the submitting member is filled in automatically.

### Classification

**`exercise_types`** (up to 3, most important first)

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

**`disciplines`** (up to 3, primary first): `accounting`, `business-analytics`, `business-communication`, `business-ethics`, `business-law`, `economics`, `entrepreneurship`, `finance`, `general-management`, `human-resource-management`, `information-systems`, `international-business`, `leadership`, `marketing`, `negotiation`, `operations-management`, `organizational-behavior`, `project-management`, `strategy`, `supply-chain-management`, `sustainability`.

Tag where the exercise is taught, not every field it touches. A pricing game used in marketing courses is `marketing`, even though it involves economics. Use `general-management` only for genuinely cross-functional exercises, such as a capstone simulation.

**`courses`** (up to 5): typical course titles in plain words, e.g. "Principles of Marketing", "MBA Capstone".

**`topics`** (up to 12): lowercase search keywords for the concepts taught, in the terms an instructor would search for (the textbook name of a concept, not the exercise's internal names), e.g. `anchoring`, `bullwhip effect`, `escalation of commitment`. Don't repeat a discipline already tagged. Spell out abbreviations unless the abbreviation is the common search term (`batna` is fine; `npv` should be `net present value`).

**`learning_objectives`** (up to 8): each completes "Students will be able to…", leaving those words out, and starts with a verb that can be assessed: *explain, apply, calculate, compare, evaluate, design, negotiate, justify*. Avoid *understand, learn, appreciate*. Use the materials' own objectives where they exist. Objectives written by the assistant are an assumption.

**`skills`** (up to 5, most important first). These support AACSB assurance-of-learning reporting; tag only skills students actually practice.

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

**`levels`**: `undergraduate-introductory` (first- or second-year courses), `undergraduate-advanced` (upper-division courses), `graduate` (master's programs, including the MBA), `executive-education`, `doctoral`.

**`delivery_modes`**: `in-person`, `online-synchronous` (live video class), `online-asynchronous` (students work on their own schedule), `hybrid` (some students in the room and some online at the same time).

**`duration`**: `in_class_minutes` as `{ "min": …, "max": … }` (total class time across all sessions, including the debrief; use the same number twice for a single estimate), `sessions` (class meetings), `out_of_class_hours`.

**`class_size`**: `{ "min": …, "max": … }`, students in the whole class; `"max": null` means no upper limit. **`group_size`**: students per team; `{ "min": 1, "max": 1 }` means individual work.

**`prep_time_minutes`**: instructor preparation the first time.

**`technology`**: `none`, `projector`, `student-devices` (laptops, tablets or phones), `internet`, `lms` (the course learning management system), `spreadsheet-software`, `specialized-software` (anything students must install or buy; explain in `technology_notes`).

**`cost_to_students`**, in US dollars per student: `free`, `under-25`, `25-to-75`, `over-75`.

**`ai_involvement`**: `topic_is_ai` (true if it teaches about AI), `students_use_ai` (`none`, `optional` or `required`), `notes`.

### Materials

`materials` lists every file the author provided and any links. An idea may have none. Each entry has:
- `file`: the exact file name, or `url` for something hosted elsewhere, such as a GitHub repository, a video or a web-based simulation;
- `title`;
- `role`;
- `audience`: `instructor-only` or `student-facing`;
- `notes` (optional).

| Role | What it is | Audience |
|---|---|---|
| `instructor-guide` | Main guide: objectives, setup, running the exercise | always instructor-only |
| `teaching-notes` | Supplementary notes, variations, tips | always instructor-only |
| `answer-key` | Solutions, scoring keys, hidden parameters | always instructor-only |
| `debrief-guide` | Debrief questions and discussion plan | usually instructor-only |
| `student-instructions` | Instructions given to all students | student-facing |
| `role-brief` | Confidential instructions for one role | student-facing (to that role only) |
| `case-text` | Case or scenario text | student-facing |
| `reading` | Background reading | student-facing |
| `slides` | Presentation slides | either |
| `simulation` | The simulation itself, e.g. `index.html` or a link to a hosted one | student-facing |
| `code-repository` | Source code, e.g. a GitHub repository | either |
| `data-file` | Data students or instructors work with | either |
| `template-or-worksheet` | Forms students fill in | student-facing |
| `rubric` | Grading rubric | either |
| `assessment` | Quiz, test or survey | either |
| `video` | Video file or link | either |
| `manuscript` | A paper or write-up describing the exercise, e.g. a conference paper | instructor-only |
| `other` | Anything else; explain in `notes` | either |

Files can be in any format the site accepts (PDF, Word, PowerPoint, Excel, CSV, images, MP4, ZIP and web files). Editors may ask for answer keys to be split from student files before publication.

### Evidence and record

| Field | Rule |
|---|---|
| `classroom_testing.times_used` | Number of times the author has run it with students, if stated. |
| `classroom_testing.contexts` | Courses, levels and class sizes it was used with. |
| `evidence_of_effectiveness` | Student feedback, assessment results or research, summarized. |
| `prior_publications` | Earlier publications, e.g. ABSEL *Developments* proceedings: `citation`, plus optional `doi` and `url`. |

### Rights

- `third_party_materials`: anything in the materials the author apparently didn't create, such as a published case, publisher slides, images or articles. Each has a `description` and a `status`: `permission-obtained`, `openly-licensed`, `public-domain` or `needs-review` (use this when permission is unclear).
- `contains_identifiable_student_information`: true if the materials appear to contain student names, grades or identifiable work.
- `author_owns_or_has_permission`: set by the site when the author submits. Submitting includes a statement that the author can share the materials.

**`license`** (if not given, editors apply `absel-member-use`)

| Value | Members may |
|---|---|
| `absel-member-use` | Use and adapt in their own courses. No redistribution. |
| `cc-by-nc-4.0` | Share and adapt for non-commercial use, with credit. |
| `cc-by-nc-sa-4.0` | Same as above; adaptations must use the same license. |
| `cc-by-4.0` | Share and adapt for any purpose, with credit. |

### Assumptions and provenance

- `assumptions`: one entry per inferred value.
  - `field` is the manifest field, using dots for nested fields, e.g. `levels`, `duration.in_class_minutes` or `materials.2.role`.
  - `basis` says briefly why, e.g. "Slides 3 and 14 refer to MBA students".
  - `confirmed` is false from the assistant. The submission form sets it to true when the author confirms the value or changes it.
- `generated_with`: name of the AI tool that drafted the manifest.
- `author_confirmed`: set by the site when the author submits after handling every assumption.

## Editor checklist

What's acceptable depends on the stage. Ideas are welcome; a "ready-to-use" exercise should really be usable.

- [ ] The stage matches the materials.
- [ ] Nothing confidential appears in the title, summary, description or topics.
- [ ] Rights are clear: no third-party item still `needs-review` at publication, and no student information in any file.
- [ ] For `ready-to-use` and `classroom-tested`: there is instructor guidance, answer keys are in instructor-only files, and the files listed are the files received.
- [ ] Web files (HTML, JavaScript) have been reviewed; they will run on absel.org.
- [ ] The tags are reasonable for search: disciplines where it's taught, honest levels and delivery modes.

## Changing the rules

Allowed values are added by editors, not by authors. Propose a new value by opening a GitHub issue.
- Adding a value is a minor change (schema 1.1).
- Renaming or removing a value is a major change (2.0) and needs a script that updates existing manifests.

Update the schema, this document and the author prompt together, then run `py tools/build_form.py`. `tools/check_library.py` fails until they all agree.
