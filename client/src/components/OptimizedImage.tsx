import React, { useState, useEffect, useRef, useCallback } from 'react';
import { usePerformance } from '../hooks/usePerformance';

interface OptimizedImageProps {
  src: string;
  alt: string;
  width?: number;
  height?: number;
  className?: string;
  placeholder?: string;
  quality?: 'low' | 'medium' | 'high';
  lazy?: boolean;
  webpFallback?: boolean;
  onLoad?: () => void;
  onError?: () => void;
}

const OptimizedImage: React.FC<OptimizedImageProps> = ({
  src,
  alt,
  width,
  height,
  className = '',
  placeholder,
  quality = 'medium',
  lazy = true,
  webpFallback = true,
  onLoad,
  onError
}) => {
  const [isLoaded, setIsLoaded] = useState(false);
  const [isInView, setIsInView] = useState(!lazy);
  const [imageSrc, setImageSrc] = useState<string>('');
  const [hasError, setHasError] = useState(false);
  
  const imgRef = useRef<HTMLImageElement>(null);
  const observerRef = useRef<IntersectionObserver>();
  const { settings } = usePerformance();

  // Generate optimized image URL based on performance settings
  const getOptimizedImageUrl = useCallback((originalSrc: string) => {
    if (!originalSrc) return '';

    // If it's already a data URL or external URL, return as is
    if (originalSrc.startsWith('data:') || originalSrc.startsWith('http')) {
      return originalSrc;
    }

    const url = new URL(originalSrc, window.location.origin);
    const params = new URLSearchParams();

    // Add quality parameter based on device performance and settings
    const qualityMap = {
      low: 60,
      medium: 80,
      high: 95
    };

    let targetQuality = qualityMap[quality];

    // Reduce quality on low-end devices or when auto-optimizing
    if (settings.autoOptimize || settings.animationQuality === 'low') {
      targetQuality = Math.min(targetQuality, 70);
    }

    params.set('quality', targetQuality.toString());

    // Add dimensions if specified
    if (width) params.set('w', width.toString());
    if (height) params.set('h', height.toString());

    // Add format preference
    if (webpFallback) {
      params.set('format', 'webp');
    }

    return `${url.pathname}?${params.toString()}`;
  }, [quality, width, height, webpFallback, settings]);

  // Set up intersection observer for lazy loading
  useEffect(() => {
    if (!lazy || isInView) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setIsInView(true);
            observer.disconnect();
          }
        });
      },
      { rootMargin: '50px' }
    );

    observerRef.current = observer;

    if (imgRef.current) {
      observer.observe(imgRef.current);
    }

    return () => {
      observer.disconnect();
    };
  }, [lazy, isInView]);

  // Load image when in view
  useEffect(() => {
    if (!isInView || !src) return;

    const optimizedSrc = getOptimizedImageUrl(src);
    
    // Preload image
    const img = new Image();
    
    img.onload = () => {
      setImageSrc(optimizedSrc);
      setIsLoaded(true);
      setHasError(false);
      onLoad?.();
    };

    img.onerror = () => {
      // Try fallback without WebP
      if (webpFallback && optimizedSrc.includes('format=webp')) {
        const fallbackSrc = getOptimizedImageUrl(src).replace('format=webp', 'format=jpeg');
        
        const fallbackImg = new Image();
        fallbackImg.onload = () => {
          setImageSrc(fallbackSrc);
          setIsLoaded(true);
          setHasError(false);
          onLoad?.();
        };
        
        fallbackImg.onerror = () => {
          setHasError(true);
          onError?.();
        };
        
        fallbackImg.src = fallbackSrc;
      } else {
        setHasError(true);
        onError?.();
      }
    };

    img.src = optimizedSrc;
  }, [isInView, src, getOptimizedImageUrl, webpFallback, onLoad, onError]);

  // Render placeholder or loading state
  if (!isInView || (!isLoaded && !hasError)) {
    return (
      <div
        ref={imgRef}
        className={`${className} bg-gray-200 animate-pulse flex items-center justify-center`}
        style={{ width, height }}
      >
        {placeholder ? (
          <img
            src={placeholder}
            alt={alt}
            className="opacity-50 max-w-full max-h-full object-contain"
          />
        ) : (
          <div className="text-gray-400 text-center">
            <svg
              className="w-8 h-8 mx-auto mb-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
              />
            </svg>
            <span className="text-xs">Loading...</span>
          </div>
        )}
      </div>
    );
  }

  // Render error state
  if (hasError) {
    return (
      <div
        className={`${className} bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-500`}
        style={{ width, height }}
      >
        <div className="text-center">
          <svg
            className="w-8 h-8 mx-auto mb-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <span className="text-xs">Failed to load</span>
        </div>
      </div>
    );
  }

  // Render loaded image
  return (
    <img
      ref={imgRef}
      src={imageSrc}
      alt={alt}
      width={width}
      height={height}
      className={`${className} ${isLoaded ? 'opacity-100' : 'opacity-0'} transition-opacity duration-300`}
      style={{
        width,
        height,
        objectFit: 'cover',
        // Add hardware acceleration for smooth animations
        transform: 'translateZ(0)',
        willChange: 'transform',
      }}
      loading={lazy ? 'lazy' : 'eager'}
      decoding="async"
    />
  );
};

