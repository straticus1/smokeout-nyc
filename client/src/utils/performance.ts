/**
 * Performance monitoring and optimization utilities for mobile gaming
 */

export interface PerformanceMetrics {
  fps: number;
  memoryUsage: number;
  renderTime: number;
  networkLatency: number;
  batteryLevel?: number;
  devicePerformanceRating: 'low' | 'medium' | 'high';
}

export class PerformanceMonitor {
  private metrics: PerformanceMetrics = {
    fps: 60,
    memoryUsage: 0,
    renderTime: 0,
    networkLatency: 0,
    devicePerformanceRating: 'medium'
  };

  private frameCount = 0;
  private lastTime = performance.now();
  private renderTimes: number[] = [];
  private networkTests: number[] = [];
  
  constructor() {
    this.detectDevicePerformance();
    this.startMonitoring();
  }

  /**
   * Detect device performance rating based on hardware specs
   */
  private detectDevicePerformance() {
    const canvas = document.createElement('canvas');
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    
    let performanceRating: 'low' | 'medium' | 'high' = 'medium';
    
    // Check WebGL capabilities
    if (gl) {
      const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
      if (debugInfo) {
        const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
        
        // Basic GPU detection for mobile devices
        if (renderer.toLowerCase().includes('adreno')) {
          // Qualcomm Adreno GPUs
          const adrenoVersion = parseInt(renderer.match(/adreno.*?(\d+)/i)?.[1] || '0');
          performanceRating = adrenoVersion >= 600 ? 'high' : adrenoVersion >= 400 ? 'medium' : 'low';
        } else if (renderer.toLowerCase().includes('mali')) {
          // ARM Mali GPUs
          performanceRating = renderer.includes('G') ? 'high' : 'medium';
        } else if (renderer.toLowerCase().includes('powervr')) {
          // PowerVR GPUs (Apple devices)
          performanceRating = 'high';
        }
      }
    }

    // Check memory and CPU cores
    const memory = (navigator as any).deviceMemory || 4;
    const cores = navigator.hardwareConcurrency || 4;
    
    if (memory <= 2 || cores <= 2) {
      performanceRating = 'low';
    } else if (memory >= 8 && cores >= 8) {
      performanceRating = 'high';
    }

    // Check if we're on mobile
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    if (isMobile && performanceRating === 'high') {
      // Mobile devices generally perform worse than desktop
      performanceRating = 'medium';
    }

    this.metrics.devicePerformanceRating = performanceRating;
  }

  /**
   * Start monitoring performance metrics
   */
  private startMonitoring() {
    // FPS monitoring
    const measureFPS = () => {
      const now = performance.now();
      this.frameCount++;
      
      if (now - this.lastTime >= 1000) {
        this.metrics.fps = Math.round((this.frameCount * 1000) / (now - this.lastTime));
        this.frameCount = 0;
        this.lastTime = now;
      }
      
      requestAnimationFrame(measureFPS);
    };
    requestAnimationFrame(measureFPS);

    // Memory monitoring
    if ('memory' in performance) {
      setInterval(() => {
        const memory = (performance as any).memory;
        this.metrics.memoryUsage = memory.usedJSHeapSize / memory.jsHeapSizeLimit;
      }, 5000);
    }

    // Battery monitoring
    if ('getBattery' in navigator) {
      (navigator as any).getBattery().then((battery: any) => {
        this.metrics.batteryLevel = battery.level;
        
        battery.addEventListener('levelchange', () => {
          this.metrics.batteryLevel = battery.level;
        });
      });
    }
  }

  /**
   * Measure render time for a component
   */
  measureRenderTime(componentName: string, renderFunction: () => void): number {
    const startTime = performance.now();
    renderFunction();
    const endTime = performance.now();
    
    const renderTime = endTime - startTime;
    this.renderTimes.push(renderTime);
    
    // Keep only last 10 measurements
    if (this.renderTimes.length > 10) {
      this.renderTimes.shift();
    }
    
    this.metrics.renderTime = this.renderTimes.reduce((a, b) => a + b, 0) / this.renderTimes.length;
    
    // Log slow renders
    if (renderTime > 16) { // 60fps = 16.67ms per frame
      console.warn(`Slow render detected for ${componentName}: ${renderTime.toFixed(2)}ms`);
    }
    
    return renderTime;
  }

