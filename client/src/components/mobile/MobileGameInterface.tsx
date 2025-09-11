import React, { useState, useEffect, useRef } from 'react';
import { 
  TouchIcon, 
  ZapIcon, 
  BatteryIcon, 
  WifiIcon, 
  SettingsIcon,
  MenuIcon,
  XIcon
} from 'lucide-react';
import { usePerformance, useResponsiveLayout, useOptimizedAnimations } from '../../hooks/usePerformance';

interface MobileGameInterfaceProps {
  children: React.ReactNode;
  onSettingsOpen?: () => void;
}

const MobileGameInterface: React.FC<MobileGameInterfaceProps> = ({ 
  children, 
  onSettingsOpen 
}) => {
  const { metrics, settings, optimizePerformance } = usePerformance();
  const { layout, isTouchDevice } = useResponsiveLayout();
  const { shouldAnimate, getAnimationProps } = useOptimizedAnimations();
  
  const [showMobileMenu, setShowMobileMenu] = useState(false);
  const [showPerformanceHUD, setShowPerformanceHUD] = useState(false);
  const [orientation, setOrientation] = useState<'portrait' | 'landscape'>('portrait');
  const [hapticEnabled, setHapticEnabled] = useState(true);
  
  const gameContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleOrientationChange = () => {
      setOrientation(window.innerWidth > window.innerHeight ? 'landscape' : 'portrait');
      
      // Force layout recalculation after orientation change
      setTimeout(() => {
        if (gameContainerRef.current) {
          gameContainerRef.current.style.height = `${window.innerHeight}px`;
        }
      }, 100);
    };

    handleOrientationChange();
    window.addEventListener('orientationchange', handleOrientationChange);
    window.addEventListener('resize', handleOrientationChange);

    return () => {
      window.removeEventListener('orientationchange', handleOrientationChange);
      window.removeEventListener('resize', handleOrientationChange);
    };
  }, []);

  // Haptic feedback
  const triggerHaptic = (type: 'light' | 'medium' | 'heavy' = 'light') => {
    if (!hapticEnabled || !('vibrate' in navigator)) return;
    
    const patterns = {
      light: [10],
      medium: [20],
      heavy: [30]
    };
    
    navigator.vibrate(patterns[type]);
  };

  // Touch gesture handlers
  const handleTouchStart = (e: React.TouchEvent) => {
    const touch = e.touches[0];
    const element = e.currentTarget as HTMLElement;
    
    // Add press effect
    element.style.transform = 'scale(0.95)';
    triggerHaptic('light');
  };

  const handleTouchEnd = (e: React.TouchEvent) => {
    const element = e.currentTarget as HTMLElement;
    element.style.transform = 'scale(1)';
  };

  // Performance indicator color
  const getPerformanceColor = (fps: number) => {
    if (fps >= 45) return 'text-green-500';
    if (fps >= 30) return 'text-yellow-500';
    return 'text-red-500';
  };

  // Network status color
  const getNetworkColor = (latency: number) => {
    if (latency < 100) return 'text-green-500';
    if (latency < 300) return 'text-yellow-500';
    return 'text-red-500';
  };

  const MobileHUD = () => (
    <div className="absolute top-2 right-2 z-50 flex flex-col space-y-2">
      {/* Performance toggle */}
      <button
        onClick={() => setShowPerformanceHUD(!showPerformanceHUD)}
        className="w-10 h-10 bg-black bg-opacity-50 rounded-full flex items-center justify-center text-white"
        onTouchStart={handleTouchStart}
        onTouchEnd={handleTouchEnd}
      >
        <ZapIcon className="w-5 h-5" />
      </button>

      {/* Performance HUD */}
      {showPerformanceHUD && (
        <div 
          className="bg-black bg-opacity-80 rounded-lg p-3 text-white text-xs min-w-32"
          style={shouldAnimate ? getAnimationProps(200) : {}}
        >
          <div className="space-y-1">
            <div className={`flex justify-between ${getPerformanceColor(metrics.fps)}`}>
              <span>FPS:</span>
              <span>{metrics.fps}</span>
            </div>
            
            <div className="flex justify-between text-white">
              <span>Memory:</span>
              <span>{Math.round(metrics.memoryUsage * 100)}%</span>
            </div>
            
            <div className={`flex justify-between ${getNetworkColor(metrics.networkLatency)}`}>
              <span>Ping:</span>
              <span>{Math.round(metrics.networkLatency)}ms</span>
            </div>
            
            {metrics.batteryLevel && (
              <div className="flex justify-between text-white">
                <BatteryIcon className="w-3 h-3" />
                <span>{Math.round(metrics.batteryLevel * 100)}%</span>
              </div>
            )}
            
            <div className="pt-1 border-t border-gray-600">
              <div className="text-center">
                <span className={`inline-block w-2 h-2 rounded-full mr-1 ${
                  metrics.devicePerformanceRating === 'high' ? 'bg-green-500' :
                  metrics.devicePerformanceRating === 'medium' ? 'bg-yellow-500' :
                  'bg-red-500'
                }`}></span>
                {metrics.devicePerformanceRating}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  const MobileMenu = () => (
    <div 
      className={`fixed inset-0 bg-black bg-opacity-50 z-40 ${showMobileMenu ? 'block' : 'hidden'}`}
      onClick={() => setShowMobileMenu(false)}
    >
      <div 
        className="absolute right-0 top-0 h-full w-80 bg-gray-900 text-white p-6 overflow-y-auto"
        style={shouldAnimate ? getAnimationProps(300) : {}}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-bold">Game Settings</h2>
          <button
            onClick={() => setShowMobileMenu(false)}
            className="w-8 h-8 flex items-center justify-center"
          >
            <XIcon className="w-6 h-6" />
          </button>
        </div>

        <div className="space-y-6">
          {/* Performance Settings */}
          <div>
            <h3 className="text-lg font-semibold mb-3">Performance</h3>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span>Animation Quality</span>
                <select 
                  value={settings.animationQuality}
                  className="bg-gray-800 rounded px-2 py-1 text-sm"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
              
              <div className="flex justify-between items-center">
                <span>Auto-Optimize</span>
                <input
                  type="checkbox"
                  checked={settings.autoOptimize}
                  className="rounded"
                />
              </div>
              
              <button
                onClick={optimizePerformance}
                className="w-full bg-blue-600 hover:bg-blue-700 py-2 px-4 rounded transition-colors"
              >
                Optimize Now
              </button>
            </div>
          </div>

          {/* Mobile Settings */}
          <div>
            <h3 className="text-lg font-semibold mb-3">Mobile Settings</h3>
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span>Haptic Feedback</span>
                <input
                  type="checkbox"
                  checked={hapticEnabled}
                  onChange={(e) => setHapticEnabled(e.target.checked)}
                  className="rounded"
                />
              </div>
              
              <div className="flex justify-between items-center">
                <span>Show Performance HUD</span>
                <input
                  type="checkbox"
                  checked={showPerformanceHUD}
                  onChange={(e) => setShowPerformanceHUD(e.target.checked)}
                  className="rounded"
                />
              </div>
            </div>
          </div>

          {/* Device Info */}
          <div>
            <h3 className="text-lg font-semibold mb-3">Device Info</h3>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span>Layout:</span>
                <span>{layout}</span>
              </div>
              <div className="flex justify-between">
                <span>Touch:</span>
                <span>{isTouchDevice ? 'Yes' : 'No'}</span>
              </div>
              <div className="flex justify-between">
                <span>Orientation:</span>
                <span>{orientation}</span>
              </div>
              <div className="flex justify-between">
                <span>Performance:</span>
                <span>{metrics.devicePerformanceRating}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );

  const MobileControls = () => (
    <div className="fixed bottom-4 left-4 right-4 z-30">
      <div className="flex justify-between items-end">
        {/* Menu button */}
        <button
          onClick={() => setShowMobileMenu(true)}
          className="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center text-white shadow-lg"
          onTouchStart={handleTouchStart}
          onTouchEnd={handleTouchEnd}
        >
          <MenuIcon className="w-6 h-6" />
        </button>

        {/* Quick actions */}
        <div className="flex space-x-2">
          <button
            onClick={onSettingsOpen}
            className="w-10 h-10 bg-gray-700 bg-opacity-80 rounded-full flex items-center justify-center text-white"
            onTouchStart={handleTouchStart}
            onTouchEnd={handleTouchEnd}
          >
            <SettingsIcon className="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>
  );

  // Only render mobile interface on mobile devices
  if (layout !== 'mobile') {
    return <div>{children}</div>;
  }

  return (
    <div 
      ref={gameContainerRef}
      className="relative w-full min-h-screen bg-gray-900 overflow-hidden"
      style={{ 
        height: `${window.innerHeight}px`,
        touchAction: 'manipulation' // Prevent double-tap zoom
      }}
    >
      {/* Main game content */}
      <div className="w-full h-full">
        {children}
      </div>

      {/* Mobile HUD */}
      <MobileHUD />

      {/* Mobile Controls */}
      <MobileControls />

      {/* Mobile Menu */}
      <MobileMenu />

      {/* Orientation hint */}
      {orientation === 'portrait' && (
        <div className="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 pointer-events-none">
          <div className="text-white text-center p-6">
            <div className="text-6xl mb-4">📱</div>
            <h2 className="text-xl font-bold mb-2">Rotate for Better Experience</h2>
            <p className="text-gray-300">
              This game is optimized for landscape mode
            </p>
          </div>
        </div>
      )}

      {/* Network status indicator */}
      {metrics.networkLatency > 500 && (
        <div className="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-red-600 text-white px-4 py-2 rounded-lg z-50">
          <div className="flex items-center space-x-2">
            <WifiIcon className="w-5 h-5" />
            <span>Poor connection</span>
          </div>
        </div>
      )}

      {/* Low battery warning */}
      {metrics.batteryLevel && metrics.batteryLevel < 0.15 && (
        <div className="fixed bottom-20 left-4 right-4 bg-orange-600 text-white p-3 rounded-lg z-40">
          <div className="flex items-center space-x-2">
            <BatteryIcon className="w-5 h-5" />
            <span>Low battery - Performance automatically reduced</span>
          </div>
        </div>
      )}

      {/* CSS for mobile optimizations */}
      <style jsx>{`
        .mobile-device {
          -webkit-tap-highlight-color: transparent;
          -webkit-touch-callout: none;
          -webkit-user-select: none;
          user-select: none;
        }
        
        /* Optimize scrolling performance */
        .mobile-device * {
          -webkit-transform: translateZ(0);
          transform: translateZ(0);
        }
        
        /* Remove iOS bounce */
        .mobile-device body {
          position: fixed;
          overflow: hidden;
          width: 100%;
          height: 100%;
        }
        
        /* Optimize touch targets */
        button, .touch-target {
          min-width: 44px;
          min-height: 44px;
        }
      `}</style>
    </div>
  );
};

export default MobileGameInterface;