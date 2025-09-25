import React, { useState, useRef, useEffect, Suspense } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { 
  OrbitControls, 
  Environment, 
  Text, 
  Box, 
  Sphere, 
  Html,
  useTexture,
  Billboard,
  Cylinder,
  Cone,
  Plane
} from '@react-three/drei';
import { XR, Controllers, Hands, useXR } from '@react-three/xr';
import { motion, AnimatePresence } from 'framer-motion';
import * as THREE from 'three';
import { 
  EyeIcon, 
  CubeIcon, 
  DevicePhoneMobileIcon,
  ComputerDesktopIcon,
  CameraIcon,
  PlayIcon,
  PauseIcon,
  ArrowsPointingOutIcon,
  XMarkIcon
} from '@heroicons/react/24/outline';

// AR Cannabis Plant Growth Visualization
interface ARPlantProps {
  stage: 'seed' | 'seedling' | 'vegetative' | 'flowering' | 'harvest';
  position: [number, number, number];
  interactive?: boolean;
  onSelect?: () => void;
}

const ARCannabisPlant: React.FC<ARPlantProps> = ({ 
  stage, 
  position, 
  interactive = false,
  onSelect 
}) => {
  const meshRef = useRef<THREE.Group>(null);
  const [hovered, setHovered] = useState(false);

  useFrame((state) => {
    if (meshRef.current && interactive) {
      meshRef.current.rotation.y += 0.005;
      meshRef.current.position.y = position[1] + Math.sin(state.clock.elapsedTime * 2) * 0.01;
    }
  });

  const getPlantGeometry = () => {
    switch (stage) {
      case 'seed':
        return <Sphere args={[0.05]} position={[0, 0.05, 0]} />;
      case 'seedling':
        return (
          <group>
            <Cylinder args={[0.01, 0.015, 0.2]} position={[0, 0.1, 0]} />
            <Sphere args={[0.08]} position={[0, 0.22, 0]} />
          </group>
        );
      case 'vegetative':
        return (
          <group>
            <Cylinder args={[0.02, 0.04, 0.6]} position={[0, 0.3, 0]} />
            <Sphere args={[0.15]} position={[0, 0.65, 0]} />
            <Sphere args={[0.12]} position={[0.15, 0.5, 0.05]} />
            <Sphere args={[0.12]} position={[-0.15, 0.5, -0.05]} />
          </group>
        );
      case 'flowering':
        return (
          <group>
            <Cylinder args={[0.025, 0.05, 0.8]} position={[0, 0.4, 0]} />
            <Sphere args={[0.18]} position={[0, 0.85, 0]} />
            <Sphere args={[0.15]} position={[0.2, 0.65, 0.1]} />
            <Sphere args={[0.15]} position={[-0.2, 0.65, -0.1]} />
            {/* Flower buds */}
            <Cone args={[0.05, 0.15]} position={[0, 1, 0]} />
            <Cone args={[0.04, 0.12]} position={[0.15, 0.85, 0]} />
            <Cone args={[0.04, 0.12]} position={[-0.15, 0.85, 0]} />
          </group>
        );
      case 'harvest':
        return (
          <group>
            <Cylinder args={[0.03, 0.06, 1]} position={[0, 0.5, 0]} />
            <Sphere args={[0.2]} position={[0, 1.05, 0]} />
            <Sphere args={[0.18]} position={[0.25, 0.8, 0.12]} />
            <Sphere args={[0.18]} position={[-0.25, 0.8, -0.12]} />
            {/* Mature buds */}
            <Cylinder args={[0.08, 0.06, 0.2]} position={[0, 1.2, 0]} />
            <Cylinder args={[0.07, 0.05, 0.18]} position={[0.2, 1.05, 0]} />
            <Cylinder args={[0.07, 0.05, 0.18]} position={[-0.2, 1.05, 0]} />
          </group>
        );
      default:
        return null;
    }
  };

  const getStageColor = () => {
    switch (stage) {
      case 'seed': return '#8B4513';
      case 'seedling': return '#90EE90';
      case 'vegetative': return '#228B22';
      case 'flowering': return '#32CD32';
      case 'harvest': return '#9ACD32';
      default: return '#228B22';
    }
  };

  return (
    <group 
      ref={meshRef}
      position={position}
      onPointerOver={() => interactive && setHovered(true)}
      onPointerOut={() => interactive && setHovered(false)}
      onClick={onSelect}
      scale={hovered ? [1.1, 1.1, 1.1] : [1, 1, 1]}
    >
      <meshStandardMaterial color={getStageColor()} />
      {getPlantGeometry()}
      
      {/* Glow effect when hovered */}
      {hovered && (
        <Sphere args={[0.3]} position={[0, 0.5, 0]}>
          <meshBasicMaterial 
            color={getStageColor()} 
            transparent 
            opacity={0.2} 
          />
        </Sphere>
      )}

      {/* AR Info Panel */}
      {hovered && (
        <Billboard position={[0, 1.5, 0]}>
          <Html center>
            <div className="bg-black/80 text-white px-3 py-2 rounded-lg text-sm min-w-max">
              <div className="font-bold">{stage.charAt(0).toUpperCase() + stage.slice(1)}</div>
              <div className="text-xs opacity-75">Cannabis Plant Stage</div>
            </div>
          </Html>
        </Billboard>
      )}
    </group>
  );
};

