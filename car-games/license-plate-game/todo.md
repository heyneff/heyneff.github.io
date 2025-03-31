# License Plate Game — TODO Checklist

This checklist outlines all the tasks required to build the License Plate Game PWA. Use this as your step-by-step guide during development.

---

## 1. Project Setup
- [ ] **Create File Structure**
  - [ ] Create `index.html` with a basic HTML5 skeleton.
  - [ ] Create `style.css` for all app styles.
  - [ ] Create `script.js` for JavaScript logic.
  - [ ] Create `manifest.json` with app metadata (name, short_name, display, icons placeholder).
  - [ ] Create `service-worker.js` for offline caching.

- [ ] **PWA Registration**
  - [ ] Register the service worker in `script.js`.
  - [ ] Verify that the manifest is linked in `index.html`.

---

## 2. Base HTML Structure
- [ ] **Header Section**
  - [ ] Add a title area that displays: `License Plate Game [date created]` (dynamic date to be implemented later).
  - [ ] Add a container for a sticky progress tracker (initially empty).

- [ ] **Main Content Area**
  - [ ] Create a scrollable area for the checklist.
  - [ ] Divide the checklist into two sections:
    - [ ] U.S. States (alphabetically sorted)
    - [ ] Canadian Provinces and Territories (alphabetically sorted)

- [ ] **New Game & Modal**
  - [ ] Add a “New Game” button.
  - [ ] Create a modal dialog (hidden by default) with:
    - [ ] Message: “Clear your observations and start a new game?”
    - [ ] Two buttons: “Back” (to cancel) and “Clear and start new game” (to confirm).

---

## 3. Styling & Theming (style.css)
- [ ] **Layout & Responsiveness**
  - [ ] Style header, sticky progress tracker, and main content area.
  - [ ] Ensure the layout is responsive for both portrait and landscape orientations.

- [ ] **Checklist Items**
  - [ ] Style default checklist items.
  - [ ] Style the “checked” state (gray out text without changing position).

- [ ] **Modal Dialog**
  - [ ] Style the modal to be centered and overlay the page.
  - [ ] Ensure the modal is hidden by default and displays correctly when triggered.

- [ ] **Theming**
  - [ ] Use `prefers-color-scheme` media query to automatically adjust for light and dark modes.

---

## 4. Checklist Data and UI Rendering (script.js)
- [ ] **Data Definitions**
  - [ ] Define an array (or object) for U.S. states (each with name and postal abbreviation).
  - [ ] Define an array (or object) for Canadian provinces/territories (each with name and postal abbreviation).

- [ ] **Dynamic Rendering**
  - [ ] Write functions to sort each array alphabetically.
  - [ ] Write a function to generate HTML for each checklist section and inject it into the main content area.
  - [ ] Ensure each checklist item displays as: “State or Province Name (Abbreviation)”.

---

## 5. Interaction & Persistence (script.js)
- [ ] **Checklist Interaction**
  - [ ] Attach event listeners for toggling check/uncheck on each checklist item.
  - [ ] Update the visual style (gray out text) when items are checked.
  - [ ] Ensure that the item order remains unchanged after checking.

- [ ] **Progress Tracker**
  - [ ] Update the sticky progress tracker in real time with counts (e.g., “34 of 50 states • 6 of 13 provinces”).

- [ ] **Local Storage Persistence**
  - [ ] Save the checked state of each item to localStorage.
  - [ ] Save the game’s start date to localStorage.
  - [ ] On page load, restore the checklist state and game date from localStorage.

---

## 6. New Game Functionality with Modal (script.js)
- [ ] **Modal Integration**
  - [ ] Add an event listener on the “New Game” button to open the confirmation modal.
  - [ ] Wire the “Back” button to close the modal without any changes.
  - [ ] Wire the “Clear and start new game” button to:
    - [ ] Clear localStorage for checklist state and game date.
    - [ ] Reset the checklist to its initial, unchecked state.
    - [ ] Generate a new game start date and update the title.
    - [ ] Reset the progress tracker to show zero progress.
  - [ ] Ensure that all UI elements are updated and re-rendered correctly.

---

## 7. PWA Install Prompt (script.js)
- [ ] **Install Prompt Setup**
  - [ ] Listen for the `beforeinstallprompt` event.
  - [ ] Capture and store the event.
  - [ ] Display an install prompt UI (banner or modal) with:
    - [ ] Message: “Install License Plate Game for offline use?”
    - [ ] Two buttons: “Not now” and “Install.”
  - [ ] Wire the “Install” button to trigger the native installation flow.
  - [ ] Wire the “Not now” button to hide the prompt.

---

## 8. Final Integration & Testing
- [ ] **Integration**
  - [ ] Ensure all components (HTML, CSS, JS, service worker, manifest) are correctly integrated.
  - [ ] Verify that checklist rendering, interactions, persistence, modal, and install prompt are working together without issues.

- [ ] **Testing**
  - [ ] Test checklist rendering and updating on various devices.
  - [ ] Verify localStorage persistence on page refresh.
  - [ ] Test the “New Game” functionality and modal confirmation.
  - [ ] Confirm that the service worker caches assets for offline use.
  - [ ] Test responsiveness in both portrait and landscape orientations.
  - [ ] Verify that the PWA install prompt works correctly.

- [ ] **Code Cleanup & Documentation**
  - [ ] Remove any unused code.
  - [ ] Add error handling where necessary.
  - [ ] Comment code for clarity and document next steps for future improvements.

---

Use this `todo.md` as your comprehensive checklist to track progress and ensure no step is missed during development.