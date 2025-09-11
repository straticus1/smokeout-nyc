import { useState, useEffect, useCallback, useRef } from 'react';
import { performanceMonitor, PerformanceMetrics, debounce, throttle } from '../utils/performance';

export interface PerformanceSettings {
  animationQuality: 'low' | 'medium' | 'high';
  particleCount: number;
  shadowQuality: 'low' | 'medium' | 'high';
  textureQuality: 'low' | 'medium' | 'high';
  refreshRate: number;
  enableEffects: boolean;
  enableAnimations: boolean;
  autoOptimize: boolean;
}

/**
 * Hook for monitoring and optimizing performance
 */
export function usePerformance() {
  const [metrics, setMetrics] = useState<PerformanceMetrics>(performanceMonitor.getMetrics());
  const [settings, setSettings] = useState<PerformanceSettings>(performanceMonitor.getRecommendedSettings());
  const [isOptimizing, setIsOptimizing] = useState(false);

  useEffect(() => {
    const updateMetrics = () => {
      const newMetrics = performanceMonitor.getMetrics();
      setMetrics(newMetrics);

      // Auto-adjust settings if enabled
      if (settings.autoOptimize) {
        const recommendedSettings = performanceMonitor.getRecommendedSettings();
        setSettings(prev => ({ ...prev, ...recommendedSettings }));
      }
    };

    const interval = setInterval(updateMetrics, 2000);
    return () => clearInterval(interval);
  }, [settings.autoOptimize]);

  const updateSettings = useCallback((newSettings: Partial<PerformanceSettings>) => {
    setSettings(prev => ({ ...prev, ...newSettings }));
  }, []);

  const optimizePerformance = useCallback(async () => {
    setIsOptimizing(true);
    
    try {
      // Force garbage collection if available
      if ('gc' in window && typeof (window as any).gc === 'function') {
        (window as any).gc();
      }

      // Test network latency
      await performanceMonitor.testNetworkLatency();

      // Apply optimal settings
      const recommendedSettings = performanceMonitor.getRecommendedSettings();
      setSettings(recommendedSettings);

      console.log('Performance optimization complete', recommendedSettings);
    } catch (error) {
      console.error('Performance optimization failed:', error);
    } finally {
      setIsOptimizing(false);
    }
  }, []);

  return {
    metrics,
    settings,
    updateSettings,
    optimizePerformance,
    isOptimizing
  };
}

/**
 * Hook for measuring component render performance
 */
export function useRenderPerformance(componentName: string) {
  const renderTimeRef = useRef<number>(0);

  const measureRender = useCallback((renderFn: () => void) => {
    renderTimeRef.current = performanceMonitor.measureRenderTime(componentName, renderFn);
  }, [componentName]);

  return {
    measureRender,
    lastRenderTime: renderTimeRef.current
  };
}

/**
 * Hook for debounced functions
 */
export function useDebounce<T extends (...args: any[]) => any>(
  func: T,
  delay: number,
  deps: React.DependencyList = []
): T {
  return useCallback(
    debounce(func, delay),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    deps
  );
}

/**
 * Hook for throttled functions
 */
export function useThrottle<T extends (...args: any[]) => any>(
  func: T,
  limit: number,
  deps: React.DependencyList = []
): T {
  return useCallback(
    throttle(func, limit),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    deps
  );
}

/**
 * Hook for virtual scrolling in large lists
 */
export function useVirtualScrolling(
  itemHeight: number,
  totalItems: number,
  containerHeight: number
) {
  const [scrollTop, setScrollTop] = useState(0);
  
  const visibleCount = Math.ceil(containerHeight / itemHeight) + 2; // Buffer
  const startIndex = Math.floor(scrollTop / itemHeight);
  const endIndex = Math.min(startIndex + visibleCount, totalItems);
  
  const handleScroll = useThrottle((event: React.UIEvent<HTMLDivElement>) => {
    setScrollTop(event.currentTarget.scrollTop);
  }, 16); // ~60fps

  const totalHeight = totalItems * itemHeight;
  const offsetY = startIndex * itemHeight;

  return {
    startIndex,
    endIndex,
    totalHeight,
    offsetY,
    handleScroll
  };
}

/**
 * Hook for responsive design based on device capabilities
 */
