<?php
session_start();

// Configuration
$API_BASE = 'http://localhost:3001/api';

// Handle form submission
$error = '';
$success = '';

if ($_POST) {
    if (isset($_POST['login'])) {
        // Regular login
        $data = [
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? ''
        ];
        
        // Call API to login user
        $response = callAPI('POST', $API_BASE . '/auth/login', $data);
        
        if ($response && isset($response['token'])) {
            $_SESSION['token'] = $response['token'];
            $_SESSION['user'] = $response['user'];
            
            // Redirect to intended page or dashboard
            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $response['error'] ?? 'Login failed. Please check your credentials.';
        }
    }
}

// Handle OAuth callback
if (isset($_GET['token'])) {
    $_SESSION['token'] = $_GET['token'];
    
    // Get user info with the token
    $userResponse = callAPI('GET', $API_BASE . '/auth/me', null, $_GET['token']);
    if ($userResponse && isset($userResponse['user'])) {
        $_SESSION['user'] = $userResponse['user'];
        $success = 'Successfully logged in!';
        
        // Redirect after a short delay
        header('refresh:2;url=index.php');
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

// Check if user is already logged in
$isLoggedIn = isset($_SESSION['user']) && isset($_SESSION['token']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmokeoutNYC</title>
    <meta name="description" content="Login to your SmokeoutNYC account to access personalized features and track smoke shop updates">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .oauth-btn {
            transition: all 0.3s ease;
        }
        .oauth-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .login-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
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
                    <?php if (!$isLoggedIn): ?>
                        <a href="signup.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-user-plus mr-1"></i>Sign Up
                        </a>
                    <?php else: ?>
                        <div class="flex items-center space-x-3">
                            <span class="text-gray-700">
                                Welcome, <?php echo htmlspecialchars($_SESSION['user']['firstName'] ?? $_SESSION['user']['username'] ?? 'User'); ?>!
                            </span>
                            <a href="logout.php" class="text-red-600 hover:text-red-700 transition-colors">
                                <i class="fas fa-sign-out-alt mr-1"></i>Logout
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <?php if ($isLoggedIn): ?>
        <!-- Already logged in view -->
        <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md w-full space-y-8 text-center">
                <div>
                    <div class="mx-auto h-16 w-16 bg-green-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-check text-white text-2xl"></i>
                    </div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                        You're logged in!
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Welcome back, <?php echo htmlspecialchars($_SESSION['user']['firstName'] ?? $_SESSION['user']['username'] ?? 'User'); ?>
                    </p>
                </div>

                <div class="space-y-4">
                    <a href="index.php" 
                       class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-home mr-2"></i>
                        Go to Home
                    </a>
                    
                    <a href="search.php" 
                       class="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-search mr-2"></i>
                        Search Smoke Shops
                    </a>
                    
                    <a href="add.php" 
                       class="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        Add a Smoke Shop
                    </a>
                </div>

                <div class="pt-4">
                    <a href="logout.php" class="text-red-600 hover:text-red-700 text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Login form -->
        <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md w-full space-y-8">
                <!-- Header -->
                <div class="text-center">
                    <div class="mx-auto h-12 w-12 bg-blue-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-sign-in-alt text-white text-xl"></i>
                    </div>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
                        Sign in to your account
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Access your SmokeoutNYC dashboard and personalized features
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle text-red-400 mr-3 mt-0.5"></i>
                            <div class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <div class="flex">
                            <i class="fas fa-check-circle text-green-400 mr-3 mt-0.5"></i>
                            <div class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- OAuth2 Providers -->
                <div class="space-y-3">
                    <h3 class="text-center text-sm font-medium text-gray-700 mb-4">Sign in with</h3>
                    
                    <button onclick="loginWithProvider('google')" 
                            class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Continue with Google
                    </button>

                    <button onclick="loginWithProvider('facebook')" 
                            class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fab fa-facebook text-blue-600 text-xl mr-3"></i>
                        Continue with Facebook
                    </button>

                    <button onclick="loginWithProvider('microsoft')" 
                            class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fab fa-microsoft text-blue-500 text-xl mr-3"></i>
                        Continue with Microsoft
                    </button>

                    <button onclick="loginWithProvider('twitter')" 
                            class="oauth-btn w-full flex justify-center items-center px-4 py-3 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fab fa-twitter text-blue-400 text-xl mr-3"></i>
                        Continue with Twitter
                    </button>
                </div>

                <!-- Divider -->
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-50 text-gray-500">Or continue with email</span>
                    </div>
                </div>

                <!-- Login Form -->
                <form class="mt-8 space-y-6" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                Email Address
                            </label>
                            <input id="email" name="email" type="email" required 
                                   class="form-input mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter your email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                            <div class="relative">
                                <input id="password" name="password" type="password" required 
                                       class="form-input mt-1 block w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter your password">
                                <button type="button" onclick="togglePassword('password')" 
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="password-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="#" onclick="showForgotPassword()" class="font-medium text-blue-600 hover:text-blue-500">
                                Forgot your password?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button type="submit" name="login" 
                                class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
                            </span>
                            Sign In
                        </button>
                    </div>

                    <div class="text-center">
                        <span class="text-sm text-gray-600">
                            Don't have an account?
                            <a href="signup.php" class="font-medium text-blue-600 hover:text-blue-500">
                                Sign up here
                            </a>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Reset Password</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Enter your email address and we'll send you a link to reset your password.
                    </p>
                    <input type="email" id="resetEmail" 
                           class="mt-3 w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter your email">
                </div>
                <div class="items-center px-4 py-3">
                    <button onclick="sendPasswordReset()" 
                            class="px-4 py-2 bg-blue-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Send Reset Link
                    </button>
                    <button onclick="closeForgotPassword()" 
                            class="mt-3 px-4 py-2 bg-gray-300 text-gray-800 text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // OAuth2 login functions
        function loginWithProvider(provider) {
            const providers = {
                google: '<?php echo $API_BASE; ?>/auth/google',
                facebook: '<?php echo $API_BASE; ?>/auth/facebook',
                microsoft: '#', // Placeholder - would need Microsoft OAuth setup
                twitter: '#'    // Placeholder - would need Twitter OAuth setup
            };
            
            if (providers[provider] && providers[provider] !== '#') {
                window.location.href = providers[provider];
            } else {
                alert(`${provider.charAt(0).toUpperCase() + provider.slice(1)} authentication is not yet configured.`);
            }
        }

        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Forgot password modal
        function showForgotPassword() {
            document.getElementById('forgotPasswordModal').classList.remove('hidden');
        }

        function closeForgotPassword() {
            document.getElementById('forgotPasswordModal').classList.add('hidden');
        }

        function sendPasswordReset() {
            const email = document.getElementById('resetEmail').value;
            if (!email) {
                alert('Please enter your email address');
                return;
            }

            // Call API to send password reset
            fetch('<?php echo $API_BASE; ?>/auth/forgot-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.message) {
                    alert('Password reset link sent to your email!');
                    closeForgotPassword();
                } else {
                    alert('Error: ' + (data.error || 'Failed to send reset email'));
                }
            })
            .catch(error => {
                alert('Error: Failed to send reset email');
                console.error('Error:', error);
            });
        }

        // Auto-redirect success message
        <?php if ($success): ?>
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
