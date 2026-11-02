# Job Search Workflow App: Spec (v0.2)

_Revised from `spec-original.md`. Open questions are collected in the last section._

## 1. Goal

Land a full-time design leadership role (Head of Design, Director, VP or similar) by the **end of Q1 2027** (31 March 2027). The app gets there by turning a scattered job search into a single daily workflow: find the right roles, find a warm way in, send strong tailored materials and never let a follow-up slip.

### Success measures

| Measure | Target |
|---|---|
| Signed offer for a design leadership role | By 31 Mar 2027 |
| Time spent on the daily review (new roles, follow-ups) | 15 minutes or less |
| Share of applications that go in with a referral or warm intro | 50% or more |
| Follow-ups missed past their due date | Zero |
| Time to produce a tailored resume and cover letter for one role | 10 minutes or less, including your edits |

## 2. Users and principles

- **One user now, open source later.** It's built for you first, but nothing personal goes into the code: your profile, target criteria, company list and API keys all live in local data and config files. Someone else should be able to clone the repo, add their own data and use it.
- **You stay in control.** The app drafts, scores and reminds. It never submits an application, sends a message or contacts anyone on its own.
- **Private by default.** Resumes, salary figures, feedback and contacts are sensitive. They stay on your machine and are sent only to the AI model, and only when generating something.
- **Every claim traces back to a source.** Anything the app writes about you (a resume bullet, a cover letter line, an interview story) must come from facts in your profile database. Nothing gets invented. Generated text shows which facts it used.
- **Collect data lightly and politely.** See section 5.

## 3. Scope

### Version 1 (build now)

These are the modules from the brief. Numbers match the original list.

1. User profile database
2. Target company profiling
3. Automated job matching
4. Referral and network mapping
5. Skill gap analysis and tailored materials
6. Application tracking
7. Matching portfolio case studies to roles
8. Networking and outreach tracker

**V1 must include importing applications you've already sent.** You're mid-search, so on day one the tracker (module 6) and outreach tracker (module 8) need to take in what you already have, from a spreadsheet (CSV) or by quick manual entry.

### Version 2 (next)

9. Leadership interview preparation
10. Compensation and market benchmarking
11. Post-interview feedback loop

V1 should leave room for these. For example, the data model already has places for interview rounds and offer details, even though V1 builds no screens around them.

### Out of scope

- Applying to jobs automatically, or filling in application forms
- Sending email or LinkedIn messages automatically
- Collecting LinkedIn data by any means other than LinkedIn's own data export
- A mobile app
- Accounts, multiple users or hosting (revisit when open-sourcing)

## 4. Platform: local web app (recommended)

"Local tool" means software that runs on your own computer instead of on a server on the internet. The version recommended here is a **local web app**: you start it on your laptop and use it in your normal browser at `http://localhost`. It looks and works like a website, but the app and all your data stay on your machine.

| | Local web app (recommended) | Hosted web app |
|---|---|---|
| Privacy | Data never leaves your laptop, except calls to the AI model | Resumes, salaries and contacts sit on a server you have to secure |
| Cost | Free to run; you pay only for AI usage | Hosting and database costs every month |
| Build effort | Lower: no login, no security for multiple users, no deployment | Higher: login, data separation between users, ops |
| Open-source friendliness | High: others clone it and bring their own API key | You'd be running a service, and paying for other people's AI usage |
| Daily job scan | Runs only while your laptop is on (it catches up when you open it) | Runs on schedule no matter what |
| Phone access | No (except on your home network) | Yes |
| Moving to hosted later | Possible: same code, add login and a server database | — |

**Decision:** build a local web app. The two downsides (no phone access, scans that wait for your laptop) are minor for one person checking in each morning. The privacy and open-source benefits are significant.

**Proposed stack** (the engineer can adjust): TypeScript, Next.js, SQLite (a single-file database) with a type-safe ORM, a background job runner for scheduled scans, and the Claude API for scoring and writing. Data lives in one local folder that's easy to back up.

## 5. Data sources and collection policy

Prefer official exports and public interfaces to scraping. Scrape only where nothing else exists, and do it slowly.

