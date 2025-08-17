<?php
session_start();

// Configuration
$API_BASE = 'http://localhost:3001/api';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user']) && isset($_SESSION['token']);

// Handle logout message
$logoutMessage = '';
if (isset($_GET['logout'])) {
    $logoutMessage = 'You have been successfully logged out.';
}

// Handle quick login
$error = '';
if ($_POST && isset($_POST['quickLogin'])) {
    $data = [
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? ''
    ];
    
    $response = callAPI('POST', $API_BASE . '/auth/login', $data);
    
    if ($response && isset($response['token'])) {
        $_SESSION['token'] = $response['token'];
        $_SESSION['user'] = $response['user'];
        header('Location: index.php');
        exit;
    } else {
        $error = $response['error'] ?? 'Login failed. Please check your credentials.';
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
    <title>SmokeoutNYC - Track NYC Smoke Shop Closures & Operation Smokeout</title>
    <meta name="description" content="Your comprehensive resource for tracking smoke shop closures and Operation Smokeout enforcement in New York City. Stay informed about cannabis, weed laws, and smoke shop status updates.">
    <meta name="keywords" content="Operation Smokeout, NYC smoke shops, cannabis, weed, marijuana, tobacco shops, New York City, smoke shop closures">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .weed-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d1fae5' fill-opacity='0.1'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .stats-counter {
            animation: countUp 2s ease-out;
        }
        @keyframes countUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
                    <span class="text-blue-600 font-medium">
                        <i class="fas fa-home mr-1"></i>Home
                    </span>
                    <a href="search.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-search mr-1"></i>Search
                    </a>
                    <a href="news.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-newspaper mr-1"></i>News
                    </a>
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
    <div class="hero-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Hero Content -->
                <div>
                    <h1 class="text-4xl md:text-6xl font-bold mb-6">
                        Track NYC <span class="text-yellow-300">Smoke Shop</span> Closures
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 opacity-90">
                        Stay informed about Operation Smokeout enforcement, cannabis laws, and smoke shop status updates across New York City.
                    </p>
                    
                    <!-- Quick Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                        <div class="text-center">
                            <div class="text-2xl font-bold stats-counter">1,247</div>
                            <div class="text-sm opacity-75">Total Shops</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-red-300 stats-counter">423</div>
                            <div class="text-sm opacity-75">Operation Smokeout</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-300 stats-counter">687</div>
                            <div class="text-sm opacity-75">Still Open</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-300 stats-counter">137</div>
                            <div class="text-sm opacity-75">Other Closures</div>
                        </div>
                    </div>

                    <!-- CTA Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="search.php" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors flex items-center justify-center">
                            <i class="fas fa-search mr-2"></i>
                            Search Smoke Shops
                        </a>
                        <a href="news.php" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors flex items-center justify-center">
                            <i class="fas fa-newspaper mr-2"></i>
                            Latest News
                        </a>
                    </div>
                </div>

                <!-- Login Form (if not logged in) -->
                <?php if (!$isLoggedIn): ?>
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-8">
                    <h3 class="text-2xl font-bold mb-6 text-center">Quick Login</h3>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-500 bg-opacity-20 border border-red-300 rounded-md p-3 mb-4">
                            <div class="text-sm text-red-100"><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($logoutMessage): ?>
                        <div class="bg-green-500 bg-opacity-20 border border-green-300 rounded-md p-3 mb-4">
                            <div class="text-sm text-green-100"><?php echo htmlspecialchars($logoutMessage); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <input type="email" name="email" required
                                   class="form-input w-full px-4 py-3 rounded-lg border-0 bg-white bg-opacity-20 text-white placeholder-gray-200 focus:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-white"
                                   placeholder="Email Address"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div>
                            <input type="password" name="password" required
                                   class="form-input w-full px-4 py-3 rounded-lg border-0 bg-white bg-opacity-20 text-white placeholder-gray-200 focus:bg-opacity-30 focus:outline-none focus:ring-2 focus:ring-white"
                                   placeholder="Password">
                        </div>
                        <button type="submit" name="quickLogin"
                                class="w-full bg-yellow-500 text-gray-900 px-4 py-3 rounded-lg font-semibold hover:bg-yellow-400 transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Sign In
                        </button>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p class="text-sm opacity-75">
                            Don't have an account? 
                            <a href="signup.php" class="text-yellow-300 hover:text-yellow-200 font-medium">Sign up here</a>
                        </p>
                        <p class="text-xs opacity-60 mt-2">
                            <a href="login.php" class="hover:text-yellow-200">Forgot password?</a>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <!-- Welcome Back Message -->
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-8 text-center">
                    <div class="mb-4">
                        <i class="fas fa-user-circle text-6xl text-green-300"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-2">
                        Welcome back, <?php echo htmlspecialchars($_SESSION['user']['firstName'] ?? $_SESSION['user']['username'] ?? 'User'); ?>!
                    </h3>
                    <p class="opacity-75 mb-6">Ready to explore smoke shop updates?</p>
                    <div class="space-y-3">
                        <a href="add.php" class="block w-full bg-green-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-600 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add a Smoke Shop
                        </a>
                        <a href="search.php" class="block w-full bg-blue-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-600 transition-colors">
                            <i class="fas fa-search mr-2"></i>Search Shops
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    Stay Informed About NYC Cannabis & Smoke Shops
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Track Operation Smokeout enforcement, discover legal dispensaries, and stay updated on NYC cannabis laws.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="card-hover bg-gray-50 rounded-lg p-6">
                    <div class="text-4xl text-red-600 mb-4">
                        <i class="fas fa-ban"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Operation Smokeout Tracking</h3>
                    <p class="text-gray-600">
                        Real-time updates on smoke shops closed due to Operation Smokeout enforcement. Stay informed about which locations have been affected.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="card-hover bg-gray-50 rounded-lg p-6">
                    <div class="text-4xl text-green-600 mb-4">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Interactive Map Search</h3>
                    <p class="text-gray-600">
                        Find smoke shops near you with our interactive map. Filter by status, location, and get real-time updates on store availability.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="card-hover bg-gray-50 rounded-lg p-6">
                    <div class="text-4xl text-blue-600 mb-4">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Latest Cannabis News</h3>
                    <p class="text-gray-600">
                        Stay updated with the latest news about NYC cannabis laws, regulations, and industry developments that affect smoke shops.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="card-hover bg-gray-50 rounded-lg p-6">
                    <div class="text-4xl text-purple-600 mb-4">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Community Updates</h3>
                    <p class="text-gray-600">
                        Join our community to share updates, report store status changes, and help keep everyone informed about smoke shop availability.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="card-hover bg-gray-50 rounded-lg p-6">
                    <div class="text-4xl text-yellow-600 mb-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Legal Information</h3>
                    <p class="text-gray-600">
                        Access up-to-date information about NYC cannabis laws, regulations, and what's legal for consumers and businesses.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="card-hover bg-gray-50 rounded-lg p-6">
                    <div class="text-4xl text-indigo-600 mb-4">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Mobile Friendly</h3>
                    <p class="text-gray-600">
                        Access all features on any device. Our mobile-optimized platform ensures you stay informed wherever you are.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cannabis Information Section -->
    <div class="py-16 bg-gradient-to-br from-green-50 to-blue-50 weed-pattern">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-leaf text-green-600 mr-3"></i>
                    NYC Cannabis & Weed Information
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Everything you need to know about cannabis laws, dispensaries, and smoke shops in New York City.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Legal Information -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-gavel text-blue-600 mr-3"></i>
                        NYC Cannabis Laws
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="border-l-4 border-green-500 pl-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Legal for Adults 21+</h4>
                            <p class="text-gray-600">
                                Adults 21 and older can legally possess up to 3 ounces of cannabis and 24 grams of concentrated cannabis in New York.
                            </p>
                        </div>

                        <div class="border-l-4 border-yellow-500 pl-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Home Cultivation</h4>
                            <p class="text-gray-600">
                                Adults can grow up to 3 mature and 3 immature plants at home, with a maximum of 12 plants per household.
                            </p>
                        </div>

                        <div class="border-l-4 border-red-500 pl-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Public Consumption</h4>
                            <p class="text-gray-600">
                                Cannabis can be consumed in designated areas, but not in public spaces where tobacco smoking is prohibited.
                            </p>
                        </div>

                        <div class="border-l-4 border-purple-500 pl-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Licensed Dispensaries</h4>
                            <p class="text-gray-600">
                                Only state-licensed dispensaries can legally sell cannabis for adult use. Always verify licensing before purchasing.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Operation Smokeout Info -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                        Operation Smokeout
                    </h3>
                    
                    <div class="space-y-6">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h4 class="font-semibold text-red-900 mb-2">What is Operation Smokeout?</h4>
                            <p class="text-red-800 text-sm">
                                A coordinated enforcement action by NYC agencies targeting unlicensed cannabis retailers and smoke shops operating illegally.
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <div>
                                    <h5 class="font-medium text-gray-900">Protecting Consumers</h5>
                                    <p class="text-gray-600 text-sm">Ensuring products meet safety standards and are properly tested.</p>
                                </div>
                            </div>

                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <div>
                                    <h5 class="font-medium text-gray-900">Supporting Legal Businesses</h5>
                                    <p class="text-gray-600 text-sm">Protecting licensed dispensaries from unfair competition.</p>
                                </div>
                            </div>

                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <div>
                                    <h5 class="font-medium text-gray-900">Tax Revenue</h5>
                                    <p class="text-gray-600 text-sm">Ensuring proper tax collection for public services and programs.</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <p class="text-yellow-800 text-sm">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Stay Informed:</strong> Use our tracking system to find legal, licensed dispensaries in your area.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cannabis Education -->
            <div class="mt-12 bg-white rounded-lg shadow-lg p-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-indigo-600 mr-3"></i>
                    Cannabis Education & Safety
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-brain text-blue-600 text-2xl"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Know Your Limits</h4>
                        <p class="text-gray-600 text-sm">
                            Start low and go slow. Cannabis affects everyone differently, and edibles can take 2+ hours to take effect.
                        </p>
                    </div>

                    <div class="text-center">
                        <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-certificate text-green-600 text-2xl"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Buy from Licensed Stores</h4>
                        <p class="text-gray-600 text-sm">
                            Licensed dispensaries test their products for safety and potency. Look for the official license display.
                        </p>
                    </div>

                    <div class="text-center">
                        <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-car text-purple-600 text-2xl"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-2">Don't Drive Impaired</h4>
                        <p class="text-gray-600 text-sm">
                            Never drive under the influence of cannabis. It's illegal and dangerous. Plan safe transportation ahead.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="bg-gray-900 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-4">
                Help Keep Our Community Informed
            </h2>
            <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto">
                Join thousands of New Yorkers staying updated on smoke shop closures, Operation Smokeout enforcement, and cannabis law changes.
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <?php if (!$isLoggedIn): ?>
                <a href="signup.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>
                    Create Free Account
                </a>
                <?php endif; ?>
                <a href="add.php" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-gray-900 transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Add a Smoke Shop
                </a>
                <a href="search.php" class="bg-green-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>
                    Search Database
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-store-slash mr-2"></i>SmokeoutNYC
                    </h3>
                    <p class="text-gray-300 text-sm">
                        Your comprehensive resource for tracking smoke shop closures and Operation Smokeout enforcement in New York City.
                    </p>
                    <div class="flex space-x-4 mt-4">
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-facebook text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-300 hover:text-white transition-colors">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors">Home</a></li>
                        <li><a href="search.php" class="text-gray-300 hover:text-white transition-colors">Search Shops</a></li>
                        <li><a href="news.php" class="text-gray-300 hover:text-white transition-colors">Latest News</a></li>
                        <li><a href="add.php" class="text-gray-300 hover:text-white transition-colors">Add Shop</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Legal</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Cannabis Laws</a></li>
                        <li><a href="#" class="text-gray-300 hover:text-white transition-colors">Disclaimer</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact</h3>
                    <div class="space-y-2 text-sm text-gray-300">
                        <p><i class="fas fa-envelope mr-2"></i>info@smokeout.nyc</p>
                        <p><i class="fas fa-map-marker-alt mr-2"></i>New York City, NY</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-300">
                <p>&copy; 2024 SmokeoutNYC. All rights reserved. This site is for informational purposes only.</p>
                <p class="text-xs mt-2 opacity-75">
                    Please consume cannabis responsibly and in accordance with local laws.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Counter animation
        function animateCounters() {
            const counters = document.querySelectorAll('.stats-counter');
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                const duration = 2000; // 2 seconds
                const step = target / (duration / 16); // 60fps
                let current = 0;
                
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) {
                        counter.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 16);
            });
        }

        // Start animation when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateCounters, 500);
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.bg-green-500, .bg-red-500');
            messages.forEach(message => {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
