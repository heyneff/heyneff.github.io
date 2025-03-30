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

  // States data - all 50 US states plus DC
  const states = [
    'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 
    'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia', 
    'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 
    'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 
    'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 
    'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 
    'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 
    'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 
    'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 
    'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming',
    'District of Columbia'
  ];

  // Initialize the game
  initGame();

  function initGame() {
    const statesGrid = document.getElementById('states-grid');
    
    // Load saved state from localStorage
    const savedStates = JSON.parse(localStorage.getItem('licensePlateGame')) || {};
    
    // Create UI for each state
    states.forEach(state => {
      const stateAbbr = getStateAbbreviation(state);
      const stateElement = document.createElement('div');
      stateElement.className = 'state-item';
      if (savedStates[stateAbbr]) {
        stateElement.classList.add('found');
      }
      
      stateElement.textContent = stateAbbr;
      stateElement.title = state;
      
      stateElement.addEventListener('click', () => {
        toggleState(stateElement, stateAbbr);
      });
      
      statesGrid.appendChild(stateElement);
    });
  }

  function toggleState(element, stateAbbr) {
    // Toggle visual state
    element.classList.toggle('found');
    
    // Update localStorage
    const savedStates = JSON.parse(localStorage.getItem('licensePlateGame')) || {};
    
    if (element.classList.contains('found')) {
      savedStates[stateAbbr] = true;
    } else {
      delete savedStates[stateAbbr];
    }
    
    localStorage.setItem('licensePlateGame', JSON.stringify(savedStates));
  }

  // Helper function to get state abbreviations
  function getStateAbbreviation(stateName) {
    const abbreviations = {
      'Alabama': 'AL', 'Alaska': 'AK', 'Arizona': 'AZ', 'Arkansas': 'AR', 'California': 'CA',
      'Colorado': 'CO', 'Connecticut': 'CT', 'Delaware': 'DE', 'Florida': 'FL', 'Georgia': 'GA',
      'Hawaii': 'HI', 'Idaho': 'ID', 'Illinois': 'IL', 'Indiana': 'IN', 'Iowa': 'IA',
      'Kansas': 'KS', 'Kentucky': 'KY', 'Louisiana': 'LA', 'Maine': 'ME', 'Maryland': 'MD',
      'Massachusetts': 'MA', 'Michigan': 'MI', 'Minnesota': 'MN', 'Mississippi': 'MS', 'Missouri': 'MO',
      'Montana': 'MT', 'Nebraska': 'NE', 'Nevada': 'NV', 'New Hampshire': 'NH', 'New Jersey': 'NJ',
      'New Mexico': 'NM', 'New York': 'NY', 'North Carolina': 'NC', 'North Dakota': 'ND', 'Ohio': 'OH',
      'Oklahoma': 'OK', 'Oregon': 'OR', 'Pennsylvania': 'PA', 'Rhode Island': 'RI', 'South Carolina': 'SC',
      'South Dakota': 'SD', 'Tennessee': 'TN', 'Texas': 'TX', 'Utah': 'UT', 'Vermont': 'VT',
      'Virginia': 'VA', 'Washington': 'WA', 'West Virginia': 'WV', 'Wisconsin': 'WI', 'Wyoming': 'WY',
      'District of Columbia': 'DC'
    };
    
    return abbreviations[stateName] || stateName;
  }
});