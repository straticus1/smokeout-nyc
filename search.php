<?php
session_start();

// Configuration
$API_BASE = 'http://localhost:3001/api';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user']) && isset($_SESSION['token']);

function callAPI($method, $url, $data = null, $token = null) {
    $curl = curl_init();
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $data ? json_encode($data) : null
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    return json_decode($response, true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Smoke Shops - SmokeoutNYC</title>
    <meta name="description" content="Search for smoke shops in NYC by name, location, or status. Find open stores, closed shops, and Operation Smokeout locations.">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .search-card {
            transition: all 0.3s ease;
        }
        .search-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-open { background-color: #d1fae5; color: #065f46; }
        .status-closed-operation { background-color: #fee2e2; color: #991b1b; }
        .status-closed-other { background-color: #fef3c7; color: #92400e; }
        .status-unknown { background-color: #f3f4f6; color: #374151; }
        
        .loading-spinner {
            border: 3px solid #f3f4f6;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        #map {
            height: 400px;
            border-radius: 8px;
        }
        
        .filter-chip {
            transition: all 0.2s ease;
        }
        .filter-chip.active {
            background-color: #3b82f6;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-xl font-bold text-gray-900">
                        <i class="fas fa-store-slash text-red-600 mr-2"></i>
                        SmokeoutNYC
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-home mr-1"></i>Home
                    </a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-newspaper mr-1"></i>News
                    </a>
                    <span class="text-blue-600 font-medium">
                        <i class="fas fa-search mr-1"></i>Search
                    </span>
                    <?php if ($isLoggedIn): ?>
                        <a href="add.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                            <i class="fas fa-plus mr-1"></i>Add Store
                        </a>
                        <div class="flex items-center space-x-3">
                            <span class="text-gray-700">
                                Welcome, <?php echo htmlspecialchars($_SESSION['user']['firstName'] ?? $_SESSION['user']['username'] ?? 'User'); ?>!
                            </span>
                            <a href="logout.php" class="text-red-600 hover:text-red-700 transition-colors">
                                <i class="fas fa-sign-out-alt mr-1"></i>Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                            <i class="fas fa-sign-in-alt mr-1"></i>Login
                        </a>
                        <a href="signup.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-user-plus mr-1"></i>Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">
                <i class="fas fa-search mr-3"></i>
                Search Smoke Shops
            </h1>
            <p class="text-xl md:text-2xl opacity-90 max-w-3xl mx-auto">
                Find smoke shops across NYC, check their status, and discover new locations
            </p>
        </div>
    </div>

    <!-- Search Interface -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Find Smoke Shops</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Search by Name -->
                <div>
                    <label for="searchName" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-store mr-1"></i>Shop Name
                    </label>
                    <input type="text" id="searchName" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter shop name...">
                </div>

                <!-- Search by City -->
                <div>
                    <label for="searchCity" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-city mr-1"></i>City
                    </label>
                    <input type="text" id="searchCity" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="New York, Brooklyn...">
                </div>

                <!-- Search by State -->
                <div>
                    <label for="searchState" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-map mr-1"></i>State
                    </label>
                    <select id="searchState" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All States</option>
                        <option value="NY" selected>New York</option>
                        <option value="NJ">New Jersey</option>
                        <option value="CT">Connecticut</option>
                    </select>
                </div>

                <!-- Search by Zip Code -->
                <div>
                    <label for="searchZip" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-mail-bulk mr-1"></i>Zip Code
                    </label>
                    <input type="text" id="searchZip" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="10001, 11201...">
                </div>
            </div>

            <!-- Location and Filters Row -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <!-- GeoIP Location Button -->
                <div class="flex-1">
                    <button onclick="useMyLocation()" 
                            class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors flex items-center justify-center">
                        <i class="fas fa-location-arrow mr-2"></i>
                        Use My Current Location
                    </button>
                    <p class="text-xs text-gray-500 mt-1">Find shops near your current location</p>
                </div>

                <!-- Distance Filter -->
                <div class="flex-1">
                    <label for="searchRadius" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-ruler-combined mr-1"></i>Search Radius
                    </label>
                    <select id="searchRadius" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="5">Within 5 miles</option>
                        <option value="10" selected>Within 10 miles</option>
                        <option value="25">Within 25 miles</option>
                        <option value="50">Within 50 miles</option>
                    </select>
                </div>
            </div>

            <!-- Status Filters -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    <i class="fas fa-filter mr-1"></i>Filter by Status
                </label>
                <div class="flex flex-wrap gap-2">
                    <button onclick="toggleFilter('all')" 
                            class="filter-chip active px-3 py-1 rounded-full border border-gray-300 text-sm font-medium">
                        All Shops
                    </button>
                    <button onclick="toggleFilter('OPEN')" 
                            class="filter-chip px-3 py-1 rounded-full border border-gray-300 text-sm font-medium">
                        <i class="fas fa-store text-green-600 mr-1"></i>Open
                    </button>
                    <button onclick="toggleFilter('CLOSED_OPERATION_SMOKEOUT')" 
                            class="filter-chip px-3 py-1 rounded-full border border-gray-300 text-sm font-medium">
                        <i class="fas fa-ban text-red-600 mr-1"></i>Operation Smokeout
                    </button>
                    <button onclick="toggleFilter('CLOSED_OTHER')" 
                            class="filter-chip px-3 py-1 rounded-full border border-gray-300 text-sm font-medium">
                        <i class="fas fa-times-circle text-yellow-600 mr-1"></i>Closed (Other)
                    </button>
                    <button onclick="toggleFilter('REOPENED')" 
                            class="filter-chip px-3 py-1 rounded-full border border-gray-300 text-sm font-medium">
                        <i class="fas fa-redo text-blue-600 mr-1"></i>Reopened
                    </button>
                </div>
            </div>

            <!-- Search Button -->
            <div class="flex gap-4">
                <button onclick="searchShops()" 
                        class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700 transition-colors font-medium">
                    <i class="fas fa-search mr-2"></i>Search Shops
                </button>
                <button onclick="clearSearch()" 
                        class="px-6 py-3 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors font-medium">
                    <i class="fas fa-times mr-2"></i>Clear
                </button>
            </div>
        </div>

        <!-- View Toggle -->
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center space-x-4">
                <h3 class="text-lg font-semibold text-gray-900">Search Results</h3>
                <span id="resultsCount" class="text-sm text-gray-500">0 shops found</span>
            </div>
            <div class="flex bg-gray-100 rounded-lg p-1">
                <button onclick="switchView('list')" id="listViewBtn"
                        class="view-btn active px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-list mr-2"></i>List View
                </button>
                <button onclick="switchView('map')" id="mapViewBtn"
                        class="view-btn px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-map mr-2"></i>Map View
                </button>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="flex justify-center items-center py-12 hidden">
            <div class="loading-spinner"></div>
        </div>

        <!-- Results Container -->
        <div id="resultsContainer">
            <!-- List View -->
            <div id="listView" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Results will be populated here -->
            </div>

            <!-- Map View -->
            <div id="mapView" class="hidden">
                <div id="map"></div>
                <div class="mt-4 bg-white rounded-lg shadow-md p-4">
                    <h4 class="font-semibold text-gray-900 mb-2">Legend</h4>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                            <span>Open</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                            <span>Operation Smokeout</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                            <span>Closed (Other)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                            <span>Reopened</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Results Message -->
        <div id="noResults" class="text-center py-12 hidden">
            <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">No shops found</h3>
            <p class="text-gray-500 mb-4">Try adjusting your search criteria or expanding your search area.</p>
            <button onclick="clearSearch()" class="text-blue-600 hover:text-blue-700 font-medium">
                Clear search and try again
            </button>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="flex justify-center items-center space-x-2 mt-8">
            <!-- Pagination will be loaded here -->
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-store-slash mr-2"></i>SmokeoutNYC
                    </h3>
                    <p class="text-gray-300">
                        Your comprehensive resource for tracking smoke shop closures and Operation Smokeout enforcement in New York City.
                    </p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors">Home</a></li>
                        <li><a href="news.php" class="text-gray-300 hover:text-white transition-colors">Latest News</a></li>
                        <li><a href="search.php" class="text-gray-300 hover:text-white transition-colors">Search Shops</a></li>
                        <li><a href="add.php" class="text-gray-300 hover:text-white transition-colors">Add Shop</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact</h3>
                    <p class="text-gray-300 mb-2">
                        <i class="fas fa-envelope mr-2"></i>
                        info@smokeout.nyc
                    </p>
                    <div class="flex space-x-4 mt-4">
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-facebook text-xl"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-300">
                <p>&copy; 2024 SmokeoutNYC. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const API_BASE = '<?php echo $API_BASE; ?>';
        let currentPage = 1;
        let totalPages = 1;
        let currentView = 'list';
        let activeFilter = 'all';
        let map = null;
        let markers = [];
        let userLocation = null;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
            // Load initial data
            searchShops();
        });

        // Initialize Leaflet map
        function initializeMap() {
            map = L.map('map').setView([40.7589, -73.9851], 11); // NYC center
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
        }

        // Search shops function
        async function searchShops(page = 1) {
            try {
                showLoading(true);
                hideNoResults();

                // Build search parameters
                const params = new URLSearchParams();
                params.append('page', page);
                params.append('limit', '12');

                const searchName = document.getElementById('searchName').value.trim();
                const searchCity = document.getElementById('searchCity').value.trim();
                const searchState = document.getElementById('searchState').value;
                const searchZip = document.getElementById('searchZip').value.trim();
                const searchRadius = document.getElementById('searchRadius').value;

                if (searchName) params.append('search', searchName);
                if (activeFilter !== 'all') params.append('status', activeFilter);

                // Add location-based search
                if (userLocation) {
                    params.append('lat', userLocation.lat);
                    params.append('lng', userLocation.lng);
                    params.append('radius', searchRadius);
                }

                // Build address search
                let addressParts = [];
                if (searchCity) addressParts.push(searchCity);
                if (searchState) addressParts.push(searchState);
                if (searchZip) addressParts.push(searchZip);
                
                if (addressParts.length > 0) {
                    params.append('search', addressParts.join(' '));
                }

                const response = await fetch(`${API_BASE}/stores?${params}`);
                if (!response.ok) {
                    throw new Error('Failed to fetch shops');
                }

                const data = await response.json();
                
                if (data.stores && data.stores.length > 0) {
                    displayResults(data.stores);
                    updatePagination(data.pagination);
                    document.getElementById('resultsCount').textContent = 
                        `${data.pagination.total} shops found`;
                } else {
                    showNoResults();
                    document.getElementById('resultsCount').textContent = '0 shops found';
                }
                
                currentPage = page;
                totalPages = data.pagination ? data.pagination.pages : 1;
                
            } catch (error) {
                console.error('Error searching shops:', error);
                showError('Failed to search shops. Please try again.');
            } finally {
                showLoading(false);
            }
        }

        // Display search results
        function displayResults(shops) {
            if (currentView === 'list') {
                displayListView(shops);
            } else {
                displayMapView(shops);
            }
        }

        // Display list view
        function displayListView(shops) {
            const listView = document.getElementById('listView');
            listView.innerHTML = '';

            shops.forEach(shop => {
                const shopCard = createShopCard(shop);
                listView.appendChild(shopCard);
            });
        }

        // Display map view
        function displayMapView(shops) {
            // Clear existing markers
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];

            if (shops.length === 0) return;

            const bounds = [];

            shops.forEach(shop => {
                if (shop.latitude && shop.longitude) {
                    const color = getStatusColor(shop.status);
                    
                    const marker = L.circleMarker([shop.latitude, shop.longitude], {
                        color: color,
                        fillColor: color,
                        fillOpacity: 0.8,
                        radius: 8
                    }).addTo(map);

                    const popupContent = `
                        <div class="p-2">
                            <h4 class="font-semibold">${escapeHtml(shop.name)}</h4>
                            <p class="text-sm text-gray-600">${escapeHtml(shop.address)}</p>
                            <div class="mt-2">
                                <span class="status-badge ${getStatusClass(shop.status)}">
                                    ${getStatusText(shop.status)}
                                </span>
                            </div>
                            ${shop.phone ? `<p class="text-sm mt-1"><i class="fas fa-phone mr-1"></i>${shop.phone}</p>` : ''}
                            ${shop.website ? `<p class="text-sm"><a href="${shop.website}" target="_blank" class="text-blue-600"><i class="fas fa-globe mr-1"></i>Website</a></p>` : ''}
                        </div>
                    `;

                    marker.bindPopup(popupContent);
                    markers.push(marker);
                    bounds.push([shop.latitude, shop.longitude]);
                }
            });

            // Fit map to show all markers
            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [20, 20] });
            }
        }

        // Create shop card for list view
        function createShopCard(shop) {
            const card = document.createElement('div');
            card.className = 'search-card bg-white rounded-lg shadow-md p-6';

            const averageRating = shop.averageRating ? 
                `<div class="flex items-center mt-2">
                    ${generateStars(shop.averageRating)}
                    <span class="ml-2 text-sm text-gray-600">${shop.averageRating}/5 (${shop.totalComments || 0} reviews)</span>
                </div>` : '';

            card.innerHTML = `
                <div class="flex justify-between items-start mb-3">
                    <h3 class="text-xl font-semibold text-gray-900">${escapeHtml(shop.name)}</h3>
                    <span class="status-badge ${getStatusClass(shop.status)}">
                        ${getStatusText(shop.status)}
                    </span>
                </div>
                
                <div class="space-y-2 text-gray-600">
                    <p class="flex items-center">
                        <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                        ${escapeHtml(shop.address)}
                    </p>
                    ${shop.phone ? `
                        <p class="flex items-center">
                            <i class="fas fa-phone mr-2 text-gray-400"></i>
                            <a href="tel:${shop.phone}" class="hover:text-blue-600">${shop.phone}</a>
                        </p>
                    ` : ''}
                    ${shop.website ? `
                        <p class="flex items-center">
                            <i class="fas fa-globe mr-2 text-gray-400"></i>
                            <a href="${shop.website}" target="_blank" class="hover:text-blue-600">Visit Website</a>
                        </p>
                    ` : ''}
                </div>

                ${averageRating}

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            ${shop.owner ? `Owner: ${escapeHtml(shop.owner.firstName || shop.owner.username || 'Unknown')}` : 'Owner: Unknown'}
                        </div>
                        <button onclick="viewShopDetails('${shop.id}')" 
                                class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                            View Details <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                </div>
            `;

            return card;
        }

        // Use user's current location
        function useMyLocation() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                return;
            }

            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Getting location...';
            button.disabled = true;

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };

                    // Update the map center
                    if (map) {
                        map.setView([userLocation.lat, userLocation.lng], 13);
                        
                        // Add user location marker
                        L.marker([userLocation.lat, userLocation.lng], {
                            icon: L.divIcon({
                                className: 'user-location-marker',
                                html: '<i class="fas fa-crosshairs text-blue-600 text-2xl"></i>',
                                iconSize: [30, 30],
                                iconAnchor: [15, 15]
                            })
                        }).addTo(map).bindPopup('Your Location');
                    }

                    // Trigger search with location
                    searchShops();

                    button.innerHTML = originalText;
                    button.disabled = false;
                },
                function(error) {
                    let errorMessage = 'Unable to retrieve your location.';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Location access denied. Please enable location services.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Location request timed out.';
                            break;
                    }
                    alert(errorMessage);
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            );
        }

        // Filter functions
        function toggleFilter(status) {
            // Update active filter
            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.remove('active');
            });
            event.target.classList.add('active');
            
            activeFilter = status;
            searchShops(1); // Reset to first page
        }

        // View switching
        function switchView(view) {
            currentView = view;
            
            // Update button states
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-blue-600', 'text-white');
                btn.classList.add('text-gray-600');
            });
            
            if (view === 'list') {
                document.getElementById('listViewBtn').classList.add('active', 'bg-blue-600', 'text-white');
                document.getElementById('listView').classList.remove('hidden');
                document.getElementById('mapView').classList.add('hidden');
            } else {
                document.getElementById('mapViewBtn').classList.add('active', 'bg-blue-600', 'text-white');
                document.getElementById('listView').classList.add('hidden');
                document.getElementById('mapView').classList.remove('hidden');
                
                // Refresh map view with current results
                setTimeout(() => {
                    if (map) {
                        map.invalidateSize();
                    }
                }, 100);
            }
        }

        // Clear search
        function clearSearch() {
            document.getElementById('searchName').value = '';
            document.getElementById('searchCity').value = '';
            document.getElementById('searchState').value = '';
            document.getElementById('searchZip').value = '';
            document.getElementById('searchRadius').value = '10';
            
            // Reset filter
            activeFilter = 'all';
            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.remove('active');
            });
            document.querySelector('.filter-chip').classList.add('active');
            
            // Clear user location
            userLocation = null;
            
            // Search again
            searchShops(1);
        }

        // View shop details
        function viewShopDetails(shopId) {
            // This could open a modal or navigate to a detail page
            window.open(`shop-details.php?id=${shopId}`, '_blank');
        }

        // Utility functions
        function getStatusColor(status) {
            const colors = {
                'OPEN': '#10b981',
                'CLOSED_OPERATION_SMOKEOUT': '#ef4444',
                'CLOSED_OTHER': '#f59e0b',
                'CLOSED_UNKNOWN': '#6b7280',
                'REOPENED': '#3b82f6'
            };
            return colors[status] || colors['CLOSED_UNKNOWN'];
        }

        function getStatusClass(status) {
            const classes = {
                'OPEN': 'status-open',
                'CLOSED_OPERATION_SMOKEOUT': 'status-closed-operation',
                'CLOSED_OTHER': 'status-closed-other',
                'CLOSED_UNKNOWN': 'status-unknown',
                'REOPENED': 'status-open'
            };
            return classes[status] || classes['CLOSED_UNKNOWN'];
        }

        function getStatusText(status) {
            const texts = {
                'OPEN': 'Open',
                'CLOSED_OPERATION_SMOKEOUT': 'Operation Smokeout',
                'CLOSED_OTHER': 'Closed',
                'CLOSED_UNKNOWN': 'Status Unknown',
                'REOPENED': 'Reopened'
            };
            return texts[status] || texts['CLOSED_UNKNOWN'];
        }

        function generateStars(rating) {
            const fullStars = Math.floor(rating);
            const hasHalfStar = rating % 1 >= 0.5;
            let stars = '';
            
            for (let i = 0; i < fullStars; i++) {
                stars += '<i class="fas fa-star text-yellow-400"></i>';
            }
            
            if (hasHalfStar) {
                stars += '<i class="fas fa-star-half-alt text-yellow-400"></i>';
            }
            
            const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
            for (let i = 0; i < emptyStars; i++) {
                stars += '<i class="far fa-star text-gray-300"></i>';
            }
            
            return stars;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showLoading(show) {
            const spinner = document.getElementById('loadingSpinner');
            const results = document.getElementById('resultsContainer');
            
            if (show) {
                spinner.classList.remove('hidden');
                results.classList.add('hidden');
            } else {
                spinner.classList.add('hidden');
                results.classList.remove('hidden');
            }
        }

        function showNoResults() {
            document.getElementById('noResults').classList.remove('hidden');
            document.getElementById('resultsContainer').classList.add('hidden');
        }

        function hideNoResults() {
            document.getElementById('noResults').classList.add('hidden');
        }

        function showError(message) {
            alert(message);
        }

        function updatePagination(pagination) {
            // Implementation similar to news.php pagination
            const paginationDiv = document.getElementById('pagination');
            paginationDiv.innerHTML = '';

            if (!pagination || pagination.pages <= 1) {
                return;
            }

            const { page, pages } = pagination;

            // Previous button
            if (page > 1) {
                const prevBtn = createPaginationButton(page - 1, '<i class="fas fa-chevron-left"></i>');
                paginationDiv.appendChild(prevBtn);
            }

            // Page numbers (simplified)
            for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
                const btn = createPaginationButton(i, i.toString(), i === page);
                paginationDiv.appendChild(btn);
            }

            // Next button
            if (page < pages) {
                const nextBtn = createPaginationButton(page + 1, '<i class="fas fa-chevron-right"></i>');
                paginationDiv.appendChild(nextBtn);
            }
        }

        function createPaginationButton(pageNum, text, isActive = false) {
            const button = document.createElement('button');
            button.className = `px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                isActive 
                    ? 'bg-blue-600 text-white' 
                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
            }`;
            button.innerHTML = text;
            button.onclick = () => searchShops(pageNum);
            return button;
        }
    </script>
</body>
</html>
