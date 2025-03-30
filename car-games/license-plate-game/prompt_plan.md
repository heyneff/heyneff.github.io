# License Plate Game — Development Blueprint & Iterative Prompts

Below is a detailed, step-by-step blueprint for building the License Plate Game PWA, followed by a series of iterative prompts. Each prompt is designed to build on the previous work, ensuring best practices, integration, and no orphaned code. These prompts are formatted in markdown with code blocks tagged as `text`.

---

## Detailed Blueprint for Building the License Plate Game PWA

### 1. Project Setup
- **File Structure:**  
  Create a basic structure with these files:
  - `index.html` – The main HTML file.
  - `style.css` – Stylesheet for UI and theming.
  - `script.js` – Contains all JavaScript logic.
  - `manifest.json` – PWA manifest file with app metadata.
  - `service-worker.js` – For offline caching.
- **PWA Registration:**  
  Register the service worker and include basic manifest details (name, icons, display mode).
- **Best Practices:**  
  Keep the project self-contained and lightweight using plain HTML, CSS, and JavaScript.

### 2. Base HTML Structure
- **Layout Components:**  
  - A header area with the title (`License Plate Game [date created]`), which will later include a dynamically generated date.
  - A sticky progress tracker below the title.
  - A main container that holds the checklist, divided into two sections (US states and Canadian provinces).
  - A “New Game” button.
- **Modal Dialog:**  
  Predefine a hidden modal for confirmation with the message “Clear your observations and start a new game?” and two buttons: “Back” and “Clear and start new game”.

### 3. Styling & Theming
- **Responsive Layout:**  
  Use CSS to create a clean, functional layout that adapts to both portrait and landscape orientations.
- **Theming:**  
  Use CSS media queries (`prefers-color-scheme`) so that the app automatically matches the device’s theme.
- **Visual Cues:**  
  Ensure that checked items are grayed out (without reordering) and style the sticky progress tracker to always be visible.

### 4. Checklist Data and UI Rendering
- **Data Definition:**  
  In `script.js`, define two arrays (or objects) for the checklist:
  - U.S. states (name and postal abbreviation).
  - Canadian provinces/territories (name and postal abbreviation).
- **Dynamic Rendering:**  
  Write functions to sort and dynamically render these lists into their respective sections in the main content area.

### 5. Interaction and Persistence
- **User Interaction:**  
  - Add event listeners to toggle the checked state of each item.
  - Update the item's appearance (gray out) and the progress tracker in real time.
- **Persistence:**  
  Use `localStorage` to save:
  - The checked state of each checklist item.
  - The game’s start date.
  - Restore these values on page refresh.

### 6. New Game Functionality with Modal
- **Modal Behavior:**  
  - Wire the “New Game” button to display the confirmation modal.
  - The modal’s “Back” button closes it without action.
  - The modal’s “Clear and start new game” button:
    - Clears the saved checklist state and start date from localStorage.
    - Resets the checklist to its initial state.
    - Generates a new game start date and updates the title.
    - Resets the progress tracker.
- **Integration:**  
  Ensure all UI elements update and re-render correctly.

### 7. PWA Install Prompt
- **Beforeinstallprompt Event:**  
  - Listen for the `beforeinstallprompt` event.
  - Capture the event and display an install prompt UI (e.g., a banner or modal) with the message: “Install License Plate Game for offline use?” and buttons “Not now” and “Install.”
  - On “Install,” trigger the native installation flow.
- **Integration:**  
  Ensure the install prompt does not interfere with the core functionality.

### 8. Final Integration & Testing
- **Wiring Components Together:**  
  Ensure all parts (checklist rendering, interactions, persistence, new game modal, and PWA install prompt) are fully integrated.
- **Testing:**  
  - Verify that the checklist renders and updates correctly.
  - Confirm that localStorage persistence works.
  - Ensure the modal functions properly.
  - Test the service worker caching for offline functionality.
  - Validate the app in both orientations and on various devices.
- **Final Clean-up:**  
  Remove any unused code, add error handling as needed, and document next steps for further improvements.

---

## Iterative Prompts for Code-Generation LLM

Each prompt below builds incrementally on the previous steps.

---

### Prompt 1: Project Structure & Basic Files

