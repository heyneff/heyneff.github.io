// Register service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => {
                console.log('Service Worker registered with scope:', registration.scope);
            })
            .catch(error => {
                console.error('Service Worker registration failed:', error);
            });
    });
}

// Define US states data
const usStates = [
    { name: "Alabama", abbreviation: "AL" },
    { name: "Alaska", abbreviation: "AK" },
    { name: "Arizona", abbreviation: "AZ" },
    { name: "Arkansas", abbreviation: "AR" },
    { name: "California", abbreviation: "CA" },
    { name: "Colorado", abbreviation: "CO" },
    { name: "Connecticut", abbreviation: "CT" },
    { name: "Delaware", abbreviation: "DE" },
    { name: "Florida", abbreviation: "FL" },
    { name: "Georgia", abbreviation: "GA" },
    { name: "Hawaii", abbreviation: "HI" },
    { name: "Idaho", abbreviation: "ID" },
    { name: "Illinois", abbreviation: "IL" },
    { name: "Indiana", abbreviation: "IN" },
    { name: "Iowa", abbreviation: "IA" },
    { name: "Kansas", abbreviation: "KS" },
    { name: "Kentucky", abbreviation: "KY" },
    { name: "Louisiana", abbreviation: "LA" },
    { name: "Maine", abbreviation: "ME" },
    { name: "Maryland", abbreviation: "MD" },
    { name: "Massachusetts", abbreviation: "MA" },
    { name: "Michigan", abbreviation: "MI" },
    { name: "Minnesota", abbreviation: "MN" },
    { name: "Mississippi", abbreviation: "MS" },
    { name: "Missouri", abbreviation: "MO" },
    { name: "Montana", abbreviation: "MT" },
    { name: "Nebraska", abbreviation: "NE" },
    { name: "Nevada", abbreviation: "NV" },
    { name: "New Hampshire", abbreviation: "NH" },
    { name: "New Jersey", abbreviation: "NJ" },
    { name: "New Mexico", abbreviation: "NM" },
    { name: "New York", abbreviation: "NY" },
    { name: "North Carolina", abbreviation: "NC" },
    { name: "North Dakota", abbreviation: "ND" },
    { name: "Ohio", abbreviation: "OH" },
    { name: "Oklahoma", abbreviation: "OK" },
    { name: "Oregon", abbreviation: "OR" },
    { name: "Pennsylvania", abbreviation: "PA" },
    { name: "Rhode Island", abbreviation: "RI" },
    { name: "South Carolina", abbreviation: "SC" },
    { name: "South Dakota", abbreviation: "SD" },
    { name: "Tennessee", abbreviation: "TN" },
    { name: "Texas", abbreviation: "TX" },
    { name: "Utah", abbreviation: "UT" },
    { name: "Vermont", abbreviation: "VT" },
    { name: "Virginia", abbreviation: "VA" },
    { name: "Washington", abbreviation: "WA" },
    { name: "West Virginia", abbreviation: "WV" },
    { name: "Wisconsin", abbreviation: "WI" },
    { name: "Wyoming", abbreviation: "WY" },
    { name: "District of Columbia", abbreviation: "DC" }
];

// Define Canadian provinces/territories data
const canadianProvinces = [
    { name: "Alberta", abbreviation: "AB" },
    { name: "British Columbia", abbreviation: "BC" },
    { name: "Manitoba", abbreviation: "MB" },
    { name: "New Brunswick", abbreviation: "NB" },
    { name: "Newfoundland and Labrador", abbreviation: "NL" },
    { name: "Northwest Territories", abbreviation: "NT" },
    { name: "Nova Scotia", abbreviation: "NS" },
    { name: "Nunavut", abbreviation: "NU" },
    { name: "Ontario", abbreviation: "ON" },
    { name: "Prince Edward Island", abbreviation: "PE" },
    { name: "Quebec", abbreviation: "QC" },
    { name: "Saskatchewan", abbreviation: "SK" },
    { name: "Yukon", abbreviation: "YT" }
];

// Function to sort an array of location objects alphabetically by name
function sortAlphabetically(locationArray) {
    return [...locationArray].sort((a, b) => a.name.localeCompare(b.name));
}

// Sort the arrays alphabetically
const sortedUSStates = sortAlphabetically(usStates);
const sortedCanadianProvinces = sortAlphabetically(canadianProvinces);

// Game state
let gameState = {
    dateCreated: null,
    spottedLocations: new Set(),
    totalLocations: sortedUSStates.length + sortedCanadianProvinces.length
};

