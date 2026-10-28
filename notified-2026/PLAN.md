# Pop-Up Cascade: Plan

A web game made of dismissible pop-ups over a blurred, mobile-first mock dashboard. No end state, no visible score, no visible level.

## Core loop
- A pop-up appears at a random position on screen.
- Manual closes record reaction time (spawn to close). Auto-closed notifications are not recorded.
- The rolling average of the last 5 manual closes steps difficulty up (faster) or down (slower). Each step change clears the window, which prevents flicker.
- The next pop-up appears after the current delay, measured from the last dismissal or the last spawn, whichever came later. Ignored pop-ups keep spawning on the same timer, so they can stack.
- The delay never goes below 400 ms.
- No count cap, no fail state, nothing shown on screen beyond the background and pop-ups.

## Pop-up types
- **Action pop-ups:** upgrade, AI note-taking, signup, cookie notice, survey, and similar. They never close themselves. The primary button or any close control dismisses them.
- **Notification pop-ups:** call recording, AI note-taking available, and similar. They auto-close after a random 4 to 10 seconds. Rare cases are 2 seconds and 60 seconds, each about 5%. A progress bar shows the time remaining. Early manual close counts; timed-out closes do not.

## Close styles
Unlocked by difficulty step:
- Step 0: corner ×, "Close" / "Not now" button
- Step 1: "No thanks" text link
- Step 2: small × on the card edge
- Step 4: tiny low-contrast ×
- Step 5: "Maybe later" small link

## Background
Generic dashboard (sidebar, header, cards, chart, list) that is responsive, mobile-first. It sits under a `filter: blur()` layer and is non-interactive.

## Constraints
- One `index.html`, vanilla JS and CSS, no dependencies.
- Mobile-first layout, touch and mouse both work.
- Invented product names only, no real brands or logos.
- Reduced-motion users get no pop-up transitions.
