import React, { useRef, useEffect, useState, Suspense } from 'react';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { 
  OrbitControls, 
  Text, 
  Box, 
  Sphere, 
  Cylinder,
  Environment,
  PerspectiveCamera,
  Html,
  useTexture,
  Stage
} from '@react-three/drei';
import { VRButton, ARButton, XR, Controllers, Hands } from '@react-three/xr';
import * as THREE from 'three';
import { motion } from 'framer-motion';
import { 
  VrIcon, 
  EyeIcon, 
  CubeIcon,
  ArrowsExpandIcon,
  AdjustmentsIcon,
  PlayIcon
} from '@heroicons/react/24/outline';

// 3D Components
interface Plant3DProps {
  position: [number, number, number];
  growthStage: 'seedling' | 'vegetative' | 'flowering' | 'harvest';
  genetics: any;
  health: number;
  size: number;
}

const Plant3D: React.FC<Plant3DProps> = ({ position, growthStage, genetics, health, size }) => {
  const meshRef = useRef<THREE.Mesh>(null);
  const [hovered, setHovered] = useState(false);

  useFrame((state, delta) => {
    if (meshRef.current) {
      // Gentle swaying animation
      meshRef.current.rotation.z = Math.sin(state.clock.elapsedTime) * 0.1;
      meshRef.current.rotation.x = Math.cos(state.clock.elapsedTime * 0.5) * 0.05;
    }
  });

  const getColor = () => {
    const healthFactor = health / 100;
    const r = 1 - healthFactor * 0.5; // Less red when healthy
    const g = healthFactor; // More green when healthy
    return new THREE.Color(r, g, 0.2);
  };

  const getStageGeometry = () => {
    switch (growthStage) {
      case 'seedling':
        return <Cylinder args={[0.05, 0.05, size * 0.3]} />;
      case 'vegetative':
        return <Cylinder args={[0.1, 0.05, size * 0.6]} />;
      case 'flowering':
        return (
          <group>
            <Cylinder args={[0.15, 0.1, size * 0.8]} />
            <Sphere position={[0, size * 0.4, 0]} args={[0.1]} />
            <Sphere position={[0.1, size * 0.35, 0]} args={[0.08]} />
            <Sphere position={[-0.1, size * 0.38, 0]} args={[0.09]} />
          </group>
        );
      case 'harvest':
        return (
          <group>
            <Cylinder args={[0.2, 0.15, size]} />
            <Sphere position={[0, size * 0.5, 0]} args={[0.15]} />
            <Sphere position={[0.15, size * 0.45, 0]} args={[0.12]} />
            <Sphere position={[-0.15, size * 0.48, 0]} args={[0.13]} />
            <Sphere position={[0, size * 0.7, 0]} args={[0.1]} />
          </group>
        );
      default:
        return <Cylinder args={[0.05, 0.05, size * 0.3]} />;
    }
  };

  return (
    <group position={position}>
      <mesh
        ref={meshRef}
        onPointerOver={() => setHovered(true)}
        onPointerOut={() => setHovered(false)}
      >
        <meshStandardMaterial 
          color={getColor()} 
          emissive={hovered ? new THREE.Color(0.1, 0.1, 0.1) : new THREE.Color(0, 0, 0)}
        />
        {getStageGeometry()}
      </mesh>
      
      {/* Health indicator */}
      <group position={[0, size + 0.3, 0]}>
        <Box args={[0.5, 0.1, 0.02]}>
          <meshBasicMaterial color="gray" />
        </Box>
        <Box args={[health * 0.005, 0.08, 0.03]} position={[(health - 100) * 0.0025, 0, 0.01]}>
          <meshBasicMaterial color={health > 70 ? 'green' : health > 40 ? 'yellow' : 'red'} />
        </Box>
      </group>

      {/* Plant info */}
      {hovered && (
        <Html position={[0, size + 0.5, 0]} center>
          <div className="bg-black bg-opacity-80 text-white p-2 rounded text-xs max-w-xs">
            <div className="font-semibold">{genetics?.strain_name || 'Unknown Strain'}</div>
            <div>Stage: {growthStage}</div>
            <div>Health: {health}%</div>
            <div>Size: {(size * 10).toFixed(1)}cm</div>
            {genetics?.thc_content && <div>THC: {genetics.thc_content}%</div>}
          </div>
        </Html>
      )}
    </group>
  );
};

