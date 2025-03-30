document.addEventListener('DOMContentLoaded', () => {
  // Register the service worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
      .then(registration => {
        console.log('Service Worker registered with scope:', registration.scope);
      })
      .catch(error => {
        console.error('Service Worker registration failed:', error);
      });
  }

  // Define checklist data for U.S. states
  const usStates = [
    { name: 'Alabama', abbreviation: 'AL' },
    { name: 'Alaska', abbreviation: 'AK' },
    { name: 'Arizona', abbreviation: 'AZ' },
    { name: 'Arkansas', abbreviation: 'AR' },
    { name: 'California', abbreviation: 'CA' },
    { name: 'Colorado', abbreviation: 'CO' },
    { name: 'Connecticut', abbreviation: 'CT' },
    { name: 'Delaware', abbreviation: 'DE' },
    { name: 'District of Columbia', abbreviation: 'DC' },
    { name: 'Florida', abbreviation: 'FL' },
    { name: 'Georgia', abbreviation: 'GA' },
    { name: 'Hawaii', abbreviation: 'HI' },
    { name: 'Idaho', abbreviation: 'ID' },
    { name: 'Illinois', abbreviation: 'IL' },
    { name: 'Indiana', abbreviation: 'IN' },
    { name: 'Iowa', abbreviation: 'IA' },
    { name: 'Kansas', abbreviation: 'KS' },
    { name: 'Kentucky', abbreviation: 'KY' },
    { name: 'Louisiana', abbreviation: 'LA' },
    { name: 'Maine', abbreviation: 'ME' },
    { name: 'Maryland', abbreviation: 'MD' },
    { name: 'Massachusetts', abbreviation: 'MA' },
    { name: 'Michigan', abbreviation: 'MI' },
    { name: 'Minnesota', abbreviation: 'MN' },
    { name: 'Mississippi', abbreviation: 'MS' },
    { name: 'Missouri', abbreviation: 'MO' },
    { name: 'Montana', abbreviation: 'MT' },
    { name: 'Nebraska', abbreviation: 'NE' },
    { name: 'Nevada', abbreviation: 'NV' },
    { name: 'New Hampshire', abbreviation: 'NH' },
    { name: 'New Jersey', abbreviation: 'NJ' },
    { name: 'New Mexico', abbreviation: 'NM' },
    { name: 'New York', abbreviation: 'NY' },
    { name: 'North Carolina', abbreviation: 'NC' },
    { name: 'North Dakota', abbreviation: 'ND' },
    { name: 'Ohio', abbreviation: 'OH' },
    { name: 'Oklahoma', abbreviation: 'OK' },
    { name: 'Oregon', abbreviation: 'OR' },
    { name: 'Pennsylvania', abbreviation: 'PA' },
    { name: 'Rhode Island', abbreviation: 'RI' },
    { name: 'South Carolina', abbreviation: 'SC' },
    { name: 'South Dakota', abbreviation: 'SD' },
    { name: 'Tennessee', abbreviation: 'TN' },
    { name: 'Texas', abbreviation: 'TX' },
    { name: 'Utah', abbreviation: 'UT' },
    { name: 'Vermont', abbreviation: 'VT' },
    { name: 'Virginia', abbreviation: 'VA' },
    { name: 'Washington', abbreviation: 'WA' },
    { name: 'West Virginia', abbreviation: 'WV' },
    { name: 'Wisconsin', abbreviation: 'WI' },
    { name: 'Wyoming', abbreviation: 'WY' }
  ];

  // Define checklist data for Canadian provinces/territories
  const canadianProvinces = [
    { name: 'Alberta', abbreviation: 'AB' },
    { name: 'British Columbia', abbreviation: 'BC' },
    { name: 'Manitoba', abbreviation: 'MB' },
    { name: 'New Brunswick', abbreviation: 'NB' },
    { name: 'Newfoundland and Labrador', abbreviation: 'NL' },
    { name: 'Northwest Territories', abbreviation: 'NT' },
    { name: 'Nova Scotia', abbreviation: 'NS' },
    { name: 'Nunavut', abbreviation: 'NU' },
    { name: 'Ontario', abbreviation: 'ON' },
    { name: 'Prince Edward Island', abbreviation: 'PE' },
    { name: 'Quebec', abbreviation: 'QC' },
    { name: 'Saskatchewan', abbreviation: 'SK' },
    { name: 'Yukon', abbreviation: 'YT' }
  ];

  // Variables to track game state
  let gameStartDate = new Date();
  let foundPlates = {};

  // Function to sort arrays alphabetically by name
  function sortRegionsAlphabetically(regions) {
    return [...regions].sort((a, b) => a.name.localeCompare(b.name));
  }

  // Get sorted arrays
  const sortedUSStates = sortRegionsAlphabetically(usStates);
  const sortedCanadianProvinces = sortRegionsAlphabetically(canadianProvinces);

  // Function to generate HTML for checklist sections
  function generateChecklistHTML() {
    const statesGrid = document.getElementById('states-grid');
    statesGrid.innerHTML = ''; // Clear existing content
    
    // Load saved state from localStorage
    loadGameState();
    
    // Create US States section
    const usSection = document.createElement('div');
    usSection.className = 'region-section';
    
    const usHeader = document.createElement('h2');
    usHeader.textContent = 'United States';
    usHeader.className = 'region-header';
    usSection.appendChild(usHeader);
    
    const usGrid = document.createElement('div');
    usGrid.className = 'region-grid';
    
    sortedUSStates.forEach(state => {
      const stateElement = createRegionElement(state, 'us');
      usGrid.appendChild(stateElement);
    });
    
    usSection.appendChild(usGrid);
    statesGrid.appendChild(usSection);
    
    // Create Canadian Provinces section
    const canadaSection = document.createElement('div');
    canadaSection.className = 'region-section';
    
    const canadaHeader = document.createElement('h2');
    canadaHeader.textContent = 'Canada';
    canadaHeader.className = 'region-header';
    canadaSection.appendChild(canadaHeader);
    
    const canadaGrid = document.createElement('div');
    canadaGrid.className = 'region-grid';
    
    sortedCanadianProvinces.forEach(province => {
      const provinceElement = createRegionElement(province, 'canada');
      canadaGrid.appendChild(provinceElement);
    });
    
    canadaSection.appendChild(canadaGrid);
    statesGrid.appendChild(canadaSection);
    
    // Update progress tracker
    updateProgressTracker();
  }
  
  // Function to create a single region element (state or province)
  function createRegionElement(region, regionType) {
    const element = document.createElement('div');
    element.className = 'state-item';
    element.dataset.abbreviation = region.abbreviation;
    element.dataset.regionType = regionType;
    
    // Check if this plate has been found
    if (foundPlates[region.abbreviation]) {
      element.classList.add('found');
    }
    
    // Format the text as "State Name (Abbreviation)"
    element.textContent = region.abbreviation;
    element.title = `${region.name} (${region.abbreviation})`;
    
    // Add click event to toggle state
    element.addEventListener('click', () => {
      toggleRegionFound(element, region.abbreviation);
    });
    
    return element;
  }
  
  // Function to toggle a region as found/not found
  function toggleRegionFound(element, abbreviation) {
    element.classList.toggle('found');
    
    if (element.classList.contains('found')) {
      foundPlates[abbreviation] = true;
    } else {
      delete foundPlates[abbreviation];
    }
    
    // Update localStorage
    saveGameState();
    
    // Update progress tracker
    updateProgressTracker();
  }
  
  // Function to update the progress tracker
  function updateProgressTracker() {
    const totalRegions = sortedUSStates.length + sortedCanadianProvinces.length;
    const foundCount = Object.keys(foundPlates).length;
    const progressPercentage = (foundCount / totalRegions) * 100;
    
    // Update the stats text
    document.getElementById('progress-stats').textContent = 
      `${foundCount}/${totalRegions} regions found`;
    
    // Update the progress bar
    document.getElementById('progress-fill').style.width = `${progressPercentage}%`;
  }
  
  // Function to save game state to localStorage
  function saveGameState() {
    const gameState = {
      dateCreated: gameStartDate.toISOString(),
      foundPlates: foundPlates
    };
    
    localStorage.setItem('licensePlateGame', JSON.stringify(gameState));
  }
  
  // Function to load game state from localStorage
  function loadGameState() {
    const savedState = localStorage.getItem('licensePlateGame');
    
    if (savedState) {
      const gameState = JSON.parse(savedState);
      gameStartDate = new Date(gameState.dateCreated);
      foundPlates = gameState.foundPlates || {};
      
      // Display the date created
      document.getElementById('date-created').textContent = 
        `(${gameStartDate.toLocaleDateString()})`;
    } else {
      // Initialize a new game
      gameStartDate = new Date();
      foundPlates = {};
      
      // Display the date created
      document.getElementById('date-created').textContent = 
        `(${gameStartDate.toLocaleDateString()})`;
        
      // Save initial state
      saveGameState();
    }
  }
  
  // Function to start a new game
  function startNewGame() {
    gameStartDate = new Date();
    foundPlates = {};
    
    // Update display
    document.getElementById('date-created').textContent = 
      `(${gameStartDate.toLocaleDateString()})`;
    
    // Clear all found states
    document.querySelectorAll('.state-item').forEach(item => {
      item.classList.remove('found');
    });
    
    // Save new game state
    saveGameState();
    
    // Update progress tracker
    updateProgressTracker();
    
    // Hide the modal
    document.getElementById('confirmation-modal').classList.add('hidden');
    document.getElementById('modal-overlay').classList.add('hidden');
  }
  
  // Set up event listeners for the buttons
  function setupEventListeners() {
    // New Game button
    document.getElementById('new-game-btn').addEventListener('click', () => {
      document.getElementById('confirmation-modal').classList.remove('hidden');
      document.getElementById('modal-overlay').classList.remove('hidden');
    });
    
    // Back button (cancel)
    document.getElementById('back-btn').addEventListener('click', () => {
      document.getElementById('confirmation-modal').classList.add('hidden');
      document.getElementById('modal-overlay').classList.add('hidden');
    });
    
    // Confirm New Game button
    document.getElementById('confirm-new-game-btn').addEventListener('click', startNewGame);
    
    // Also close modal when clicking the overlay
    document.getElementById('modal-overlay').addEventListener('click', () => {
      document.getElementById('confirmation-modal').classList.add('hidden');
      document.getElementById('modal-overlay').classList.add('hidden');
    });
  }
  
  // Initialize the game
  function initGame() {
    generateChecklistHTML();
    setupEventListeners();
  }
  
  // Start the game
  initGame();
});