export function useResponsiveLayout() {
  const [layout, setLayout] = useState<'mobile' | 'tablet' | 'desktop'>('desktop');
  const [isTouchDevice, setIsTouchDevice] = useState(false);

  useEffect(() => {
    const checkLayout = () => {
      const width = window.innerWidth;
      const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
      const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

      setIsTouchDevice(hasTouch);

      if (isMobile || width < 768) {
        setLayout('mobile');
      } else if (width < 1024) {
        setLayout('tablet');
      } else {
        setLayout('desktop');
      }
    };

    checkLayout();
    window.addEventListener('resize', debounce(checkLayout, 250));

    return () => {
      window.removeEventListener('resize', checkLayout);
    };
  }, []);

  return { layout, isTouchDevice };
}

/**
 * Hook for optimized animations based on device performance
 */
export function useOptimizedAnimations() {
  const { settings } = usePerformance();
  const [shouldAnimate, setShouldAnimate] = useState(true);

  useEffect(() => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    setShouldAnimate(settings.enableAnimations && !prefersReducedMotion);
  }, [settings.enableAnimations]);

  const getAnimationProps = useCallback((duration = 300) => {
    if (!shouldAnimate) {
      return { duration: 0, animate: false };
    }

    const adjustedDuration = settings.animationQuality === 'low' ? duration / 2 : 
                            settings.animationQuality === 'high' ? duration : 
                            duration * 0.75;

    return {
      duration: adjustedDuration,
      animate: true,
      transition: {
        duration: adjustedDuration / 1000,
        ease: settings.animationQuality === 'low' ? 'linear' : 'easeInOut'
      }
    };
  }, [shouldAnimate, settings.animationQuality]);

  return {
    shouldAnimate,
    getAnimationProps,
    animationQuality: settings.animationQuality
  };
}

/**
 * Hook for network-aware data fetching
 */
export function useNetworkOptimizedFetch() {
  const [networkStatus, setNetworkStatus] = useState<'online' | 'offline' | 'slow'>('online');
  const { metrics } = usePerformance();

  useEffect(() => {
    const updateNetworkStatus = () => {
      if (!navigator.onLine) {
        setNetworkStatus('offline');
      } else if (metrics.networkLatency > 1000) {
        setNetworkStatus('slow');
      } else {
        setNetworkStatus('online');
      }
    };

    updateNetworkStatus();
    
    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);

    return () => {
      window.removeEventListener('online', updateNetworkStatus);
      window.removeEventListener('offline', updateNetworkStatus);
    };
  }, [metrics.networkLatency]);

  const fetchWithRetry = useCallback(async (
    url: string, 
    options: RequestInit = {}, 
    maxRetries = 3
  ) => {
    let retries = 0;
    
    while (retries <= maxRetries) {
      try {
        // Adjust timeout based on network status
        const timeout = networkStatus === 'slow' ? 10000 : 5000;
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        const response = await fetch(url, {
          ...options,
          signal: controller.signal
        });

        clearTimeout(timeoutId);

        if (response.ok) {
          return response;
        } else if (response.status >= 500 && retries < maxRetries) {
          retries++;
          await new Promise(resolve => setTimeout(resolve, Math.pow(2, retries) * 1000));
        } else {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
      } catch (error) {
        if (retries === maxRetries) {
          throw error;
        }
        retries++;
        await new Promise(resolve => setTimeout(resolve, Math.pow(2, retries) * 1000));
      }
    }
  }, [networkStatus]);

  return {
    networkStatus,
    fetchWithRetry
  };
}

/**
 * Hook for memory-efficient data management
 */
export function useMemoryOptimizedData<T>(
  data: T[],
  keyExtractor: (item: T) => string,
  maxItems = 1000
) {
  const [optimizedData, setOptimizedData] = useState<T[]>([]);
  const cache = useRef(new Map<string, T>());

  useEffect(() => {
    const newCache = new Map<string, T>();
    const newData: T[] = [];

    // Add new items to cache and data
    data.forEach(item => {
      const key = keyExtractor(item);
      newCache.set(key, item);
      newData.push(item);
    });

    // Limit cache size
    if (newCache.size > maxItems) {
      const excess = newCache.size - maxItems;
      const keysToDelete = Array.from(newCache.keys()).slice(0, excess);
      
      keysToDelete.forEach(key => {
        newCache.delete(key);
      });

      setOptimizedData(Array.from(newCache.values()));
    } else {
      setOptimizedData(newData);
    }

    cache.current = newCache;
  }, [data, keyExtractor, maxItems]);

  const getItem = useCallback((key: string) => {
    return cache.current.get(key);
  }, []);

  const hasItem = useCallback((key: string) => {
    return cache.current.has(key);
  }, []);

  const clearCache = useCallback(() => {
    cache.current.clear();
    setOptimizedData([]);
  }, []);

  return {
    data: optimizedData,
    getItem,
    hasItem,
    clearCache,
    cacheSize: cache.current.size
  };
}