  /**
   * Test network latency
   */
  async testNetworkLatency(endpoint: string = '/api/ping'): Promise<number> {
    const startTime = performance.now();
    
    try {
      await fetch(endpoint, { method: 'HEAD' });
      const latency = performance.now() - startTime;
      
      this.networkTests.push(latency);
      if (this.networkTests.length > 5) {
        this.networkTests.shift();
      }
      
      this.metrics.networkLatency = this.networkTests.reduce((a, b) => a + b, 0) / this.networkTests.length;
      return latency;
    } catch (error) {
      console.error('Network latency test failed:', error);
      return -1;
    }
  }

  /**
   * Get current performance metrics
   */
  getMetrics(): PerformanceMetrics {
    return { ...this.metrics };
  }

  /**
   * Get recommended settings based on device performance
   */
  getRecommendedSettings() {
    const { devicePerformanceRating, fps, memoryUsage, batteryLevel } = this.metrics;
    
    let settings = {
      animationQuality: 'high',
      particleCount: 100,
      shadowQuality: 'high',
      textureQuality: 'high',
      refreshRate: 60,
      enableEffects: true,
      enableAnimations: true,
      autoOptimize: false
    };

    // Adjust based on device performance
    if (devicePerformanceRating === 'low') {
      settings = {
        ...settings,
        animationQuality: 'low',
        particleCount: 20,
        shadowQuality: 'low',
        textureQuality: 'medium',
        refreshRate: 30,
        enableEffects: false,
        autoOptimize: true
      };
    } else if (devicePerformanceRating === 'medium') {
      settings = {
        ...settings,
        animationQuality: 'medium',
        particleCount: 50,
        shadowQuality: 'medium',
        textureQuality: 'high',
        refreshRate: 45
      };
    }

    // Adjust based on current performance
    if (fps < 30) {
      settings.animationQuality = 'low';
      settings.particleCount = Math.min(settings.particleCount, 20);
      settings.enableEffects = false;
      settings.autoOptimize = true;
    } else if (fps < 45) {
      settings.animationQuality = 'medium';
      settings.particleCount = Math.min(settings.particleCount, 50);
    }

    // Adjust based on memory usage
    if (memoryUsage > 0.8) {
      settings.textureQuality = 'medium';
      settings.particleCount = Math.min(settings.particleCount, 30);
      settings.autoOptimize = true;
    }

    // Adjust based on battery level
    if (batteryLevel && batteryLevel < 0.2) {
      settings.refreshRate = Math.min(settings.refreshRate, 30);
      settings.enableAnimations = false;
      settings.enableEffects = false;
      settings.autoOptimize = true;
    }

    return settings;
  }
}

/**
 * Debounce function for performance optimization
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number,
  immediate = false
): T {
  let timeout: NodeJS.Timeout | null = null;
  
  return ((...args: Parameters<T>) => {
    const callNow = immediate && !timeout;
    
    if (timeout) clearTimeout(timeout);
    
    timeout = setTimeout(() => {
      timeout = null;
      if (!immediate) func(...args);
    }, wait);
    
    if (callNow) func(...args);
  }) as T;
}

/**
 * Throttle function for performance optimization
 */
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  limit: number
): T {
  let inThrottle: boolean;
  
  return ((...args: Parameters<T>) => {
    if (!inThrottle) {
      func(...args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  }) as T;
}

/**
 * Virtual scrolling implementation for large lists
 */
export class VirtualScroller {
  private container: HTMLElement;
  private itemHeight: number;
  private visibleCount: number;
  private totalCount: number;
  private scrollTop = 0;
  private renderRange = { start: 0, end: 0 };

  constructor(
    container: HTMLElement,
    itemHeight: number,
    totalCount: number
  ) {
    this.container = container;
    this.itemHeight = itemHeight;
    this.totalCount = totalCount;
    this.visibleCount = Math.ceil(container.clientHeight / itemHeight) + 2; // +2 for buffer
    
    this.updateRenderRange();
    this.container.addEventListener('scroll', this.handleScroll.bind(this));
  }

  private handleScroll() {
    this.scrollTop = this.container.scrollTop;
    this.updateRenderRange();
  }

  private updateRenderRange() {
    const start = Math.floor(this.scrollTop / this.itemHeight);
    const end = Math.min(start + this.visibleCount, this.totalCount);
    
    this.renderRange = { start, end };
  }

  getRenderRange() {
    return this.renderRange;
  }

  getOffsetY() {
    return this.renderRange.start * this.itemHeight;
  }

  getTotalHeight() {
    return this.totalCount * this.itemHeight;
  }
}

/**
 * Image lazy loading for performance
 */
export class LazyImageLoader {
  private observer: IntersectionObserver;
  private images = new Set<HTMLImageElement>();

  constructor(rootMargin = '50px') {
    this.observer = new IntersectionObserver(
      this.handleIntersection.bind(this),
      { rootMargin }
    );
  }

  private handleIntersection(entries: IntersectionObserverEntry[]) {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target as HTMLImageElement;
        const src = img.dataset.src;
        
        if (src) {
          img.src = src;
          img.removeAttribute('data-src');
          this.observer.unobserve(img);
          this.images.delete(img);
        }
      }
    });
  }

  observe(img: HTMLImageElement) {
    this.images.add(img);
    this.observer.observe(img);
  }

  unobserve(img: HTMLImageElement) {
    this.images.delete(img);
    this.observer.unobserve(img);
  }

  disconnect() {
    this.observer.disconnect();
    this.images.clear();
  }
}