// VR Growing Room Experience
export const VRGrowingRoom: React.FC = () => {
  const [selectedPlant, setSelectedPlant] = useState<number | null>(null);
  
  const plants = [
    { id: 1, stage: 'vegetative' as const, position: [-2, 0, -2] as [number, number, number] },
    { id: 2, stage: 'flowering' as const, position: [0, 0, -2] as [number, number, number] },
    { id: 3, stage: 'harvest' as const, position: [2, 0, -2] as [number, number, number] },
    { id: 4, stage: 'seedling' as const, position: [-2, 0, 0] as [number, number, number] },
    { id: 5, stage: 'vegetative' as const, position: [0, 0, 0] as [number, number, number] },
    { id: 6, stage: 'flowering' as const, position: [2, 0, 0] as [number, number, number] },
  ];

  return (
    <group>
      {/* Room Environment */}
      {/* Floor */}
      <Plane args={[8, 6]} rotation={[-Math.PI / 2, 0, 0]} position={[0, 0, -1]}>
        <meshStandardMaterial color="#8B4513" />
      </Plane>
      
      {/* Walls */}
      <Plane args={[8, 4]} position={[0, 2, -4]}>
        <meshStandardMaterial color="#E5E5E5" />
      </Plane>
      <Plane args={[6, 4]} rotation={[0, Math.PI / 2, 0]} position={[-4, 2, -1]}>
        <meshStandardMaterial color="#E5E5E5" />
      </Plane>
      <Plane args={[6, 4]} rotation={[0, -Math.PI / 2, 0]} position={[4, 2, -1]}>
        <meshStandardMaterial color="#E5E5E5" />
      </Plane>

      {/* Growing Equipment */}
      <Cylinder args={[0.1, 0.1, 3]} position={[-3, 1.5, -1]}>
        <meshStandardMaterial color="#444444" />
      </Cylinder>
      <Cylinder args={[0.1, 0.1, 3]} position={[3, 1.5, -1]}>
        <meshStandardMaterial color="#444444" />
      </Cylinder>

      {/* Lighting */}
      <Box args={[1, 0.2, 0.5]} position={[0, 3.8, -1]}>
        <meshStandardMaterial color="#FFFF00" emissive="#FFFF00" emissiveIntensity={0.3} />
      </Box>

      {/* Plants */}
      {plants.map((plant) => (
        <ARCannabisPlant
          key={plant.id}
          stage={plant.stage}
          position={plant.position}
          interactive={true}
          onSelect={() => setSelectedPlant(plant.id)}
        />
      ))}

      {/* VR Room Title */}
      <Billboard position={[0, 3, -1]}>
        <Text
          fontSize={0.5}
          color="#333333"
          anchorX="center"
          anchorY="middle"
        >
          VR Cannabis Growing Room
        </Text>
      </Billboard>
    </group>
  );
};

