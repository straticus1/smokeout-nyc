<?php
// News page for SmokeoutNYC - Displays news articles from the API
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - SmokeoutNYC</title>
    <meta name="description" content="Latest news and updates about smoke shop closures and Operation Smokeout in New York City">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .news-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .news-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
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
                        <i class="fas fa-map-marker-alt mr-1"></i>Map
                    </a>
                    <a href="news.php" class="text-blue-600 font-medium">
                        <i class="fas fa-newspaper mr-1"></i>News
                    </a>
                    <a href="search.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-search mr-1"></i>Search
                    </a>
                    <a href="add.php" class="text-gray-700 hover:text-blue-600 transition-colors">
                        <i class="fas fa-plus mr-1"></i>Add Store
                    </a>
                    <a href="login.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-sign-in-alt mr-1"></i>Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">
                <i class="fas fa-newspaper mr-3"></i>
                Latest News
            </h1>
            <p class="text-xl md:text-2xl opacity-90 max-w-3xl mx-auto">
                Stay updated on Operation Smokeout, smoke shop closures, and NYC regulatory changes
            </p>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="searchInput" class="block text-sm font-medium text-gray-700 mb-2">
                        Search Articles
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="searchInput"
                            placeholder="Search news articles..." 
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div class="md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Sort By
                    </label>
                    <select id="sortSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                    </select>
                </div>
                <div class="md:w-32 flex items-end">
                    <button 
                        onclick="loadNews(1)" 
                        class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                    >
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Spinner -->
        <div id="loadingSpinner" class="flex justify-center items-center py-12">
            <div class="loading-spinner"></div>
        </div>

        <!-- News Articles Grid -->
        <div id="newsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- Articles will be loaded here -->
        </div>

        <!-- Pagination -->
        <div id="pagination" class="flex justify-center items-center space-x-2">
            <!-- Pagination will be loaded here -->
        </div>

        <!-- No Results Message -->
        <div id="noResults" class="text-center py-12 hidden">
            <i class="fas fa-newspaper text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">No articles found</h3>
            <p class="text-gray-500">Try adjusting your search terms or check back later for new content.</p>
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
                        Tracking smoke shop closures and Operation Smokeout enforcement in New York City.
                    </p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="index.php" class="text-gray-300 hover:text-white transition-colors">Interactive Map</a></li>
                        <li><a href="news.php" class="text-gray-300 hover:text-white transition-colors">Latest News</a></li>
                        <li><a href="search.php" class="text-gray-300 hover:text-white transition-colors">Search Stores</a></li>
                        <li><a href="add.php" class="text-gray-300 hover:text-white transition-colors">Add Store</a></li>
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

    <script>
        const API_BASE = 'http://localhost:3001/api';
        let currentPage = 1;
        let totalPages = 1;

        // Load news articles
        async function loadNews(page = 1) {
            try {
                showLoading(true);
                hideNoResults();

                const searchTerm = document.getElementById('searchInput').value;
                const sortOrder = document.getElementById('sortSelect').value;
                
                let url = `${API_BASE}/news?page=${page}&limit=12`;
                if (searchTerm) {
                    url += `&search=${encodeURIComponent(searchTerm)}`;
                }

                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error('Failed to fetch news');
                }

                const data = await response.json();
                
                if (data.articles && data.articles.length > 0) {
                    displayNews(data.articles);
                    updatePagination(data.pagination);
                } else {
                    showNoResults();
                }
                
                currentPage = page;
                totalPages = data.pagination ? data.pagination.pages : 1;
                
            } catch (error) {
                console.error('Error loading news:', error);
                showError('Failed to load news articles. Please try again.');
            } finally {
                showLoading(false);
            }
        }

        // Display news articles
        function displayNews(articles) {
            const newsGrid = document.getElementById('newsGrid');
            newsGrid.innerHTML = '';

            articles.forEach(article => {
                const articleCard = createArticleCard(article);
                newsGrid.appendChild(articleCard);
            });
        }

        // Create article card
        function createArticleCard(article) {
            const card = document.createElement('div');
            card.className = 'news-card bg-white rounded-lg shadow-md overflow-hidden cursor-pointer';
            card.onclick = () => openArticle(article.slug);

            const publishedDate = new Date(article.publishedAt || article.createdAt).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            const authorName = article.author.username || 
                             `${article.author.firstName || ''} ${article.author.lastName || ''}`.trim() || 
                             'Anonymous';

            card.innerHTML = `
                ${article.featuredImage ? `
                    <div class="h-48 bg-cover bg-center" style="background-image: url('${article.featuredImage}')"></div>
                ` : `
                    <div class="h-48 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                        <i class="fas fa-newspaper text-white text-4xl"></i>
                    </div>
                `}
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-2 line-clamp-2">
                        ${escapeHtml(article.title)}
                    </h3>
                    ${article.excerpt ? `
                        <p class="text-gray-600 mb-4 line-clamp-3">
                            ${escapeHtml(article.excerpt)}
                        </p>
                    ` : ''}
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span>
                            <i class="fas fa-user mr-1"></i>
                            ${escapeHtml(authorName)}
                        </span>
                        <span>
                            <i class="fas fa-calendar mr-1"></i>
                            ${publishedDate}
                        </span>
                    </div>
                </div>
            `;

            return card;
        }

        // Open article in new tab/window
        function openArticle(slug) {
            window.open(`article.php?slug=${slug}`, '_blank');
        }

        // Update pagination
        function updatePagination(pagination) {
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

            // Page numbers
            const startPage = Math.max(1, page - 2);
            const endPage = Math.min(pages, page + 2);

            if (startPage > 1) {
                paginationDiv.appendChild(createPaginationButton(1, '1'));
                if (startPage > 2) {
                    paginationDiv.appendChild(createPaginationEllipsis());
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                const btn = createPaginationButton(i, i.toString(), i === page);
                paginationDiv.appendChild(btn);
            }

            if (endPage < pages) {
                if (endPage < pages - 1) {
                    paginationDiv.appendChild(createPaginationEllipsis());
                }
                paginationDiv.appendChild(createPaginationButton(pages, pages.toString()));
            }

            // Next button
            if (page < pages) {
                const nextBtn = createPaginationButton(page + 1, '<i class="fas fa-chevron-right"></i>');
                paginationDiv.appendChild(nextBtn);
            }
        }

        // Create pagination button
        function createPaginationButton(pageNum, text, isActive = false) {
            const button = document.createElement('button');
            button.className = `px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                isActive 
                    ? 'bg-blue-600 text-white' 
                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
            }`;
            button.innerHTML = text;
            button.onclick = () => loadNews(pageNum);
            return button;
        }

        // Create pagination ellipsis
        function createPaginationEllipsis() {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-3 py-2 text-gray-500';
            ellipsis.textContent = '...';
            return ellipsis;
        }

        // Show/hide loading spinner
        function showLoading(show) {
            const spinner = document.getElementById('loadingSpinner');
            const newsGrid = document.getElementById('newsGrid');
            
            if (show) {
                spinner.classList.remove('hidden');
                newsGrid.classList.add('hidden');
            } else {
                spinner.classList.add('hidden');
                newsGrid.classList.remove('hidden');
            }
        }

        // Show no results message
        function showNoResults() {
            document.getElementById('noResults').classList.remove('hidden');
            document.getElementById('newsGrid').classList.add('hidden');
        }

        // Hide no results message
        function hideNoResults() {
            document.getElementById('noResults').classList.add('hidden');
        }

        // Show error message
        function showError(message) {
            // Create a toast notification or alert
            alert(message);
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Search on Enter key
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadNews(1);
            }
        });

        // Load initial news
        document.addEventListener('DOMContentLoaded', function() {
            loadNews(1);
        });
    </script>
</body>
</html>