interface GrowRoom3DProps {
  plants: any[];
  roomType: 'tent' | 'room' | 'greenhouse' | 'outdoor';
  lighting: {
    intensity: number;
    color: string;
    schedule: number;
  };
  environment: {
    temperature: number;
    humidity: number;
    co2: number;
  };
}

const GrowRoom3D: React.FC<GrowRoom3DProps> = ({ plants, roomType, lighting, environment }) => {
  const groupRef = useRef<THREE.Group>(null);

  const getRoomGeometry = () => {
    switch (roomType) {
      case 'tent':
        return (
          <group>
            {/* Tent frame */}
            <Box args={[4, 0.05, 0.05]} position={[0, 2, -2]} />
            <Box args={[4, 0.05, 0.05]} position={[0, 2, 2]} />
            <Box args={[0.05, 0.05, 4]} position={[-2, 2, 0]} />
            <Box args={[0.05, 0.05, 4]} position={[2, 2, 0]} />
            
            {/* Tent walls (transparent) */}
            <Box args={[4, 4, 4]} position={[0, 0, 0]}>
              <meshStandardMaterial 
                color="white" 
                transparent 
                opacity={0.1} 
                side={THREE.DoubleSide}
              />
            </Box>
          </group>
        );
      case 'greenhouse':
        return (
          <group>
            {/* Glass panels */}
            <Box args={[6, 0.1, 6]} position={[0, 3, 0]}>
              <meshPhysicalMaterial 
                color="white" 
                transparent 
                opacity={0.3} 
                transmission={0.9}
                roughness={0.1}
              />
            </Box>
            <Box args={[6, 3, 0.1]} position={[0, 1.5, 3]}>
              <meshPhysicalMaterial 
                color="white" 
                transparent 
                opacity={0.3} 
                transmission={0.9}
              />
            </Box>
            <Box args={[6, 3, 0.1]} position={[0, 1.5, -3]}>
              <meshPhysicalMaterial 
                color="white" 
                transparent 
                opacity={0.3} 
                transmission={0.9}
              />
            </Box>
          </group>
        );
      default:
        return null;
    }
  };

  const getLighting = () => {
    const lightIntensity = lighting.intensity / 100;
    const lightColor = new THREE.Color(lighting.color);
    
    return (
      <group>
        <pointLight 
          position={[0, 2.5, 0]} 
          intensity={lightIntensity * 2}
          color={lightColor}
          castShadow
        />
        <pointLight 
          position={[-1, 2.5, 1]} 
          intensity={lightIntensity * 0.8}
          color={lightColor}
        />
        <pointLight 
          position={[1, 2.5, -1]} 
          intensity={lightIntensity * 0.8}
          color={lightColor}
        />
      </group>
    );
  };

  return (
    <group ref={groupRef}>
      {/* Room structure */}
      {getRoomGeometry()}
      
      {/* Lighting */}
      {getLighting()}
      
      {/* Floor */}
      <Box args={[roomType === 'tent' ? 4 : 6, 0.1, roomType === 'tent' ? 4 : 6]} position={[0, -2, 0]}>
        <meshStandardMaterial color="#8B4513" />
      </Box>
      
      {/* Plants */}
      {plants.map((plant, index) => (
        <Plant3D
          key={plant.id}
          position={plant.position || [
            (index % 3 - 1) * 1.2,
            -1.5,
            Math.floor(index / 3) * 1.2 - 1.2
          ]}
          growthStage={plant.growth_stage}
          genetics={plant.genetics}
          health={plant.health}
          size={plant.size || 1}
        />
      ))}
      
      {/* Environment indicators */}
      <group position={[2.5, 1, 2.5]}>
        <Html center>
          <div className="bg-black bg-opacity-80 text-white p-2 rounded text-xs">
            <div>🌡️ {environment.temperature}°F</div>
            <div>💧 {environment.humidity}%</div>
            <div>🫧 {environment.co2}ppm</div>
            <div>💡 {lighting.intensity}%</div>
          </div>
        </Html>
      </group>
    </group>
  );
};