// AR Store Locator Component
const ARStoreMarker: React.FC<{
  position: [number, number, number];
  name: string;
  status: 'open' | 'closed';
  distance: number;
}> = ({ position, name, status, distance }) => {
  const [hovered, setHovered] = useState(false);

  return (
    <group 
      position={position}
      onPointerOver={() => setHovered(true)}
      onPointerOut={() => setHovered(false)}
    >
      {/* Marker Pin */}
      <Cone args={[0.1, 0.3]} position={[0, 0.15, 0]}>
        <meshStandardMaterial color={status === 'open' ? '#10b981' : '#ef4444'} />
      </Cone>
      
      <Sphere args={[0.08]} position={[0, 0.35, 0]}>
        <meshStandardMaterial color={status === 'open' ? '#10b981' : '#ef4444'} />
      </Sphere>

      {/* Pulsing Ring */}
      <Cylinder args={[0.2, 0.2, 0.01]} position={[0, 0.01, 0]}>
        <meshBasicMaterial 
          color={status === 'open' ? '#10b981' : '#ef4444'}
          transparent 
          opacity={0.3} 
        />
      </Cylinder>

      {/* Info Billboard */}
      {hovered && (
        <Billboard position={[0, 0.8, 0]}>
          <Html center>
            <div className={`p-3 rounded-lg text-white text-sm min-w-max ${
              status === 'open' ? 'bg-green-600' : 'bg-red-600'
            }`}>
              <div className="font-bold">{name}</div>
              <div className="text-xs opacity-90">
                {status === 'open' ? 'Open' : 'Closed'} • {distance}m away
              </div>
            </div>
          </Html>
        </Billboard>
      )}
    </group>
  );
};

// AR NYC Store Locator
export const ARStoreLocator: React.FC = () => {
  const stores = [
    { id: 1, name: "Green Dreams NYC", position: [-1, 0, -1] as [number, number, number], status: 'open' as const, distance: 150 },
    { id: 2, name: "Empire Cannabis", position: [1, 0, -0.5] as [number, number, number], status: 'open' as const, distance: 280 },
    { id: 3, name: "Brooklyn Buds", position: [-0.5, 0, 1] as [number, number, number], status: 'closed' as const, distance: 420 },
    { id: 4, name: "Manhattan Mary Jane", position: [1.5, 0, 0.5] as [number, number, number], status: 'open' as const, distance: 340 },
  ];

  return (
    <group>
      {/* Ground Plane */}
      <Plane args={[4, 4]} rotation={[-Math.PI / 2, 0, 0]} position={[0, -0.1, 0]}>
        <meshStandardMaterial color="#333333" transparent opacity={0.1} />
      </Plane>

      {/* Store Markers */}
      {stores.map((store) => (
        <ARStoreMarker
          key={store.id}
          position={store.position}
          name={store.name}
          status={store.status}
          distance={store.distance}
        />
      ))}

      {/* AR Compass */}
      <Billboard position={[2, 1, -2]}>
        <Cylinder args={[0.3, 0.3, 0.05]} rotation={[Math.PI / 2, 0, 0]}>
          <meshStandardMaterial color="#2563eb" />
        </Cylinder>
        <Cone args={[0.05, 0.2]} position={[0, 0, 0.15]} rotation={[0, 0, 0]}>
          <meshStandardMaterial color="#ef4444" />
        </Cone>
      </Billboard>

      {/* AR Title */}
      <Billboard position={[0, 2, 0]}>
        <Text
          fontSize={0.3}
          color="#ffffff"
          anchorX="center"
          anchorY="middle"
        >
          AR Store Locator
        </Text>
      </Billboard>
    </group>
  );
};

// VR/AR Mode Selector
interface VRARSelectorProps {
  mode: 'ar' | 'vr' | '3d';
  onModeChange: (mode: 'ar' | 'vr' | '3d') => void;
}