// Initialize game - either load existing game or create new one
function initializeGame() {
    const savedGame = localStorage.getItem('licensePlateGame');
    
    if (savedGame) {
        try {
            const parsedGame = JSON.parse(savedGame);
            gameState.dateCreated = new Date(parsedGame.dateCreated);
            gameState.spottedLocations = new Set(parsedGame.spottedLocations);
            console.log('Game loaded successfully:', {
                dateCreated: gameState.dateCreated,
                spottedCount: gameState.spottedLocations.size
            });
        } catch (e) {
            console.error('Error loading saved game, creating new game instead:', e);
            createNewGame();
        }
    } else {
        createNewGame();
    }
    
    // Update the UI with the game state
    updateDateCreated();
    updateProgressTracker();
}

// Create a new game
function createNewGame() {
    gameState.dateCreated = new Date();
    gameState.spottedLocations = new Set();
    saveGameState();
    
    // Enable auto-save confirmation for the first save
    window.autoSaveConfirmation = true;
    setTimeout(() => {
        window.autoSaveConfirmation = false;
    }, 5000);
}

// Save the current game state to localStorage
function saveGameState() {
    const gameData = {
        dateCreated: gameState.dateCreated.toISOString(),
        spottedLocations: Array.from(gameState.spottedLocations),
        lastUpdated: new Date().toISOString()
    };
    
    localStorage.setItem('licensePlateGame', JSON.stringify(gameData));
    
    // Enable auto-save confirmation if needed
    if (window.autoSaveConfirmation) {
        showAutoSaveConfirmation();
    }
}

// Show a brief auto-save confirmation
function showAutoSaveConfirmation() {
    // Create auto-save confirmation element if it doesn't exist
    if (!document.getElementById('auto-save-confirmation')) {
        const confirmationEl = document.createElement('div');
        confirmationEl.id = 'auto-save-confirmation';
        confirmationEl.textContent = 'Progress saved';
        confirmationEl.style.position = 'fixed';
        confirmationEl.style.bottom = '20px';
        confirmationEl.style.right = '20px';
        confirmationEl.style.backgroundColor = 'var(--accent-color)';
        confirmationEl.style.color = 'white';
        confirmationEl.style.padding = '8px 16px';
        confirmationEl.style.borderRadius = '4px';
        confirmationEl.style.opacity = '0';
        confirmationEl.style.transition = 'opacity 0.3s ease';
        confirmationEl.style.zIndex = '1000';
        document.body.appendChild(confirmationEl);
    }
    
    const confirmationEl = document.getElementById('auto-save-confirmation');
    confirmationEl.style.opacity = '1';
    
    // Hide after 2 seconds
    setTimeout(() => {
        confirmationEl.style.opacity = '0';
    }, 2000);
}

// Update the date created display
function updateDateCreated() {
    const dateElement = document.getElementById('creation-date');
    if (dateElement && gameState.dateCreated) {
        dateElement.textContent = gameState.dateCreated.toLocaleDateString();
    }
}

// Update the progress tracker
function updateProgressTracker() {
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    
    // Count US states and Canadian provinces separately
    const usSpotted = [...gameState.spottedLocations].filter(id => id.startsWith('us-')).length;
    const caSpotted = [...gameState.spottedLocations].filter(id => id.startsWith('ca-')).length;
    
    // Calculate total spotted and overall percentage
    const spottedCount = gameState.spottedLocations.size;
    const totalCount = gameState.totalLocations;
    const progressPercentage = (spottedCount / totalCount) * 100;
    
    // Update progress bar
    progressBar.style.width = `${progressPercentage}%`;
    
    // Update progress text with detailed counts
    progressText.textContent = `${usSpotted} of ${sortedUSStates.length} states • ${caSpotted} of ${sortedCanadianProvinces.length} provinces`;
}

// Toggle spotted status of a location
function toggleSpottedStatus(locationId) {
    // Use a small timeout for better visual feedback when clicked
    const locationElement = document.getElementById(locationId);
    if (locationElement) {
        locationElement.classList.add('clicking');
        
        setTimeout(() => {
            // Update the game state
            if (gameState.spottedLocations.has(locationId)) {
                gameState.spottedLocations.delete(locationId);
            } else {
                gameState.spottedLocations.add(locationId);
            }
            
            // Update UI and save state
            updateLocationElement(locationId);
            updateProgressTracker();
            updateSectionCounts();
            saveGameState();
            
            // Update accessibility attributes
            locationElement.setAttribute('aria-pressed', gameState.spottedLocations.has(locationId));
            
            locationElement.classList.remove('clicking');
        }, 100);
    }
}

// Update a single location element based on current state
function updateLocationElement(locationId) {
    const element = document.getElementById(locationId);
    if (element) {
        if (gameState.spottedLocations.has(locationId)) {
            element.classList.add('spotted');
            // Add animation effect for smooth transition
            element.classList.add('just-spotted');
            setTimeout(() => {
                element.classList.remove('just-spotted');
            }, 500);
        } else {
            element.classList.remove('spotted');
        }
    }
}