const MarketVisualization3D: React.FC<{ marketData: any[] }> = ({ marketData }) => {
  return (
    <group>
      {marketData.map((item, index) => {
        const height = (item.price / 50) * 2; // Scale height based on price
        const position: [number, number, number] = [
          (index % 5 - 2) * 1.5,
          height / 2,
          Math.floor(index / 5) * 1.5 - 2
        ];
        
        return (
          <group key={index} position={position}>
            <Box args={[0.8, height, 0.8]}>
              <meshStandardMaterial 
                color={item.trend === 'up' ? 'green' : item.trend === 'down' ? 'red' : 'blue'} 
              />
            </Box>
            <Html position={[0, height + 0.5, 0]} center>
              <div className="bg-black bg-opacity-80 text-white p-1 rounded text-xs text-center">
                <div className="font-semibold">{item.strain}</div>
                <div>${item.price}</div>
                <div className={item.trend === 'up' ? 'text-green-400' : item.trend === 'down' ? 'text-red-400' : 'text-blue-400'}>
                  {item.change > 0 ? '+' : ''}{item.change}%
                </div>
              </div>
            </Html>
          </group>
        );
      })}
    </group>
  );
};

const RiskVisualization3D: React.FC<{ riskData: any }> = ({ riskData }) => {
  const { riskLevel, factors } = riskData;
  
  const getRiskColor = (level: number) => {
    if (level < 20) return 'green';
    if (level < 40) return 'yellow';
    if (level < 60) return 'orange';
    if (level < 80) return 'red';
    return 'darkred';
  };

  return (
    <group>
      {/* Central risk sphere */}
      <Sphere args={[1]} position={[0, 1, 0]}>
        <meshStandardMaterial 
          color={getRiskColor(riskLevel)}
          emissive={getRiskColor(riskLevel)}
          emissiveIntensity={0.2}
        />
      </Sphere>
      
      {/* Risk factors as orbiting elements */}
      {factors && factors.map((factor: any, index: number) => {
        const angle = (index / factors.length) * Math.PI * 2;
        const radius = 2.5;
        const x = Math.cos(angle) * radius;
        const z = Math.sin(angle) * radius;
        
        return (
          <group key={index} position={[x, 1, z]}>
            <Box args={[0.3, 0.3, 0.3]}>
              <meshStandardMaterial color={getRiskColor(factor.impact * 100)} />
            </Box>
            <Html position={[0, 0.5, 0]} center>
              <div className="bg-black bg-opacity-80 text-white p-1 rounded text-xs text-center">
                <div>{factor.name}</div>
                <div>{(factor.impact * 100).toFixed(0)}%</div>
              </div>
            </Html>
          </group>
        );
      })}
      
      {/* Risk level text */}
      <Html position={[0, 2.5, 0]} center>
        <div className="bg-black bg-opacity-80 text-white p-2 rounded text-center">
          <div className="text-lg font-bold">Risk Level</div>
          <div className={`text-2xl font-bold text-${getRiskColor(riskLevel)}-400`}>
            {riskLevel}%
          </div>
        </div>
      </Html>
    </group>
  );
};

interface ARVRSceneProps {
  sceneType: 'growroom' | 'market' | 'risk' | 'game';
  data: any;
  interactive?: boolean;
}

const ARVRScene: React.FC<ARVRSceneProps> = ({ sceneType, data, interactive = true }) => {
  const renderScene = () => {
    switch (sceneType) {
      case 'growroom':
        return <GrowRoom3D {...data} />;
      case 'market':
        return <MarketVisualization3D marketData={data.marketData || []} />;
      case 'risk':
        return <RiskVisualization3D riskData={data} />;
      case 'game':
        return (
          <group>
            <GrowRoom3D {...data.growRoom} />
            {data.showMarket && <MarketVisualization3D marketData={data.marketData || []} />}
          </group>
        );
      default:
        return null;
    }
  };

  return (
    <Suspense fallback={<Html center>Loading 3D Scene...</Html>}>
      <Environment preset="sunset" />
      <ambientLight intensity={0.6} />
      <directionalLight position={[10, 10, 5]} intensity={1} castShadow />
      
      {renderScene()}
      
      {interactive && (
        <>
          <OrbitControls 
            enablePan={true}
            enableZoom={true}
            enableRotate={true}
            maxPolarAngle={Math.PI / 2}
          />
          <Controllers />
          <Hands />
        </>
      )}
    </Suspense>
  );
};

