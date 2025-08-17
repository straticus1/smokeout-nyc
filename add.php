<?php
session_start();

// Configuration
$API_BASE = 'http://localhost:3001/api';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user']) && isset($_SESSION['token']);

// Handle form submission
$error = '';
$success = '';

if ($_POST && isset($_POST['addStore'])) {
    if (!$isLoggedIn) {
        $error = 'You must be logged in to add a store.';
    } else {
        // Prepare store data
        $storeData = [
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'latitude' => floatval($_POST['latitude'] ?? 0),
            'longitude' => floatval($_POST['longitude'] ?? 0),
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'website' => $_POST['website'] ?? '',
            'description' => $_POST['description'] ?? '',
            'status' => $_POST['status'] ?? 'OPEN',
            'hours' => [
                'Monday' => $_POST['hours_monday'] ?? '',
                'Tuesday' => $_POST['hours_tuesday'] ?? '',
                'Wednesday' => $_POST['hours_wednesday'] ?? '',
                'Thursday' => $_POST['hours_thursday'] ?? '',
                'Friday' => $_POST['hours_friday'] ?? '',
                'Saturday' => $_POST['hours_saturday'] ?? '',
                'Sunday' => $_POST['hours_sunday'] ?? ''
            ]
        ];

        // Call API to add store
        $response = callAPI('POST', $API_BASE . '/stores', $storeData, $_SESSION['token']);
        
        if ($response && isset($response['id'])) {
            $success = 'Store submitted successfully! It will be reviewed by administrators before appearing on the site.';
        } else {
            $error = $response['error'] ?? 'Failed to add store. Please try again.';
        }
    }
}

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
    <title>Add Smoke Shop - SmokeoutNYC</title>
    <meta name="description" content="Submit a new smoke shop to the SmokeoutNYC database. Help keep our community informed about smoke shop locations and status.">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        #map {
            height: 300px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
        }
        .location-marker {
            background-color: #3b82f6;
            border: 2px solid white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
        }
        .required::after {
            content: " *";
            color: #ef4444;
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
                    <a href="search.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-search mr-1"></i>Search
                    </a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-newspaper mr-1"></i>News
                    </a>
                    <span class="text-blue-600 font-medium">
                        <i class="fas fa-plus mr-1"></i>Add Store
                    </span>
                    <?php if ($isLoggedIn): ?>
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
    <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">
                <i class="fas fa-plus-circle mr-3"></i>
                Add a Smoke Shop
            </h1>
            <p class="text-xl md:text-2xl opacity-90 max-w-3xl mx-auto">
                Help keep our community informed by adding smoke shops to our database
            </p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if (!$isLoggedIn): ?>
            <!-- Not logged in message -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-6 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-400 mr-3 text-2xl"></i>
                    <div>
                        <h3 class="text-lg font-semibold text-yellow-800 mb-2">Login Required</h3>
                        <p class="text-yellow-700 mb-4">
                            You must be logged in to add a smoke shop to our database. This helps us maintain quality and prevent spam.
                        </p>
                        <div class="flex space-x-4">
                            <a href="login.php?redirect=add.php" 
                               class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 transition-colors">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login
                            </a>
                            <a href="signup.php" 
                               class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                                <i class="fas fa-user-plus mr-2"></i>Create Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Add Store Form -->
            <div class="bg-white rounded-lg shadow-md p-8">
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Submit New Smoke Shop</h2>
                    <p class="text-gray-600">
                        Please provide accurate information about the smoke shop. All submissions will be reviewed by our administrators before being published.
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle text-red-400 mr-3 mt-0.5"></i>
                            <div class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                        <div class="flex">
                            <i class="fas fa-check-circle text-green-400 mr-3 mt-0.5"></i>
                            <div class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 required">
                                Store Name
                            </label>
                            <input type="text" id="name" name="name" required
                                   class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter store name"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">
                                Current Status
                            </label>
                            <select id="status" name="status"
                                    class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="OPEN" <?php echo ($_POST['status'] ?? 'OPEN') === 'OPEN' ? 'selected' : ''; ?>>Open</option>
                                <option value="CLOSED_OTHER" <?php echo ($_POST['status'] ?? '') === 'CLOSED_OTHER' ? 'selected' : ''; ?>>Closed</option>
                                <option value="CLOSED_UNKNOWN" <?php echo ($_POST['status'] ?? '') === 'CLOSED_UNKNOWN' ? 'selected' : ''; ?>>Status Unknown</option>
                            </select>
                        </div>
                    </div>

                    <!-- Address and Location -->
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 required">
                            Full Address
                        </label>
                        <input type="text" id="address" name="address" required
                               class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="123 Main St, New York, NY 10001"
                               value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                               onchange="geocodeAddress()">
                        <p class="mt-1 text-sm text-gray-500">
                            Enter the complete address. We'll automatically find the coordinates.
                        </p>
                    </div>

                    <!-- Map for Location Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            Location on Map
                        </label>
                        <div id="map"></div>
                        <p class="mt-2 text-sm text-gray-500">
                            Click on the map to set the exact location, or it will be set automatically from the address.
                        </p>
                        
                        <!-- Hidden coordinate fields -->
                        <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                    </div>

                    <!-- Contact Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">
                                Phone Number
                            </label>
                            <input type="tel" id="phone" name="phone"
                                   class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="(212) 555-0123"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                Email Address
                            </label>
                            <input type="email" id="email" name="email"
                                   class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="store@example.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div>
                        <label for="website" class="block text-sm font-medium text-gray-700">
                            Website URL
                        </label>
                        <input type="url" id="website" name="website"
                               class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="https://www.example.com"
                               value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>">
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">
                            Description
                        </label>
                        <textarea id="description" name="description" rows="4"
                                  class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Describe the store, products available, special features, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <p class="mt-1 text-sm text-gray-500">
                            Optional: Provide additional information about the store.
                        </p>
                    </div>

                    <!-- Store Hours -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            <i class="fas fa-clock mr-1"></i>
                            Store Hours (Optional)
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day): 
                            ?>
                                <div>
                                    <label for="hours_<?php echo strtolower($day); ?>" class="block text-xs font-medium text-gray-600 mb-1">
                                        <?php echo $day; ?>
                                    </label>
                                    <input type="text" 
                                           id="hours_<?php echo strtolower($day); ?>" 
                                           name="hours_<?php echo strtolower($day); ?>"
                                           class="form-input block w-full px-2 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                           placeholder="9:00 AM - 9:00 PM"
                                           value="<?php echo htmlspecialchars($_POST['hours_' . strtolower($day)] ?? ''); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            Enter hours in any format (e.g., "9:00 AM - 9:00 PM", "Closed", "24 hours")
                        </p>
                    </div>

                    <!-- Submission Guidelines -->
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <h4 class="font-semibold text-blue-900 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>
                            Submission Guidelines
                        </h4>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li>• All submissions are reviewed by administrators before publication</li>
                            <li>• Please provide accurate and up-to-date information</li>
                            <li>• Duplicate submissions will be removed</li>
                            <li>• You may be contacted for verification</li>
                            <li>• False information may result in account suspension</li>
                        </ul>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-4">
                        <button type="button" onclick="clearForm()" 
                                class="px-6 py-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-times mr-2"></i>Clear Form
                        </button>
                        <button type="submit" name="addStore"
                                class="px-6 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-paper-plane mr-2"></i>Submit for Review
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
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
                        Help keep our community informed about smoke shop locations and Operation Smokeout enforcement.
                    </p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors">Home</a></li>
                        <li><a href="search.php" class="text-gray-300 hover:text-white transition-colors">Search Shops</a></li>
                        <li><a href="news.php" class="text-gray-300 hover:text-white transition-colors">Latest News</a></li>
                        <li><a href="add.php" class="text-gray-300 hover:text-white transition-colors">Add Shop</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact</h3>
                    <p class="text-gray-300 mb-2">
                        <i class="fas fa-envelope mr-2"></i>
                        info@smokeout.nyc
                    </p>
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
        let map;
        let marker;

        // Initialize map
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
        });

        function initializeMap() {
            // Initialize map centered on NYC
            map = L.map('map').setView([40.7589, -73.9851], 11);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add click handler to set location
            map.on('click', function(e) {
                setLocation(e.latlng.lat, e.latlng.lng);
            });

            // Set initial marker if coordinates exist
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            if (lat && lng) {
                setLocation(parseFloat(lat), parseFloat(lng));
            }
        }

        function setLocation(lat, lng) {
            // Remove existing marker
            if (marker) {
                map.removeLayer(marker);
            }

            // Add new marker
            marker = L.marker([lat, lng]).addTo(map);
            
            // Update form fields
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            // Center map on marker
            map.setView([lat, lng], 15);
        }

        function geocodeAddress() {
            const address = document.getElementById('address').value;
            if (!address) return;

            // Use a geocoding service (you would need to implement this with your preferred provider)
            // For now, we'll use a simple approach with the browser's geolocation as fallback
            
            // This is a placeholder - in production you'd use Google Maps Geocoding API or similar
            console.log('Geocoding address:', address);
            
            // You could implement geocoding here using:
            // - Google Maps Geocoding API
            // - Mapbox Geocoding API
            // - OpenStreetMap Nominatim
        }

        function clearForm() {
            if (confirm('Are you sure you want to clear all form data?')) {
                document.querySelector('form').reset();
                
                // Clear map marker
                if (marker) {
                    map.removeLayer(marker);
                    marker = null;
                }
                
                // Clear coordinate fields
                document.getElementById('latitude').value = '';
                document.getElementById('longitude').value = '';
            }
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const address = document.getElementById('address').value.trim();
            
            if (!name) {
                alert('Please enter a store name');
                e.preventDefault();
                return;
            }
            
            if (!address) {
                alert('Please enter a store address');
                e.preventDefault();
                return;
            }

            // Check if location is set
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            
            if (!lat || !lng) {
                alert('Please set the location on the map by clicking on it or entering a complete address');
                e.preventDefault();
                return;
            }
        });

        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
