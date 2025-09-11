import React, { useState, useEffect, useRef, useMemo } from 'react';
import { useVirtualScrolling, useMemoryOptimizedData } from '../hooks/usePerformance';

interface VirtualScrollListProps<T> {
  items: T[];
  itemHeight: number;
  containerHeight: number;
  renderItem: (item: T, index: number) => React.ReactNode;
  keyExtractor: (item: T, index: number) => string;
  onEndReached?: () => void;
  onEndReachedThreshold?: number;
  loadingComponent?: React.ReactNode;
  emptyComponent?: React.ReactNode;
  overscan?: number; // Number of items to render outside visible area
  className?: string;
  maxCachedItems?: number;
}

function VirtualScrollList<T>({
  items,
  itemHeight,
  containerHeight,
  renderItem,
  keyExtractor,
  onEndReached,
  onEndReachedThreshold = 0.8,
  loadingComponent,
  emptyComponent,
  overscan = 5,
  className = '',
  maxCachedItems = 1000
}: VirtualScrollListProps<T>) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [isLoading, setIsLoading] = useState(false);
  
  // Use memory-optimized data management
  const { data: optimizedItems, clearCache } = useMemoryOptimizedData(
    items,
    (item, index) => keyExtractor(item, index || 0),
    maxCachedItems
  );

  // Use virtual scrolling
  const { startIndex, endIndex, totalHeight, offsetY, handleScroll } = useVirtualScrolling(
    itemHeight,
    optimizedItems.length,
    containerHeight
  );

  // Apply overscan to render additional items for smooth scrolling
  const startWithOverscan = Math.max(0, startIndex - overscan);
  const endWithOverscan = Math.min(optimizedItems.length, endIndex + overscan);

  // Memoize visible items to prevent unnecessary re-renders
  const visibleItems = useMemo(() => {
    return optimizedItems.slice(startWithOverscan, endWithOverscan);
  }, [optimizedItems, startWithOverscan, endWithOverscan]);

  // Handle infinite scrolling
  useEffect(() => {
    if (!onEndReached || isLoading) return;

    const container = containerRef.current;
    if (!container) return;

    const scrollHandler = () => {
      const { scrollTop, scrollHeight, clientHeight } = container;
      const scrollRatio = (scrollTop + clientHeight) / scrollHeight;

      if (scrollRatio >= onEndReachedThreshold) {
        setIsLoading(true);
        onEndReached();
        
        // Reset loading state after a short delay
        setTimeout(() => setIsLoading(false), 1000);
      }
    };

    container.addEventListener('scroll', scrollHandler);
    return () => container.removeEventListener('scroll', scrollHandler);
  }, [onEndReached, onEndReachedThreshold, isLoading]);

  // Performance monitoring
  const renderStart = useRef<number>(0);
  useEffect(() => {
    renderStart.current = performance.now();
  });

  useEffect(() => {
    const renderTime = performance.now() - renderStart.current;
    if (renderTime > 16) { // More than one frame at 60fps
      console.warn(`VirtualScrollList slow render: ${renderTime.toFixed(2)}ms`);
    }
  });

  // Render empty state
  if (optimizedItems.length === 0) {
    return (
      <div className={`${className} flex items-center justify-center`} style={{ height: containerHeight }}>
        {emptyComponent || (
          <div className="text-gray-500 text-center">
            <p>No items to display</p>
          </div>
        )}
      </div>
    );
  }

  return (
    <div
      ref={containerRef}
      className={`${className} overflow-auto`}
      style={{ height: containerHeight }}
      onScroll={handleScroll}
    >
      {/* Total height spacer */}
      <div style={{ height: totalHeight, position: 'relative' }}>
        {/* Visible items container */}
        <div
          style={{
            transform: `translateY(${offsetY}px)`,
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
          }}
        >
          {visibleItems.map((item, index) => {
            const actualIndex = startWithOverscan + index;
            const key = keyExtractor(item, actualIndex);
            
            return (
              <div
                key={key}
                style={{
                  height: itemHeight,
                  overflow: 'hidden', // Prevent content overflow affecting layout
                }}
              >
                {renderItem(item, actualIndex)}
              </div>
            );
          })}
        </div>
      </div>

      {/* Loading indicator */}
      {isLoading && (
        <div className="flex justify-center py-4">
          {loadingComponent || (
            <div className="flex items-center space-x-2 text-gray-600">
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
              <span>Loading more...</span>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// Higher-order component for automatic item height calculation
interface AutoHeightVirtualScrollListProps<T> extends Omit<VirtualScrollListProps<T>, 'itemHeight'> {
  estimatedItemHeight?: number;
}

export function AutoHeightVirtualScrollList<T>({
  estimatedItemHeight = 100,
  ...props
}: AutoHeightVirtualScrollListProps<T>) {
  const [measuredHeight, setMeasuredHeight] = useState(estimatedItemHeight);
  const measureRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    // Measure actual height of first item
    if (measureRef.current && props.items.length > 0) {
      const height = measureRef.current.offsetHeight;
      if (height > 0 && height !== measuredHeight) {
        setMeasuredHeight(height);
      }
    }
  }, [props.items, measuredHeight]);

  return (
    <>
      {/* Hidden measurement element */}
      {props.items.length > 0 && (
        <div
          ref={measureRef}
          style={{ 
            position: 'absolute', 
            visibility: 'hidden', 
            top: -9999,
            left: -9999,
            width: '100%'
          }}
        >
          {props.renderItem(props.items[0], 0)}
        </div>
      )}
      
      <VirtualScrollList<T>
        {...props}
        itemHeight={measuredHeight}
      />
    </>
  );
}

// Specialized version for plant/genetics lists with search and filtering
interface GameItemListProps<T> {
  items: T[];
  searchTerm: string;
  filterFn?: (item: T) => boolean;
  sortFn?: (a: T, b: T) => number;
  renderItem: (item: T, index: number) => React.ReactNode;
  keyExtractor: (item: T, index: number) => string;
  itemHeight?: number;
  containerHeight: number;
  onEndReached?: () => void;
  className?: string;
}

export function GameItemList<T>({
  items,
  searchTerm,
  filterFn,
  sortFn,
  renderItem,
  keyExtractor,
  itemHeight = 120,
  containerHeight,
  onEndReached,
  className = ''
}: GameItemListProps<T>) {
  // Memoize filtered and sorted items
  const processedItems = useMemo(() => {
    let result = [...items];

    // Apply filter
    if (filterFn) {
      result = result.filter(filterFn);
    }

    // Apply search
    if (searchTerm) {
      const searchLower = searchTerm.toLowerCase();
      result = result.filter(item => {
        // This assumes items have a name property, adjust as needed
        const itemName = (item as any).name?.toLowerCase() || '';
        const itemDescription = (item as any).description?.toLowerCase() || '';
        return itemName.includes(searchLower) || itemDescription.includes(searchLower);
      });
    }

    // Apply sort
    if (sortFn) {
      result.sort(sortFn);
    }

    return result;
  }, [items, searchTerm, filterFn, sortFn]);

  return (
    <VirtualScrollList
      items={processedItems}
      itemHeight={itemHeight}
      containerHeight={containerHeight}
      renderItem={renderItem}
      keyExtractor={keyExtractor}
      onEndReached={onEndReached}
      className={className}
      emptyComponent={
        <div className="text-center py-8 text-gray-500">
          <p className="text-lg mb-2">No items found</p>
          {searchTerm && (
            <p className="text-sm">Try adjusting your search: "{searchTerm}"</p>
          )}
        </div>
      }
    />
  );
}

export default VirtualScrollList;