/**
 * Memory-efficient object pool for game objects
 */
export class ObjectPool<T> {
  private objects: T[] = [];
  private createFn: () => T;
  private resetFn: (obj: T) => void;
  private maxSize: number;

  constructor(
    createFn: () => T,
    resetFn: (obj: T) => void,
    initialSize = 10,
    maxSize = 100
  ) {
    this.createFn = createFn;
    this.resetFn = resetFn;
    this.maxSize = maxSize;

    // Pre-populate pool
    for (let i = 0; i < initialSize; i++) {
      this.objects.push(this.createFn());
    }
  }

  get(): T {
    if (this.objects.length > 0) {
      return this.objects.pop()!;
    }
    return this.createFn();
  }

  release(obj: T) {
    if (this.objects.length < this.maxSize) {
      this.resetFn(obj);
      this.objects.push(obj);
    }
  }

  clear() {
    this.objects.length = 0;
  }
}

/**
 * Mobile-specific optimizations
 */
export class MobileOptimizer {
  private isLowEndDevice = false;
  private isMobile = false;

  constructor() {
    this.detectDevice();
    this.applyOptimizations();
  }

  private detectDevice() {
    this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    
    // Detect low-end devices
    const memory = (navigator as any).deviceMemory || 4;
    const cores = navigator.hardwareConcurrency || 4;
    
    this.isLowEndDevice = memory <= 2 || cores <= 2;
  }

  private applyOptimizations() {
    if (!this.isMobile) return;

    // Disable hover effects on mobile
    document.body.classList.add('mobile-device');

    // Reduce passive event listeners for better scrolling
    const passiveEvents = ['touchstart', 'touchmove', 'wheel'];
    passiveEvents.forEach(event => {
      document.addEventListener(event, () => {}, { passive: true });
    });

    // Optimize viewport for mobile
    const viewport = document.querySelector('meta[name=viewport]');
    if (viewport) {
      viewport.setAttribute(
        'content',
        'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, shrink-to-fit=no'
      );
    }

    // Enable hardware acceleration for animations
    document.body.style.transform = 'translateZ(0)';
  }

  isMobileDevice() {
    return this.isMobile;
  }

  isLowEndDevice() {
    return this.isLowEndDevice;
  }

  optimizeForMobile(element: HTMLElement) {
    // Add hardware acceleration
    element.style.transform = 'translateZ(0)';
    element.style.willChange = 'transform';
    
    // Prevent text selection on game elements
    element.style.userSelect = 'none';
    element.style.webkitUserSelect = 'none';
    element.style.webkitTouchCallout = 'none';
    
    // Prevent context menu on long press
    element.addEventListener('contextmenu', (e) => e.preventDefault());
  }
}

// Global performance monitor instance
export const performanceMonitor = new PerformanceMonitor();
export const mobileOptimizer = new MobileOptimizer();