export const VRARModeSelector: React.FC<VRARSelectorProps> = ({ mode, onModeChange }) => {
  return (
    <div className="fixed top-4 left-4 z-50 bg-white/90 backdrop-blur-sm rounded-lg p-4 shadow-lg">
      <div className="flex space-x-2">
        <button
          onClick={() => onModeChange('3d')}
          className={`flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all ${
            mode === '3d'
              ? 'bg-blue-600 text-white shadow-lg'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          }`}
        >
          <ComputerDesktopIcon className="w-5 h-5" />
          <span>3D View</span>
        </button>
        
        <button
          onClick={() => onModeChange('ar')}
          className={`flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all ${
            mode === 'ar'
              ? 'bg-green-600 text-white shadow-lg'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          }`}
        >
          <CameraIcon className="w-5 h-5" />
          <span>AR Mode</span>
        </button>
        
        <button
          onClick={() => onModeChange('vr')}
          className={`flex items-center space-x-2 px-4 py-2 rounded-lg font-medium transition-all ${
            mode === 'vr'
              ? 'bg-purple-600 text-white shadow-lg'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          }`}
        >
          <EyeIcon className="w-5 h-5" />
          <span>VR Mode</span>
        </button>
      </div>
      
      <div className="mt-3 text-xs text-gray-600">
        {mode === '3d' && 'Navigate with mouse and scroll'}
        {mode === 'ar' && 'Point your camera at a flat surface'}
        {mode === 'vr' && 'Put on your VR headset'}
      </div>
    </div>
  );
};

// AR/VR Experience Component
interface ARVRExperienceProps {
  experience: 'growing' | 'stores';
  mode: 'ar' | 'vr' | '3d';
}

const ARVRExperience: React.FC<ARVRExperienceProps> = ({ experience, mode }) => {
  const { session } = useXR();

  return (
    <group>
      {/* Lighting Setup */}
      <ambientLight intensity={0.4} />
      <directionalLight position={[10, 10, 5]} intensity={1} />
      <pointLight position={[0, 5, 0]} intensity={0.5} color="#ff69b4" />

      {/* Experience Content */}
      {experience === 'growing' ? <VRGrowingRoom /> : <ARStoreLocator />}

      {/* VR Controllers */}
      {mode === 'vr' && session && (
        <>
          <Controllers rayMaterial={{ color: '#10b981' }} />
          <Hands />
        </>
      )}

      {/* Environment */}
      <Environment preset={mode === 'ar' ? 'studio' : 'sunset'} />
    </group>
  );
};