| Source | How | Notes |
|---|---|---|
| Greenhouse, Lever, Ashby, Workable (ATS job boards) | Their **public job board APIs** (structured job data, no scraping) | Covers most startup and growth-stage companies. Given a company's board name, the app polls it once a day. |
| Other company career pages | Scrape the page, at most once a day per company | Honour robots.txt, identify the app honestly, rate-limit, cache. If a page is hard to parse, fall back to a link plus "check manually". |
| Lenny's Jobs, TrueUp | Manual or very light use | Used mainly to **discover companies** (seed lists, plus growth and funding signals), not as a daily job feed. Follow each site's terms. Importing a CSV or pasting a URL is fine. |
| LinkedIn connections | **LinkedIn's own data export** (Settings → Data privacy → Get a copy of your data → Connections.csv) | No scraping, so no risk of a ban. Re-import monthly. Matching connections to companies works off current company and title. |
| Your documents | Upload: resume(s), LinkedIn profile PDF, portfolio case studies, performance reviews, notes | Parsed into structured facts that you review. |
| Paste-in job descriptions | Paste a URL or text | For roles found anywhere else (recruiter emails, word of mouth). |

**Collection rules for all automated fetching:** one shared rate limiter, at most one request every few seconds per domain, retries that back off, caching of each day's results, and an easy way to switch off any single source.

## 6. Core data model

| Entity | Key fields |
|---|---|
| **Profile fact** | Type (role, achievement, metric, skill, story, education, award), text, dates, company, source document, verified (yes/no), tags |
| **Story** | STAR structure (situation, task, action, result), linked facts, leadership themes (e.g. scaling a team, executive influence, design ops) |
| **Case study** | Title, URL, summary, skills shown, scope (team size, budget, impact), tags |
| **Target criteria** | Role titles, seniority, location and remote preferences, company stage, size, industry, culture signals, founder background, comp floor, deal-breakers. Each has a weight. |
| **Company** | Name, website, ATS type and board name, stage, headcount, funding, industry, notes, tier (dream / strong / maybe / excluded), how it fits the criteria |
| **Job** | Company, title, URL, description, location, posted date, source, first and last seen, status (new / reviewing / saved / applied / dismissed / closed), fit score, fit summary |
| **Contact** | Name, LinkedIn URL, current company and title, relationship strength (1 to 3), how you know them, last contacted |
| **Application** | Job, date applied, materials sent (versions), referral contact, stage (see module 6), next action and due date, outcome |
| **Interaction** | Contact and/or application, type (message, call, coffee, intro request, interview), date, notes, follow-up due |
| **Generated asset** | Type (resume, cover letter, outreach message, interview story set), job, content, facts used, version, status (draft / final / sent) |
| **Interview round** _(V2)_ | Application, round type, date, interviewers, feedback, self-assessment |
| **Offer** _(V2)_ | Application, base, bonus, equity details, benefits, deadline |

## 7. Modules (V1)

Each module lists what it does and how we'll know it works (**acceptance criteria**).

### 1. User profile database

You upload documents. The app pulls out facts, you approve or edit them, and the approved facts become the single source of truth for everything the app writes about you. A short guided interview (you answer questions in a chat) fills gaps, for example: "What's the biggest team you've built?" or "Tell me about a time you changed an executive's mind."

- Accepts PDF, DOCX, Markdown and pasted text.
- Each extracted fact links to the document it came from. Nothing is used until you've verified it.
- You can turn facts into STAR stories.
- Acceptance: uploading your current resume produces at least 90% of its roles and achievements as separate, editable facts within 2 minutes.

### 2. Target company profiling

Turns what "right" means into weighted criteria, then into a list of companies. Seed companies come from Lenny's Jobs, TrueUp, your own list and suggestions.