interface ARVRFoundationProps {
  mode: 'ar' | 'vr' | '3d';
  sceneType: 'growroom' | 'market' | 'risk' | 'game';
  data: any;
  onModeChange?: (mode: 'ar' | 'vr' | '3d') => void;
}

const ARVRFoundation: React.FC<ARVRFoundationProps> = ({ 
  mode, 
  sceneType, 
  data, 
  onModeChange 
}) => {
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    // Simulate loading time for 3D assets
    const timer = setTimeout(() => setIsLoading(false), 2000);
    return () => clearTimeout(timer);
  }, []);

  const handleFullscreen = () => {
    if (!document.fullscreenElement) {
      canvasRef.current?.requestFullscreen();
      setIsFullscreen(true);
    } else {
      document.exitFullscreen();
      setIsFullscreen(false);
    }
  };

  if (isLoading) {
    return (
      <div className="w-full h-96 bg-gradient-to-b from-gray-900 to-gray-800 rounded-lg flex items-center justify-center">
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="text-center text-white"
        >
          <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-500 mx-auto mb-4"></div>
          <h3 className="text-xl font-semibold mb-2">Loading 3D Experience</h3>
          <p className="text-gray-300">Preparing immersive visualization...</p>
        </motion.div>
      </div>
    );
  }

  return (
    <div className="relative w-full h-96 bg-black rounded-lg overflow-hidden">
      {/* Control Panel */}
      <div className="absolute top-4 left-4 z-10 flex space-x-2">
        <motion.button
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          onClick={() => onModeChange?.('3d')}
          className={`p-2 rounded-lg ${mode === '3d' ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-300'} hover:bg-blue-700 transition-colors`}
        >
          <CubeIcon className="w-5 h-5" />
        </motion.button>
        
        <motion.button
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          onClick={() => onModeChange?.('ar')}
          className={`p-2 rounded-lg ${mode === 'ar' ? 'bg-green-600 text-white' : 'bg-gray-800 text-gray-300'} hover:bg-green-700 transition-colors`}
        >
          <EyeIcon className="w-5 h-5" />
        </motion.button>
        
        <motion.button
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          onClick={() => onModeChange?.('vr')}
          className={`p-2 rounded-lg ${mode === 'vr' ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-300'} hover:bg-purple-700 transition-colors`}
        >
          <VrIcon className="w-5 h-5" />
        </motion.button>
        
        <motion.button
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          onClick={handleFullscreen}
          className="p-2 rounded-lg bg-gray-800 text-gray-300 hover:bg-gray-700 transition-colors"
        >
          <ArrowsExpandIcon className="w-5 h-5" />
        </motion.button>
      </div>

      {/* Mode Info */}
      <div className="absolute top-4 right-4 z-10">
        <div className="bg-black bg-opacity-50 text-white px-3 py-1 rounded-full text-sm">
          {mode.toUpperCase()} Mode
        </div>
      </div>

      {/* XR Buttons for AR/VR */}
      {(mode === 'ar' || mode === 'vr') && (
        <div className="absolute bottom-4 left-4 z-10 space-y-2">
          {mode === 'ar' && <ARButton className="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg" />}
          {mode === 'vr' && <VRButton className="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg" />}
        </div>
      )}

      {/* 3D Canvas */}
      <Canvas
        ref={canvasRef}
        shadows
        camera={{ position: [5, 5, 5], fov: 75 }}
        style={{ width: '100%', height: '100%' }}
      >
        <XR>
          <ARVRScene 
            sceneType={sceneType} 
            data={data} 
            interactive={true}
          />
        </XR>
      </Canvas>

      {/* Instructions */}
      <div className="absolute bottom-4 right-4 z-10 max-w-xs">
        <div className="bg-black bg-opacity-70 text-white p-3 rounded-lg text-xs">
          <div className="font-semibold mb-1">Controls:</div>
          <div>• Click & drag to rotate</div>
          <div>• Mouse wheel to zoom</div>
          <div>• Right click & drag to pan</div>
          {mode !== '3d' && <div>• Use XR button for immersive mode</div>}
        </div>
      </div>
    </div>
  );
};

export default ARVRFoundation;