// Progressive image component that loads low quality first, then high quality
interface ProgressiveImageProps extends Omit<OptimizedImageProps, 'quality'> {
  lowQualitySrc?: string;
}

export const ProgressiveImage: React.FC<ProgressiveImageProps> = ({
  src,
  lowQualitySrc,
  ...props
}) => {
  const [showHighQuality, setShowHighQuality] = useState(false);
  const { settings } = usePerformance();

  // Skip progressive loading on low-end devices to save bandwidth
  const shouldUseProgressive = settings.textureQuality === 'high' && !settings.autoOptimize;

  useEffect(() => {
    if (!shouldUseProgressive || !lowQualitySrc) {
      setShowHighQuality(true);
      return;
    }

    // Preload high quality image
    const img = new Image();
    img.onload = () => {
      setShowHighQuality(true);
    };
    img.src = src;
  }, [src, lowQualitySrc, shouldUseProgressive]);

  const effectiveSrc = shouldUseProgressive && !showHighQuality && lowQualitySrc ? lowQualitySrc : src;

  return (
    <OptimizedImage
      {...props}
      src={effectiveSrc}
      quality={showHighQuality ? 'high' : 'low'}
    />
  );
};

// Image gallery component optimized for mobile
interface ImageGalleryProps {
  images: Array<{
    src: string;
    alt: string;
    thumbnail?: string;
  }>;
  currentIndex: number;
  onIndexChange: (index: number) => void;
  className?: string;
}

export const MobileImageGallery: React.FC<ImageGalleryProps> = ({
  images,
  currentIndex,
  onIndexChange,
  className = ''
}) => {
  const [startX, setStartX] = useState(0);
  const [currentX, setCurrentX] = useState(0);
  const [isDragging, setIsDragging] = useState(false);
  
  const galleryRef = useRef<HTMLDivElement>(null);

  const handleTouchStart = (e: React.TouchEvent) => {
    setStartX(e.touches[0].clientX);
    setIsDragging(true);
  };

  const handleTouchMove = (e: React.TouchEvent) => {
    if (!isDragging) return;
    setCurrentX(e.touches[0].clientX);
  };

  const handleTouchEnd = () => {
    if (!isDragging) return;
    
    const diffX = startX - currentX;
    const threshold = 50; // Minimum swipe distance
    
    if (Math.abs(diffX) > threshold) {
      if (diffX > 0 && currentIndex < images.length - 1) {
        onIndexChange(currentIndex + 1);
      } else if (diffX < 0 && currentIndex > 0) {
        onIndexChange(currentIndex - 1);
      }
    }
    
    setIsDragging(false);
    setCurrentX(0);
    setStartX(0);
  };

  const translateX = isDragging ? currentX - startX : 0;

  return (
    <div className={`${className} relative overflow-hidden`}>
      <div
        ref={galleryRef}
        className="flex transition-transform duration-300 ease-out"
        style={{
          transform: `translateX(calc(-${currentIndex * 100}% + ${translateX}px))`,
        }}
        onTouchStart={handleTouchStart}
        onTouchMove={handleTouchMove}
        onTouchEnd={handleTouchEnd}
      >
        {images.map((image, index) => (
          <div key={index} className="w-full flex-shrink-0">
            <OptimizedImage
              src={image.src}
              alt={image.alt}
              className="w-full h-full object-cover"
              lazy={Math.abs(index - currentIndex) > 1} // Only lazy load distant images
            />
          </div>
        ))}
      </div>
      
      {/* Pagination dots */}
      <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2">
        {images.map((_, index) => (
          <button
            key={index}
            onClick={() => onIndexChange(index)}
            className={`w-2 h-2 rounded-full transition-colors ${
              index === currentIndex ? 'bg-white' : 'bg-white bg-opacity-50'
            }`}
          />
        ))}
      </div>
    </div>
  );
};

export default OptimizedImage;