- Criteria editor with must-haves, nice-to-haves, deal-breakers and weights.
- For each company: profile fields (stage, size, funding, founders' background), how well it matches the criteria with reasons, and a tier you set.
- The app detects which ATS the company uses (Greenhouse, Lever and so on) so module 3 can poll it.
- Acceptance: you can add 50 companies from a CSV and see each one's criteria match and ATS detection.

### 3. Automated job matching

A daily scan of every tracked company's job board finds new leadership openings and scores them against your criteria and profile.

- Filters by title and seniority first (cheap), then sends only likely matches to the AI model for scoring (cost control).
- **Fit score** (0 to 100) made up of shown parts: role and seniority match, company match, skills match (from module 5), plus a penalty for any deal-breaker. A 2 to 3 sentence summary covers why it fits and what the concerns are.
- Detects duplicates, and marks a role closed when it disappears from the board.
- Daily digest page: new roles sorted by score, each with a warm-path badge (from module 4).
- Acceptance: a scan of 100 companies finishes in under 15 minutes, never hits any domain more than its rate limit allows, and produces a digest you can review in 5 minutes or less.

### 4. Referral and network mapping

Finds who you know at each target company.

- Imports Connections.csv and matches connections to companies (allowing for name variants like "Inc." and parent companies).
- For each job: first-degree connections at the company, ranked by relevance (design or product leaders, recruiters, hiring managers, people whose relationship strength you've set) and by how well you know them.
- Second-degree introductions are out of scope (LinkedIn's export doesn't include them). Instead, you can tag a contact as a "connector" for a company.
- One click creates an outreach task in module 8.
- Acceptance: after importing, every tracked company shows its connection count, and every job shows its best warm paths.

### 5. Skill gap analysis and tailored materials

For a chosen job: compares the description against your verified profile, shows strengths and gaps, and drafts tailored materials.

- Pulls requirements out of the description and labels each one met (with the facts that prove it), partly met or gap. For each gap, it suggests how to address it (reframe an existing experience, mention adjacent work, or flag it as a real gap to prepare for).
- Generates a tailored resume (from a template you control, exported as DOCX and PDF), a cover letter, and a short "why me" summary for the referral message.
- Every generated line shows the facts it used. Lines that rely on unverified or missing facts are flagged and never left in silently.
- Versions are saved and linked to the application.
- Acceptance: the resume and cover letter for one job take 10 minutes or less including your edits, and contain no claims that aren't in your profile.

### 6. Application tracking

One place for every application, both existing and new.

- Stages: Saved → Referral requested → Applied → Recruiter screen → Hiring manager → Panel/onsite → Final → Offer → Accepted / Rejected / Withdrawn / Ghosted.
- Board and table views, filters, and a timeline of activity for each application.
- **Reminder rules** (configurable): follow up 7 days after applying with no response; send a thank-you within 24 hours of an interview; follow up on a referral request after 5 days; warn 48 hours before an offer deadline.
- Reminders show up in the daily digest, and optionally as a calendar file (.ics) or email-to-self. The app never contacts anyone else.
- Imports from CSV (columns can be mapped at import).
- Acceptance: your existing applications can be imported in under 15 minutes, and every one with a past-due next action appears in the digest.

### 7. Matching portfolio case studies to roles

- Tags each case study by skills, domain, scale and leadership themes. The app suggests tags and you confirm them.
- For each job: suggests the 2 to 3 best case studies to lead with, explains why each fits, and notes what to emphasise when presenting it.
- Acceptance: every scored job shows its recommended case studies, along with the reason for each.

### 8. Networking and outreach tracker

- Contacts, interactions and follow-up schedules (e.g. check in every 6 weeks with strong contacts, and follow up 3 days after an intro).
- Drafts outreach messages (referral ask, informational interview request, thank-you) using the contact, the job and your "why me" summary. You copy each one and send it yourself.
- Links referrals to applications so you can see which warm paths turned into interviews.
- Acceptance: every contact with a follow-up due appears in the daily digest, along with a draft message.

## 8. The daily workflow (what using it feels like)

1. Open the app. It catches up on any missed scans.
2. **Digest:** follow-ups due today, then new roles sorted by fit, each with its warm paths.
3. For a promising role: save it → check the gap analysis → ask for a referral (draft provided) → generate materials → apply on the company's site → mark it applied.
4. Done in about 15 minutes, apart from writing and applying.

## 9. Other requirements

- **AI cost:** the app tracks spending per month and lets you set a limit. Scoring uses a cheaper model and writing uses a stronger one (configurable).
- **Backup:** one data folder, plus an export of everything to JSON or CSV.
- **Setup for open source:** a README, a sample config, sample (made-up) data, and no personal data in the repo, ever. The data folder must be listed in `.gitignore`.
- **Reliability:** a failure in one source never stops the rest of the scan. Errors appear on a "source health" panel.
- **Repository:** this lives inside a GitHub Pages site repo. App code must not be published as part of the site (see open questions).

## 10. Open questions

1. **Repo location:** your `heyneff.github.io` repo publishes everything on its `main` branch as a public website. It's probably better to keep the spec here and build the app in its own new repo, which also suits open-sourcing it later. Is that OK?
2. **Target criteria:** what are your role titles, locations and remote preferences, company stage, minimum compensation and deal-breakers? You can fill these in through the app's settings, but early answers help with testing.
3. **Existing applications:** where do they live today (a spreadsheet, Notion, email)? This decides which import to build first.
4. **Portfolio:** is it on a website the app can read, or in files?
5. **Resume template:** do you have a preferred format to reuse?
6. **AI budget:** a monthly cap you're comfortable with (rough guess: $20 to $50 a month for daily scoring of a few hundred roles plus drafting).
