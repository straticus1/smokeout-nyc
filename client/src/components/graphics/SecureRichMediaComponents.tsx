import React, { useState, useRef, useEffect, useCallback, useMemo } from 'react';
import { motion, AnimatePresence, useAnimation, ReducedMotion } from 'framer-motion';
import DOMPurify from 'dompurify';
import { 
  PlayIcon, 
  PauseIcon, 
  SpeakerWaveIcon, 
  SpeakerXMarkIcon,
  ArrowsPointingOutIcon,
  XMarkIcon,
  ShareIcon,
  HeartIcon,
  ChatBubbleLeftIcon,
  EyeIcon,
  PhotoIcon,
  VideoCameraIcon,
  DocumentTextIcon
} from '@heroicons/react/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/react/24/solid';

// Security utilities
const sanitizeInput = (input: string): string => {
  return input.replace(/[<>\"'&]/g, (match) => {
    const entities: Record<string, string> = {
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#x27;',
      '&': '&amp;'
    };
    return entities[match] || match;
  }).substring(0, 500); // Limit length
};

const isValidUrl = (url: string): boolean => {
  try {
    const parsed = new URL(url);
    return ['http:', 'https:'].includes(parsed.protocol);
  } catch {
    return false;
  }
};

// Error Boundary Component
interface ErrorBoundaryState {
  hasError: boolean;
  error?: Error;
}

class GraphicsErrorBoundary extends React.Component<
  React.PropsWithChildren<{}>,
  ErrorBoundaryState
