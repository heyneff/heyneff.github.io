# License Plate Game — Developer Specification

A lightweight, offline-friendly Progressive Web App (PWA) for tracking U.S. states and Canadian provinces during road trips.

---

## Overview

**Purpose:**  
Allow children and adults to play the License Plate Game by tracking sightings of U.S. states and Canadian provinces while offline.

**Platform:**  
Progressive Web App (PWA)  
- Works offline  
- Installable on both iOS and Android  
- No backend required  
- Built with plain HTML, CSS, and JavaScript  

---

## Core Features

- Checklist of **U.S. states** and **Canadian provinces/territories**
- Track progress by checking/unchecking each location
- State persists between sessions until reset
- **Single active game** at a time
- **Sticky progress bar** showing number found per region
- Support for both **portrait and landscape**
- **Automatic light/dark mode** matching the device
- **No haptics or sounds**
- Optional **“Install app” prompt**

---

## Game Flow

### Start Screen
- On first visit, the user sees the checklist immediately.
- The title reads:  
  `License Plate Game [date created]`  
  Example: `License Plate Game March 29, 2025`

### Checklist View
- A **single scrollable list** with two alphabetically sorted sections:
  1. U.S. States
  2. Canadian Provinces and Territories
- Each item displays:  
  `State or Province Name (Abbreviation)`  
  Example: `Texas (TX)`
- Checked items stay in place and appear **grayed out**.

### Progress Tracker
- **Sticky bar** below the title, always visible on scroll
- Displays counts:  
  `34 of 50 states • 6 of 13 provinces`

### New Game Button
- Button label: `New Game`
- Tapping it opens a **confirmation modal**:
  - Message:  
    `Clear your observations and start a new game?`
  - Buttons:  
    - `Back` (closes modal)  
    - `Clear and start new game` (resets checklist and date)

---

## Visual Style

- Functional, clean layout
- Native checkbox style or simple custom alternative
- Responsive design for both mobile orientations
- Minimal UI chrome
- No onboarding or tutorials

---

## Persistence

- Checklist state and game start date saved in **localStorage**
- Data persists until the user taps **New Game**

---

## Theming

- Follows device’s **light or dark mode** using `prefers-color-scheme`
- No in-app theme toggle required

---

## PWA Behavior

- Installable on both iOS and Android
- Uses `beforeinstallprompt` to show a banner or modal:
  - Message: `Install License Plate Game for offline use?`
  - Buttons: `Not now`, `Install`

---

## Hosting & Deployment

- Self-contained single-page web app (no build tools)
- Compatible with platforms like **Bluehost**
- All assets (HTML, CSS, JS, icons, manifest) should be bundled locally for offline support