```text
Create the basic file structure for the License Plate Game PWA. The project should include the following files:
- index.html
- style.css
- script.js
- manifest.json
- service-worker.js

In index.html, create a basic HTML5 skeleton and link style.css and script.js. In manifest.json, include basic app metadata (name, short_name, display, and icons placeholder). In service-worker.js, set up a minimal service worker that caches the necessary assets for offline use. Finally, register the service worker in script.js. Ensure that the project can load in a browser.



⸻

Prompt 2: Base HTML Structure

In index.html, add the following structure:
- A header section containing:
  - A title area that displays “License Plate Game [date created]” (the date will be dynamically generated later).
  - A sticky progress tracker area (initially empty).
- A main content area that will contain the checklist.
- A “New Game” button.
- A modal dialog element (hidden by default) that will be used for confirmation. This modal should contain the message “Clear your observations and start a new game?” and two buttons labeled “Back” and “Clear and start new game”.

Ensure that each element has clear ids or classes for targeting in CSS and JavaScript.



⸻

Prompt 3: Styling & Theming

In style.css, write CSS to achieve the following:
- Create a clean, functional layout with a header, a sticky progress tracker, and a scrollable main content area.
- Style checklist items with a default appearance and a “checked” state that grays out the text.
- Style the modal dialog to be centered and overlay the page when visible.
- Use the `prefers-color-scheme` media query to automatically adjust for light and dark modes.
- Ensure the layout is responsive for both portrait and landscape orientations.

Include basic CSS reset styles as needed and comments to explain your choices.



⸻

Prompt 4: Checklist Data and UI Rendering

In script.js, perform the following:
- Define two arrays (or objects) for checklist data:
  - One for U.S. states.
  - One for Canadian provinces/territories.
  Each entry should include the full name and postal abbreviation (e.g., { name: "Texas", abbreviation: "TX" }).
- Write functions to sort each array alphabetically.
- Create a function that dynamically generates the HTML for each checklist section (US states and Canadian provinces) and injects it into the main content area of index.html.
- Ensure that each checklist item includes a checkbox (or clickable element) and displays text in the format “State or Province Name (Abbreviation)”.

Verify that the checklist renders correctly in two separate, alphabetically sorted sections when the page loads.



⸻

Prompt 5: Interaction for Check/Uncheck and Persistence

Enhance script.js to add the following functionality:
- Attach event listeners to each checklist item to allow toggling its checked state.
- When an item is checked, update its visual style (gray out the item) without changing its position.
- Update the sticky progress tracker in real time to show counts (e.g., “34 of 50 states • 6 of 13 provinces”).
- Implement persistence using localStorage: on every change, save the current checklist state (which items are checked) and the game’s start date.
- On page load, read from localStorage to restore the checklist state and update the progress tracker accordingly.

Ensure smooth interactions and that refreshing the page retains the user's progress.



⸻

Prompt 6: New Game Functionality with Modal

In script.js, implement the “New Game” functionality:
- Add an event listener to the “New Game” button so that when clicked, the confirmation modal appears.
- Wire the modal’s “Back” button to close the modal without taking any action.
- Wire the modal’s “Clear and start new game” button to:
  - Clear the saved checklist state and game start date from localStorage.
  - Reset the checklist to its initial, unchecked state.
  - Generate a new game start date and update the title area accordingly.
  - Reset the progress tracker to reflect zero progress.
- Ensure that all UI elements update correctly after a new game is started.



⸻

Prompt 7: PWA Install Prompt

Implement the PWA install prompt in script.js:
- Listen for the `beforeinstallprompt` event.
- Capture the event and display an install prompt UI (such as a banner or modal) with the message “Install License Plate Game for offline use?” and two buttons: “Not now” and “Install.”
- If the user clicks “Install,” call the prompt method on the saved event and handle the user’s choice.
- If “Not now” is clicked, simply hide the prompt.
- Ensure that this prompt is fully integrated and does not interfere with other functionalities.



⸻

Prompt 8: Final Integration & Testing

Perform final integration and testing in script.js and index.html:
- Ensure that all functionalities (checklist rendering, check/uncheck interactions, persistence, new game modal, and PWA install prompt) are properly wired together.
- Add necessary error handling and comments to clarify the code.
- Verify that the service worker correctly caches assets for offline use.
- Test the complete app flow:
  - The checklist renders correctly.
  - Checking/unchecking items updates both the UI and localStorage.
  - The “New Game” modal appears and resets the state when confirmed.
  - The install prompt appears when appropriate.
- Clean up any unused code and document any final notes or next steps.



⸻

This complete series of prompts provides a solid, incremental plan that you can hand off to a code-generation LLM to implement the License Plate Game PWA step by step.