> {
  constructor(props: React.PropsWithChildren<{}>) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Graphics component error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div 
          className="flex items-center justify-center p-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300"
          role="alert"
          aria-live="polite"
        >
          <div className="text-center">
            <div className="text-gray-400 mb-2">
              <PhotoIcon className="w-12 h-12 mx-auto" />
            </div>
            <h3 className="text-lg font-medium text-gray-900 mb-2">
              Content Unavailable
            </h3>
            <p className="text-gray-600 text-sm">
              Unable to load media content. Please try refreshing the page.
            </p>
            <button
              onClick={() => this.setState({ hasError: false })}
              className="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            >
              Retry
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

// Enhanced Image Gallery with Security and Accessibility
interface GalleryImage {
  id: string;
  url: string;
  title: string;
  description: string;
  tags: string[];
  author: string;
  date: string;
  alt?: string; // Proper alt text
}

interface ImageGalleryProps {
  images: GalleryImage[];
  columns?: number;
  maxImages?: number;
}

export const SecureImageGallery: React.FC<ImageGalleryProps> = ({ 
  images, 
  columns = 3,
  maxImages = 50
}) => {
  const [selectedImage, setSelectedImage] = useState<GalleryImage | null>(null);
  const [filter, setFilter] = useState<string>('all');
  const [liked, setLiked] = useState<Set<string>>(new Set());
  const [imageErrors, setImageErrors] = useState<Set<string>>(new Set());
  const [loadedImages, setLoadedImages] = useState<Set<string>>(new Set());

  // Sanitize and validate images
  const validatedImages = useMemo(() => {
    return images
      .slice(0, maxImages) // Limit number of images
      .filter(img => img.id && img.url && img.title)
      .map(img => ({
        ...img,
        title: sanitizeInput(img.title),
        description: sanitizeInput(img.description),
        author: sanitizeInput(img.author),
        tags: img.tags.map(tag => sanitizeInput(tag)).slice(0, 10), // Limit tags
        url: isValidUrl(img.url) ? img.url : '/placeholder-image.jpg'
      }));
  }, [images, maxImages]);

  // Get unique tags safely
  const allTags = useMemo(() => {
    const tagSet = new Set<string>();
    validatedImages.forEach(img => {
      img.tags.forEach(tag => {
        if (tag.length > 0) tagSet.add(tag);
      });
    });
    return Array.from(tagSet).slice(0, 20); // Limit displayed tags
  }, [validatedImages]);
  
  const filteredImages = useMemo(() => {
    return validatedImages.filter(img => 
      filter === 'all' || img.tags.includes(filter)
    );
  }, [validatedImages, filter]);

  const toggleLike = useCallback((imageId: string) => {
    setLiked(prev => {
      const newLiked = new Set(prev);
      if (newLiked.has(imageId)) {
        newLiked.delete(imageId);
      } else {
        newLiked.add(imageId);
      }
      return newLiked;
    });
  }, []);

  const handleImageLoad = useCallback((imageId: string) => {
    setLoadedImages(prev => new Set([...prev, imageId]));
  }, []);

  const handleImageError = useCallback((imageId: string) => {
    setImageErrors(prev => new Set([...prev, imageId]));
  }, []);

  // Keyboard navigation
  const handleKeyDown = useCallback((event: React.KeyboardEvent, action: () => void) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      action();
    }
  }, []);

  // Close modal with Escape key
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && selectedImage) {
        setSelectedImage(null);
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [selectedImage]);

  // Focus management for modal
  const modalRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (selectedImage && modalRef.current) {
      modalRef.current.focus();
    }
  }, [selectedImage]);

  return (
    <GraphicsErrorBoundary>
      <div className="bg-white rounded-2xl shadow-lg p-6">
        <div className="flex justify-between items-center mb-6">
          <h3 
            className="text-xl font-semibold text-gray-900"
            id="gallery-title"
          >
            Cannabis Media Gallery ({filteredImages.length} items)
          </h3>
          
          {/* Filter Tags with proper accessibility */}
          <div 
            className="flex flex-wrap gap-2"
            role="tablist"
            aria-labelledby="gallery-title"
          >
            <button
              role="tab"
              aria-selected={filter === 'all'}
              aria-controls="image-grid"
              onClick={() => setFilter('all')}
              className={`px-3 py-1 rounded-full text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 ${
                filter === 'all'
                  ? 'bg-green-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              All
            </button>
            {allTags.map((tag) => (
              <button
                key={tag}
                role="tab"
                aria-selected={filter === tag}
                aria-controls="image-grid"
                onClick={() => setFilter(tag)}
                className={`px-3 py-1 rounded-full text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 ${
                  filter === tag
                    ? 'bg-green-600 text-white'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                }`}
              >
                {tag}
              </button>
            ))}
          </div>
        </div>

        {/* Image Grid with proper accessibility */}
        <div 
          id="image-grid"
          role="tabpanel"
          aria-labelledby="gallery-title"
          className={`grid grid-cols-1 md:grid-cols-${Math.min(columns, 3)} lg:grid-cols-${columns} gap-4`}
        >
          <AnimatePresence mode="popLayout">
            {filteredImages.map((image, index) => (
              <motion.div
                key={image.id}
                layout
                initial={{ opacity: 0, scale: 0.8 }}
                animate={{ opacity: 1, scale: 1 }}
                exit={{ opacity: 0, scale: 0.8 }}
                transition={{ 
                  delay: index * 0.05,
                  type: 'spring',
                  stiffness: 300,
                  damping: 30
                }}
                className="relative group cursor-pointer overflow-hidden rounded-lg bg-gray-100 focus-within:ring-2 focus-within:ring-green-500 focus-within:ring-offset-2"
                tabIndex={0}
                role="button"
                aria-label={`View image: ${image.title}`}
                onClick={() => setSelectedImage(image)}
                onKeyDown={(e) => handleKeyDown(e, () => setSelectedImage(image))}
              >
                {!imageErrors.has(image.id) ? (
                  <img
                    src={image.url}
                    alt={image.alt || `${image.title} - ${image.description}`}
                    className={`w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300 ${
                      loadedImages.has(image.id) ? 'opacity-100' : 'opacity-0'
                    }`}
                    onLoad={() => handleImageLoad(image.id)}
                    onError={() => handleImageError(image.id)}
                    loading="lazy"
                  />
                ) : (
                  <div className="w-full h-64 flex items-center justify-center bg-gray-200">
                    <div className="text-center text-gray-400">
                      <PhotoIcon className="w-12 h-12 mx-auto mb-2" />
                      <p className="text-sm">Image unavailable</p>
                    </div>
                  </div>
                )}
                
                {/* Loading placeholder */}
                {!loadedImages.has(image.id) && !imageErrors.has(image.id) && (
                  <div className="absolute inset-0 bg-gray-200 animate-pulse" />
                )}
                
                {/* Overlay with accessibility */}
                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/40 group-focus:bg-black/40 transition-all duration-300 flex items-end">
                  <div className="p-4 text-white transform translate-y-full group-hover:translate-y-0 group-focus:translate-y-0 transition-transform duration-300">
                    <h4 className="font-semibold mb-1">{image.title}</h4>
                    <p className="text-sm opacity-90">{image.description}</p>
                  </div>
                </div>

                {/* Like Button with accessibility */}
                <button
                  aria-label={liked.has(image.id) ? 'Unlike image' : 'Like image'}
                  className="absolute top-3 right-3 p-2 rounded-full bg-white/80 backdrop-blur-sm hover:bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors"
                  onClick={(e) => {
                    e.stopPropagation();
                    toggleLike(image.id);
                  }}
                >
                  {liked.has(image.id) ? (
                    <HeartSolidIcon className="w-5 h-5 text-red-500" />
                  ) : (
                    <HeartIcon className="w-5 h-5 text-gray-600" />
                  )}
                </button>
              </motion.div>
            ))}
          </AnimatePresence>
        </div>

        {filteredImages.length === 0 && (
          <div className="text-center py-12 text-gray-500">
            <PhotoIcon className="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>No images found for the selected filter.</p>
          </div>
        )}

        {/* Accessible Fullscreen Modal */}
        <AnimatePresence>
          {selectedImage && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4"
              role="dialog"
              aria-modal="true"
              aria-labelledby="modal-title"
              aria-describedby="modal-description"
              onClick={() => setSelectedImage(null)}
            >
              <motion.div
                ref={modalRef}
                initial={{ scale: 0.8, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                exit={{ scale: 0.8, opacity: 0 }}
                className="relative max-w-4xl w-full bg-white rounded-lg overflow-hidden focus:outline-none"
                tabIndex={-1}
                onClick={(e) => e.stopPropagation()}
              >
                <img
                  src={selectedImage.url}
                  alt={selectedImage.alt || `${selectedImage.title} - ${selectedImage.description}`}
                  className="w-full h-96 object-cover"
                />
                
                <div className="p-6">
                  <h3 id="modal-title" className="text-2xl font-bold mb-2">
                    {selectedImage.title}
                  </h3>
                  <p id="modal-description" className="text-gray-600 mb-4">
                    {selectedImage.description}
                  </p>
                  
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4 text-sm text-gray-500">
                      <span>By {selectedImage.author}</span>
                      <span>{selectedImage.date}</span>
                    </div>
                    
                    <button 
                      className="flex items-center space-x-1 text-gray-600 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded px-2 py-1"
                      aria-label="Share this image"
                    >
                      <ShareIcon className="w-5 h-5" />
                      <span>Share</span>
                    </button>
                  </div>
                </div>
                
                <button
                  onClick={() => setSelectedImage(null)}
                  aria-label="Close image viewer"
                  className="absolute top-4 right-4 p-2 bg-white/80 backdrop-blur-sm rounded-full hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                  <XMarkIcon className="w-6 h-6" />
                </button>
              </motion.div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </GraphicsErrorBoundary>
  );
};

// Secure News Article Component
interface NewsArticle {
  id: string;
  title: string;
  excerpt: string;
  content: string;
  author: string;
  publishedAt: string;
  featuredImage: string;
  category: string;
  tags: string[];
  readTime: number;
  views: number;
  likes: number;
}

export const SecureNewsArticleCard: React.FC<{ article: NewsArticle }> = ({ 
  article 
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const [isLiked, setIsLiked] = useState(false);
  const [imageError, setImageError] = useState(false);

  // Sanitize article data
  const sanitizedArticle = useMemo(() => ({
    ...article,
    title: sanitizeInput(article.title),
    excerpt: sanitizeInput(article.excerpt),
    author: sanitizeInput(article.author),
    category: sanitizeInput(article.category),
    tags: article.tags.map(tag => sanitizeInput(tag)).slice(0, 10),
    // Sanitize HTML content with DOMPurify
    content: DOMPurify.sanitize(article.content, {
      ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li'],
      ALLOWED_ATTR: []
    }),
    featuredImage: isValidUrl(article.featuredImage) ? article.featuredImage : '/placeholder-news.jpg'
  }), [article]);

  const handleImageError = useCallback(() => {
    setImageError(true);
  }, []);

  const toggleExpanded = useCallback(() => {
    setIsExpanded(prev => !prev);
  }, []);

  const toggleLike = useCallback(() => {
    setIsLiked(prev => !prev);
  }, []);

  return (
    <GraphicsErrorBoundary>
      <motion.article
        layout
        className="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2"
        role="article"
        aria-labelledby={`article-title-${article.id}`}
      >
        {/* Featured Image */}
        <div className="relative h-48 overflow-hidden bg-gray-200">
          {!imageError ? (
            <img
              src={sanitizedArticle.featuredImage}
              alt={`Featured image for: ${sanitizedArticle.title}`}
              className="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
              onError={handleImageError}
              loading="lazy"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-400">
              <div className="text-center">
                <DocumentTextIcon className="w-12 h-12 mx-auto mb-2" />
                <p className="text-sm">Image unavailable</p>
              </div>
            </div>
          )}
          
          {/* Category Badge */}
          <div className="absolute top-4 left-4">
            <span className="px-3 py-1 bg-green-600 text-white text-sm font-medium rounded-full">
              {sanitizedArticle.category}
            </span>
          </div>
          
          {/* Reading Time */}
          <div className="absolute bottom-4 right-4">
            <span className="px-2 py-1 bg-black/50 text-white text-xs rounded backdrop-blur-sm">
              {article.readTime} min read
            </span>
          </div>
        </div>

        {/* Content */}
        <div className="p-6">
          <h2 
            id={`article-title-${article.id}`}
            className="text-xl font-bold text-gray-900 mb-2 line-clamp-2"
          >
            {sanitizedArticle.title}
          </h2>
          
          <p className="text-gray-600 mb-4 line-clamp-3">
            {sanitizedArticle.excerpt}
          </p>

          {/* Article Meta */}
          <div className="flex items-center justify-between text-sm text-gray-500 mb-4">
            <div className="flex items-center space-x-3">
              <span>By {sanitizedArticle.author}</span>
              <span aria-hidden="true">•</span>
              <time dateTime={article.publishedAt}>
                {new Date(article.publishedAt).toLocaleDateString()}
              </time>
              <span aria-hidden="true">•</span>
              <div className="flex items-center space-x-1">
                <EyeIcon className="w-4 h-4" aria-hidden="true" />
                <span aria-label={`${article.views} views`}>
                  {article.views.toLocaleString()}
                </span>
              </div>
            </div>
          </div>

          {/* Tags */}
          <div className="flex flex-wrap gap-2 mb-4" role="list" aria-label="Article tags">
            {sanitizedArticle.tags.slice(0, 3).map((tag) => (
              <span
                key={tag}
                role="listitem"
                className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full"
              >
                #{tag}
              </span>
            ))}
          </div>

          {/* Expanded Content */}
          <AnimatePresence>
            {isExpanded && (
              <motion.div
                initial={{ opacity: 0, height: 0 }}
                animate={{ opacity: 1, height: 'auto' }}
                exit={{ opacity: 0, height: 0 }}
                className="prose prose-sm max-w-none mb-4"
              >
                <div 
                  dangerouslySetInnerHTML={{ __html: sanitizedArticle.content }}
                  aria-label="Full article content"
                />
              </motion.div>
            )}
          </AnimatePresence>

          {/* Actions */}
          <div className="flex items-center justify-between pt-4 border-t border-gray-100">
            <button
              onClick={toggleExpanded}
              className="flex items-center space-x-1 text-green-600 hover:text-green-700 font-medium focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 rounded px-2 py-1"
              aria-expanded={isExpanded}
              aria-controls={`article-content-${article.id}`}
            >
              <DocumentTextIcon className="w-4 h-4" aria-hidden="true" />
              <span>{isExpanded ? 'Read Less' : 'Read More'}</span>
            </button>
            
            <div className="flex items-center space-x-3">
              <button
                onClick={toggleLike}
                aria-label={isLiked ? 'Unlike article' : 'Like article'}
                className={`flex items-center space-x-1 px-3 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                  isLiked 
                    ? 'bg-red-100 text-red-600 focus:ring-red-500' 
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500'
                }`}
              >
                {isLiked ? (
                  <HeartSolidIcon className="w-4 h-4" aria-hidden="true" />
                ) : (
                  <HeartIcon className="w-4 h-4" aria-hidden="true" />
                )}
                <span>{(article.likes + (isLiked ? 1 : 0)).toLocaleString()}</span>
              </button>
              
              <button 
                className="flex items-center space-x-1 px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                aria-label="Comment on article"
              >
                <ChatBubbleLeftIcon className="w-4 h-4" aria-hidden="true" />
                <span>Comment</span>
              </button>
            </div>
          </div>
        </div>
      </motion.article>
    </GraphicsErrorBoundary>
  );
};