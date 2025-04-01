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

  // Set date created in header
  updateDateCreated();
  
  // Initialize modal functionality
  setupNewGameButton();
  
  // Initialize PWA install prompt
  setupPWAInstallPrompt();

  // U.S. states data with abbreviations
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

  // Canadian provinces and territories data with abbreviations
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

  // Function to sort arrays alphabetically by name
  function sortAlphabetically(array) {
    return array.sort((a, b) => a.name.localeCompare(b.name));
  }

  // Sort both arrays alphabetically
  const sortedUSStates = sortAlphabetically([...usStates]);
  const sortedCanadianProvinces = sortAlphabetically([...canadianProvinces]);

  // Game state storage keys
  const STORAGE_KEY = 'licensePlateGame';
  const START_DATE_KEY = 'licensePlateGameStartDate';

  // Initialize the checklist
  initChecklist();

  /**
   * Updates the date created text in the header
   */
  function updateDateCreated() {
    const dateElement = document.getElementById('date-created');
    if (!dateElement) return;
    
    // Get saved date or create a new one
    let startDate = localStorage.getItem(START_DATE_KEY);
    if (!startDate) {
      startDate = new Date().toISOString();
      localStorage.setItem(START_DATE_KEY, startDate);
    }
    
    // Format the date for display
    const formattedDate = new Date(startDate).toLocaleDateString('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric'
    });
    
    dateElement.textContent = formattedDate;
  }

  /**
   * Initializes the checklist by loading saved state and rendering UI
   */
  function initChecklist() {
    const statesGrid = document.getElementById('states-grid');
    if (!statesGrid) {
      console.error('States grid element not found in the document');
      return;
    }
    
    // Clear existing content
    statesGrid.innerHTML = '';
    
    // Load saved state from localStorage
    const savedData = getSavedData();
    
    // Create U.S. States section
    const usSection = document.createElement('div');
    usSection.className = 'section';
    usSection.innerHTML = '<h2>U.S. States</h2>';
    
    const usGrid = document.createElement('div');
    usGrid.className = 'state-grid';
    usGrid.style.display = 'grid';
    usGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(90px, 1fr))';
    usGrid.style.gap = '10px';
    usGrid.style.marginBottom = '20px';
    
    sortedUSStates.forEach(state => {
      const stateItem = createStateItem(state, savedData.checkedItems);
      usGrid.appendChild(stateItem);
    });
    
    usSection.appendChild(usGrid);
    statesGrid.appendChild(usSection);
    
    // Create Canadian Provinces section
    const canadaSection = document.createElement('div');
    canadaSection.className = 'section';
    canadaSection.innerHTML = '<h2>Canadian Provinces & Territories</h2>';
    
    const canadaGrid = document.createElement('div');
    canadaGrid.className = 'state-grid';
    canadaGrid.style.display = 'grid';
    canadaGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(90px, 1fr))';
    canadaGrid.style.gap = '10px';
    
    sortedCanadianProvinces.forEach(province => {
      const provinceItem = createStateItem(province, savedData.checkedItems);
      canadaGrid.appendChild(provinceItem);
    });
    
    canadaSection.appendChild(canadaGrid);
    statesGrid.appendChild(canadaSection);
    
    // Update progress counts
    updateProgressTracker();
  }

  /**
   * Creates a state/province item for the grid
   * @param {Object} item - The state/province object
   * @param {Object} checkedItems - Object containing checked states
   * @returns {HTMLElement} - The created state/province element
   */
  function createStateItem(item, checkedItems) {
    const isChecked = !!checkedItems[item.abbreviation];
    
    const stateItem = document.createElement('div');
    stateItem.className = 'state-item';
    stateItem.dataset.abbreviation = item.abbreviation;
    
    if (isChecked) {
      stateItem.classList.add('found');
    }
    
    stateItem.style.backgroundColor = isChecked ? '#81c784' : '#e0e0e0';
    stateItem.style.color = isChecked ? 'white' : '#333';
    stateItem.style.borderRadius = '5px';
    stateItem.style.padding = '10px 5px';
    stateItem.style.textAlign = 'center';
    stateItem.style.cursor = 'pointer';
    stateItem.style.transition = 'background-color 0.2s ease, transform 0.1s ease';
    stateItem.style.userSelect = 'none';
    
    // Create abbreviation element
    const abbr = document.createElement('div');
    abbr.textContent = item.abbreviation;
    abbr.style.fontWeight = 'bold';
    abbr.style.fontSize = '20px';
    abbr.style.marginBottom = '5px';
    
    // Create name element
    const name = document.createElement('div');
    name.textContent = item.name;
    name.style.fontSize = '12px';
    
    stateItem.appendChild(abbr);
    stateItem.appendChild(name);
    
    // Add click event to toggle found state
    stateItem.addEventListener('click', () => {
      // Toggle found state
      const newState = !stateItem.classList.contains('found');
      
      // Update visual state
      if (newState) {
        stateItem.classList.add('found');
        stateItem.style.backgroundColor = '#81c784';
        stateItem.style.color = 'white';
      } else {
        stateItem.classList.remove('found');
        stateItem.style.backgroundColor = '#e0e0e0';
        stateItem.style.color = '#333';
      }
      
      // Update localStorage
      toggleItem(item.abbreviation, newState);
      
      // Update progress
      updateProgressTracker();
    });
    
    return stateItem;
  }

  /**
   * Toggles the checked state of an item and updates localStorage
   * @param {string} abbreviation - The state/province abbreviation
   * @param {boolean} isChecked - Whether the item is checked
   */
  function toggleItem(abbreviation, isChecked) {
    // Get current saved data
    const savedData = getSavedData();
    
    // Initialize start date if this is the first check
    if (!localStorage.getItem(START_DATE_KEY) && isChecked) {
      localStorage.setItem(START_DATE_KEY, new Date().toISOString());
      updateDateCreated();
    }
    
    // Update checked items
    if (isChecked) {
      savedData.checkedItems[abbreviation] = true;
    } else {
      delete savedData.checkedItems[abbreviation];
    }
    
    // Save updated data
    localStorage.setItem(STORAGE_KEY, JSON.stringify(savedData.checkedItems));
  }

  /**
   * Gets saved game data from localStorage
   * @returns {Object} - Object containing checked items and start date
   */
  function getSavedData() {
    // Get saved checklist state
    const checkedItems = JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
    
    return {
      checkedItems,
      startDate: localStorage.getItem(START_DATE_KEY)
    };
  }

  /**
   * Updates the progress tracker with current counts
   */
  function updateProgressTracker() {
    const progressTracker = document.getElementById('progress-tracker');
    if (!progressTracker) {
      console.error('Progress tracker element not found');
      return;
    }
    
    const savedData = getSavedData();
    
    // Count checked items for each category
    const statesCount = countCheckedItems(sortedUSStates, savedData.checkedItems);
    const provincesCount = countCheckedItems(sortedCanadianProvinces, savedData.checkedItems);
    
    // Update progress text
    progressTracker.innerHTML = `
      <div class="progress-info">
        <span>${statesCount} of ${sortedUSStates.length} states • ${provincesCount} of ${sortedCanadianProvinces.length} provinces</span>
      </div>
    `;
  }

  /**
   * Counts checked items in a category
   * @param {Array} items - Array of state/province objects
   * @param {Object} checkedItems - Object containing checked states
   * @returns {number} - Count of checked items
   */
  function countCheckedItems(items, checkedItems) {
    return items.reduce((count, item) => {
      return count + (checkedItems[item.abbreviation] ? 1 : 0);
    }, 0);
  }
  
  /**
   * Sets up the New Game button and modal
   */
  function setupNewGameButton() {
    const newGameBtn = document.getElementById('new-game-btn');
    const modal = document.getElementById('confirmation-modal');
    
    if (!newGameBtn || !modal) {
      console.error('New game button or modal not found');
      return;
    }
    
    const backBtn = document.getElementById('back-btn');
    const clearBtn = document.getElementById('clear-btn');
    
    if (!backBtn || !clearBtn) {
      console.error('Modal buttons not found');
      return;
    }
    
    // Show modal when New Game button is clicked
    newGameBtn.addEventListener('click', () => {
      modal.classList.remove('hidden');
    });
    
    // Hide modal when Back button is clicked
    backBtn.addEventListener('click', () => {
      modal.classList.add('hidden');
    });
    
    // Clear game data and start new game when Clear button is clicked
    clearBtn.addEventListener('click', () => {
      startNewGame();
      modal.classList.add('hidden');
    });
  }
  
  /**
   * Starts a new game by clearing saved data and resetting UI
   */
  function startNewGame() {
    // Clear localStorage
    localStorage.removeItem(STORAGE_KEY);
    localStorage.removeItem(START_DATE_KEY);
    
    // Set new start date
    localStorage.setItem(START_DATE_KEY, new Date().toISOString());
    
    // Update date created in header
    updateDateCreated();
    
    // Reinitialize the checklist
    initChecklist();
  }
  
  /**
   * Sets up the PWA install prompt
   */
  function setupPWAInstallPrompt() {
    // Variable to store the deferred prompt event
    let deferredPrompt;
    
    // Listen for beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
      // Prevent Chrome 76+ from automatically showing the prompt
      e.preventDefault();
      
      // Stash the event so it can be triggered later
      deferredPrompt = e;
      
      // Show install banner
      showInstallBanner(deferredPrompt);
    });
  }
  
  /**
   * Shows the install banner for PWA installation
   * @param {Event} deferredPrompt - The stored beforeinstallprompt event
   */
  function showInstallBanner(deferredPrompt) {
    // Check if banner already exists
    if (document.getElementById('install-banner')) {
      return;
    }
    
    // Create banner element
    const banner = document.createElement('div');
    banner.id = 'install-banner';
    banner.style.position = 'fixed';
    banner.style.bottom = '0';
    banner.style.left = '0';
    banner.style.right = '0';
    banner.style.backgroundColor = '#4285f4';
    banner.style.color = 'white';
    banner.style.padding = '12px 16px';
    banner.style.display = 'flex';
    banner.style.justifyContent = 'space-between';
    banner.style.alignItems = 'center';
    banner.style.zIndex = '1000';
    
    // Add message and buttons
    banner.innerHTML = `
      <div>Install License Plate Game for offline use?</div>
      <div>
        <button id="install-no" style="margin-right: 10px; padding: 8px 12px; background: transparent; color: white; border: 1px solid white; border-radius: 4px; cursor: pointer;">Not now</button>
        <button id="install-yes" style="padding: 8px 12px; background: white; color: #4285f4; border: none; border-radius: 4px; cursor: pointer;">Install</button>
      </div>
    `;
    
    // Add banner to page
    document.body.appendChild(banner);
    
    // Set up button handlers
    document.getElementById('install-no').addEventListener('click', () => {
      banner.remove();
    });
    
    document.getElementById('install-yes').addEventListener('click', async () => {
      // Hide banner
      banner.remove();
      
      // Show install prompt
      deferredPrompt.prompt();
      
      // Wait for user response
      const { outcome } = await deferredPrompt.userChoice;
      console.log(`User ${outcome} the installation`);
    });
  }
});