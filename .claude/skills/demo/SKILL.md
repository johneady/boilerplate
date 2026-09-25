---
name: demo
description: "Build an Upwork client prototype from this boilerplate on the current demo/* branch and write the matching proposal in UPWORK.txt. Only run when the user invokes /demo."
argument-hint: "[demoN] <pasted job post: title, description, budget, screening questions>"
disable-model-invocation: true
---

# Upwork demo

Build a working prototype of the job post below on the current `demo/<slug>` branch, then write the proposal.

## Before starting

- Run `git branch --show-current`. It must be `demo/<slug>`, created fresh from main by `./docker/new-demo.sh <slug>`. If it is not, stop and tell me.
- If the arguments begin with a slot such as `demo3`, the demo URL is `https://demo3.powerphpscripts.com`. Otherwise leave `xxxxxx` in the proposal URL.

## Goal

Make the prototype functional and polished, not complete. Keep the scope to roughly 2 hours of work. Pick the 2–4 features that best prove the client's requirements were understood, then stub or skip everything else. A disabled button or a "coming soon" label is fine.

## Build rules

- Don't ask questions first. Make sensible assumptions and list them at the end.
- Brand it as a plausible client business: name, colours, logo, and realistic seed data for their industry (no lorem ipsum).
- Hide boilerplate features the client won't care about from the navigation and the dashboard.
- One-click demo logins must keep working, and each role should land somewhere that shows off the work.
- If the domain needs it (formulas, pricing rules, regulations), research it briefly and cite your sources in the final summary.
- Edit existing migrations in place instead of adding fix-up migrations.
- Preview on the local sqlite database. No need to create a separate database. The one in this project is throwaway.
- Write tests for core logic only, plus one browser smoke test of the main flow. Run them and Pint.
- Screenshot the key pages and fix anything that looks off.
- Commit on the demo branch. Don't run `./docker/publish-image.sh` and don't push.

## Proposal

Rewrite the part of `UPWORK.txt` above the `---` line, keeping its current style. Leave the strategy notes below the line untouched.

- The first line mirrors the client's own words and says what the demo does for them.
- Add a dash-bullet list of what is in the demo, mapped to their requirements.
- Ask one sharp question about something ambiguous in their post.
- Keep RELEVANT TRACK RECORD word for word. Never invent experience, clients or numbers.
- Plain text only: ALL CAPS headers, dash bullets, no markdown.
- If the post has screening questions, draft the answers in the chat, not in the file.

## When done, report

- What was built, the assumptions made, and what is faked or stubbed.

## Job post

$ARGUMENTS