// Generate HTML for a location section
function generateLocationSection(title, locations, idPrefix) {
    const sectionElement = document.createElement('section');
    sectionElement.className = 'states-checklist';
    
    const headerContainer = document.createElement('div');
    headerContainer.className = 'section-header';
    
    const titleElement = document.createElement('h2');
    titleElement.textContent = title;
    headerContainer.appendChild(titleElement);
    
    // Add count badge
    const countBadge = document.createElement('span');
    countBadge.className = 'count-badge';
    countBadge.id = `${idPrefix}-count`;
    
    const spottedInSection = [...gameState.spottedLocations]
        .filter(id => id.startsWith(`${idPrefix}-`)).length;
    
    countBadge.textContent = `${spottedInSection}/${locations.length}`;
    headerContainer.appendChild(countBadge);
    
    sectionElement.appendChild(headerContainer);
    
    const gridElement = document.createElement('div');
    gridElement.className = 'states-grid';
    
    locations.forEach(location => {
        const locationId = `${idPrefix}-${location.abbreviation}`;
        const locationElement = document.createElement('div');
        locationElement.id = locationId;
        locationElement.className = 'state';
        
        if (gameState.spottedLocations.has(locationId)) {
            locationElement.classList.add('spotted');
        }
        
        const nameSpan = document.createElement('span');
        nameSpan.className = 'location-name';
        nameSpan.textContent = location.name;
        
        const abbrSpan = document.createElement('span');
        abbrSpan.className = 'location-abbr';
        abbrSpan.textContent = location.abbreviation;
        
        locationElement.appendChild(nameSpan);
        locationElement.appendChild(document.createTextNode(' '));
        locationElement.appendChild(abbrSpan);
        
        locationElement.addEventListener('click', () => toggleSpottedStatus(locationId));
        locationElement.setAttribute('aria-pressed', gameState.spottedLocations.has(locationId));
        
        gridElement.appendChild(locationElement);
    });
    
    sectionElement.appendChild(gridElement);
    return sectionElement;
}

// Render the checklist sections
function renderChecklist() {
    const contentArea = document.getElementById('states-checklist');
    if (!contentArea) return;
    
    // Clear existing content
    contentArea.innerHTML = '';
    
    // Add title and instruction
    const headerDiv = document.createElement('div');
    headerDiv.className = 'checklist-header';
    
    const mainTitle = document.createElement('h2');
    mainTitle.textContent = 'License Plate Spotting Checklist';
    headerDiv.appendChild(mainTitle);
    
    const instruction = document.createElement('p');
    instruction.textContent = 'Tap on a location when you spot its license plate:';
    headerDiv.appendChild(instruction);
    
    contentArea.appendChild(headerDiv);
    
    // Create and append US states section
    const usSection = generateLocationSection('United States', sortedUSStates, 'us');
    contentArea.appendChild(usSection);
    
    // Create and append Canadian provinces section
    const canadaSection = generateLocationSection('Canadian Provinces & Territories', sortedCanadianProvinces, 'ca');
    contentArea.appendChild(canadaSection);
    
    // Update section badges whenever the checklist is rendered
    updateSectionCounts();
}

// Update the count badges for each section
function updateSectionCounts() {
    // Update US states count
    const usCount = document.getElementById('us-count');
    if (usCount) {
        const usSpotted = [...gameState.spottedLocations].filter(id => id.startsWith('us-')).length;
        usCount.textContent = `${usSpotted}/${sortedUSStates.length}`;
    }
    
    // Update Canadian provinces count
    const caCount = document.getElementById('ca-count');
    if (caCount) {
        const caSpotted = [...gameState.spottedLocations].filter(id => id.startsWith('ca-')).length;
        caCount.textContent = `${caSpotted}/${sortedCanadianProvinces.length}`;
    }
}

// Setup modal functionality
function setupModalHandlers() {
    const newGameBtn = document.getElementById('new-game-btn');
    const backBtn = document.getElementById('back-btn');
    const confirmNewGameBtn = document.getElementById('confirm-new-game-btn');
    const modal = document.getElementById('confirmation-modal');
    const modalBackdrop = document.getElementById('modal-backdrop');
    
    // Show modal when New Game button is clicked
    newGameBtn.addEventListener('click', () => {
        modal.classList.remove('hidden');
        modalBackdrop.classList.remove('hidden');
    });
    
    // Hide modal when Back button is clicked
    backBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        modalBackdrop.classList.add('hidden');
    });
    
    // Create new game when confirmed
    confirmNewGameBtn.addEventListener('click', () => {
        createNewGame();
        renderChecklist();
        updateDateCreated();
        updateProgressTracker();
        
        modal.classList.add('hidden');
        modalBackdrop.classList.add('hidden');
    });
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    initializeGame();
    renderChecklist();
    setupModalHandlers();
});