// Main AR/VR Dashboard
export const ARVRDashboard: React.FC = () => {
  const [mode, setMode] = useState<'ar' | 'vr' | '3d'>('3d');
  const [experience, setExperience] = useState<'growing' | 'stores'>('growing');
  const [isXRSupported, setIsXRSupported] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);

  useEffect(() => {
    // Check for XR support
    if ('xr' in navigator) {
      navigator.xr?.isSessionSupported('immersive-ar').then(setIsXRSupported);
    }
  }, []);

  const enterFullscreen = () => {
    const canvas = document.querySelector('canvas');
    if (canvas?.requestFullscreen) {
      canvas.requestFullscreen();
      setIsFullscreen(true);
    }
  };

  const exitFullscreen = () => {
    if (document.exitFullscreen) {
      document.exitFullscreen();
      setIsFullscreen(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto p-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="mb-8"
      >
        <h1 className="text-3xl font-bold text-gray-900 mb-4">
          AR/VR Cannabis Experience
        </h1>
        <p className="text-gray-600">
          Immersive augmented and virtual reality experiences for cannabis education and store discovery
        </p>
      </motion.div>

      {/* Experience Selector */}
      <div className="mb-6">
        <div className="flex space-x-4">
          <button
            onClick={() => setExperience('growing')}
            className={`flex items-center space-x-2 px-6 py-3 rounded-lg font-medium transition-all ${
              experience === 'growing'
                ? 'bg-green-600 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            <CubeIcon className="w-5 h-5" />
            <span>Cannabis Growing</span>
          </button>
          <button
            onClick={() => setExperience('stores')}
            className={`flex items-center space-x-2 px-6 py-3 rounded-lg font-medium transition-all ${
              experience === 'stores'
                ? 'bg-green-600 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            <DevicePhoneMobileIcon className="w-5 h-5" />
            <span>Store Locator</span>
          </button>
        </div>
      </div>

      {/* AR/VR Canvas */}
      <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        className="relative bg-gradient-to-b from-blue-400 to-blue-600 rounded-2xl shadow-2xl overflow-hidden"
        style={{ height: '600px' }}
      >
        {/* Mode Selector */}
        <VRARModeSelector mode={mode} onModeChange={setMode} />

        {/* Fullscreen Controls */}
        <div className="absolute top-4 right-4 z-50 flex space-x-2">
          <button
            onClick={isFullscreen ? exitFullscreen : enterFullscreen}
            className="bg-white/90 backdrop-blur-sm p-2 rounded-lg shadow-lg hover:bg-white transition-colors"
          >
            {isFullscreen ? (
              <XMarkIcon className="w-5 h-5 text-gray-600" />
            ) : (
              <ArrowsPointingOutIcon className="w-5 h-5 text-gray-600" />
            )}
          </button>
        </div>

        {/* 3D/AR/VR Canvas */}
        <Canvas
          camera={{ position: [0, 2, 5], fov: 50 }}
          style={{ width: '100%', height: '100%' }}
        >
          <XR>
            <Suspense fallback={null}>
              <ARVRExperience experience={experience} mode={mode} />
              
              {/* Camera Controls for 3D mode */}
              {mode === '3d' && (
                <OrbitControls 
                  enablePan={true}
                  enableZoom={true}
                  enableRotate={true}
                  minDistance={2}
                  maxDistance={20}
                />
              )}
            </Suspense>
          </XR>
        </Canvas>

        {/* Mode-specific overlays */}
        {mode === 'ar' && !isXRSupported && (
          <div className="absolute inset-0 flex items-center justify-center bg-black/50">
            <div className="text-center text-white p-6">
              <CameraIcon className="w-12 h-12 mx-auto mb-4" />
              <h3 className="text-xl font-bold mb-2">AR Not Supported</h3>
              <p className="text-sm opacity-90">
                Your device doesn't support WebXR AR features.
                Try using a mobile device with AR capabilities.
              </p>
            </div>
          </div>
        )}
      </motion.div>

      {/* Info Panel */}
      <div className="mt-6 bg-white rounded-lg shadow-lg p-6">
        <h3 className="text-lg font-semibold mb-4">Experience Features</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="text-center">
            <ComputerDesktopIcon className="w-8 h-8 mx-auto text-blue-600 mb-2" />
            <h4 className="font-medium mb-1">3D Visualization</h4>
            <p className="text-sm text-gray-600">
              Interactive 3D models with mouse/touch controls
            </p>
          </div>
          <div className="text-center">
            <CameraIcon className="w-8 h-8 mx-auto text-green-600 mb-2" />
            <h4 className="font-medium mb-1">Augmented Reality</h4>
            <p className="text-sm text-gray-600">
              Overlay digital content on your real environment
            </p>
          </div>
          <div className="text-center">
            <EyeIcon className="w-8 h-8 mx-auto text-purple-600 mb-2" />
            <h4 className="font-medium mb-1">Virtual Reality</h4>
            <p className="text-sm text-gray-600">
              Fully immersive VR experiences with hand tracking
            </p>
          </div>
        </div>
      </div>

      {/* Device Compatibility */}
      <div className="mt-4 text-center text-sm text-gray-500">
        <p>
          Best experienced on devices with WebXR support. 
          {isXRSupported ? ' ✅ AR supported on this device' : ' ❌ AR not available'}
        </p>
      </div>
    </div>
  );
};