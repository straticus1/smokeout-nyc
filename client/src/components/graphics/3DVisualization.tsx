import React, { useRef, useState, useEffect, Suspense } from 'react';
import { Canvas, useFrame, useLoader, useThree } from '@react-three/fiber';
import { 
  OrbitControls, 
  Environment, 
  Text, 
  Box, 
  Sphere, 
  Cylinder,
  Html,
  useTexture,
  Sky,
  Stars,
  Cloud
} from '@react-three/drei';
import { motion } from 'framer-motion';
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader';

// 3D Cannabis Plant Component
interface CannabisPlant3DProps {
  stage: 'seed' | 'seedling' | 'vegetative' | 'flowering' | 'harvest';
  health: number;
  position: [number, number, number];
  onClick?: () => void;
}

const CannabisPlant3D: React.FC<CannabisPlant3DProps> = ({ 
  stage, 
  health, 
  position, 
  onClick 
}) => {
  const meshRef = useRef<THREE.Group>(null);
  const [hovered, setHovered] = useState(false);

  useFrame((state) => {
    if (meshRef.current) {
      meshRef.current.rotation.y += 0.002;
      meshRef.current.position.y = position[1] + Math.sin(state.clock.elapsedTime) * 0.02;
    }
  });

  const getPlantScale = () => {
    switch (stage) {
      case 'seed': return [0.1, 0.1, 0.1];
      case 'seedling': return [0.3, 0.3, 0.3];
      case 'vegetative': return [0.7, 0.7, 0.7];
      case 'flowering': return [1, 1, 1];
      case 'harvest': return [1.2, 1.2, 1.2];
      default: return [1, 1, 1];
    }
  };

  const getHealthColor = () => {
    if (health > 80) return '#10b981';
    if (health > 60) return '#fbbf24';
    if (health > 40) return '#f97316';
    return '#ef4444';
  };

  return (
    <group 
      ref={meshRef} 
      position={position} 
      scale={getPlantScale()}
      onPointerOver={() => setHovered(true)}
      onPointerOut={() => setHovered(false)}
      onClick={onClick}
    >
      {/* Plant Stem */}
      <Cylinder args={[0.02, 0.05, 1]} position={[0, 0.5, 0]}>
        <meshLambertMaterial color="#8B4513" />
      </Cylinder>

      {/* Plant Leaves */}
      {stage !== 'seed' && (
        <>
          <Sphere args={[0.3, 8, 6]} position={[0, 1, 0]}>
            <meshLambertMaterial color={getHealthColor()} />
          </Sphere>
          <Sphere args={[0.25, 8, 6]} position={[0.2, 0.8, 0.1]}>
            <meshLambertMaterial color={getHealthColor()} />
          </Sphere>
          <Sphere args={[0.25, 8, 6]} position={[-0.2, 0.8, -0.1]}>
            <meshLambertMaterial color={getHealthColor()} />
          </Sphere>
        </>
      )}

      {/* Cannabis Buds (flowering stage) */}
      {stage === 'flowering' && (
        <>
          <Sphere args={[0.1, 6, 4]} position={[0, 1.2, 0]}>
            <meshLambertMaterial color="#9333ea" />
          </Sphere>
          <Sphere args={[0.08, 6, 4]} position={[0.15, 1.1, 0]}>
            <meshLambertMaterial color="#9333ea" />
          </Sphere>
          <Sphere args={[0.08, 6, 4]} position={[-0.15, 1.1, 0]}>
            <meshLambertMaterial color="#9333ea" />
          </Sphere>
        </>
      )}

      {/* Health Indicator */}
      {hovered && (
        <Html distanceFactor={10}>
          <div className="bg-black/80 text-white px-2 py-1 rounded text-sm">
            <div>{stage.charAt(0).toUpperCase() + stage.slice(1)}</div>
            <div>Health: {health}%</div>
          </div>
        </Html>
      )}

      {/* Glow Effect */}
      <Sphere args={[0.5]} position={[0, 1, 0]}>
        <meshBasicMaterial 
          color={getHealthColor()} 
          transparent 
          opacity={hovered ? 0.2 : 0.1} 
        />
      </Sphere>
    </group>
  );
};

// 3D Growing Room Component
export const GrowingRoom3D: React.FC = () => {
  const [selectedPlant, setSelectedPlant] = useState<number | null>(null);
  const [plants, setPlants] = useState([
    { id: 1, stage: 'vegetative' as const, health: 85, position: [-2, 0, -2] as [number, number, number] },
    { id: 2, stage: 'flowering' as const, health: 92, position: [0, 0, -2] as [number, number, number] },
    { id: 3, stage: 'seedling' as const, health: 78, position: [2, 0, -2] as [number, number, number] },
    { id: 4, stage: 'harvest' as const, health: 95, position: [-2, 0, 0] as [number, number, number] },
    { id: 5, stage: 'vegetative' as const, health: 88, position: [0, 0, 0] as [number, number, number] },
    { id: 6, stage: 'flowering' as const, health: 90, position: [2, 0, 0] as [number, number, number] },
  ]);

  return (
    <div className="w-full h-96 bg-gradient-to-b from-blue-200 to-green-200 rounded-lg overflow-hidden">
      <Canvas camera={{ position: [5, 5, 5], fov: 50 }}>
        <Suspense fallback={null}>
          {/* Lighting */}
          <ambientLight intensity={0.4} />
          <directionalLight position={[10, 10, 5]} intensity={1} />
          <pointLight position={[0, 5, 0]} intensity={0.5} color="#ff69b4" />

          {/* Environment */}
          <Sky sunPosition={[100, 20, 100]} />
          <Environment preset="sunset" />

          {/* Room Floor */}
          <Box args={[6, 0.1, 4]} position={[0, -0.05, -1]}>
            <meshLambertMaterial color="#8B4513" />
          </Box>

          {/* Room Walls */}
          <Box args={[0.1, 3, 4]} position={[-3, 1.5, -1]}>
            <meshLambertMaterial color="#d1d5db" />
          </Box>
          <Box args={[0.1, 3, 4]} position={[3, 1.5, -1]}>
            <meshLambertMaterial color="#d1d5db" />
          </Box>
          <Box args={[6, 3, 0.1]} position={[0, 1.5, -3]}>
            <meshLambertMaterial color="#d1d5db" />
          </Box>

          {/* Growing Equipment */}
          <Cylinder args={[0.3, 0.3, 2]} position={[-2.5, 1, -1]}>
            <meshLambertMaterial color="#374151" />
          </Cylinder>
          <Cylinder args={[0.3, 0.3, 2]} position={[2.5, 1, -1]}>
            <meshLambertMaterial color="#374151" />
          </Cylinder>

          {/* Plants */}
          {plants.map((plant) => (
            <CannabisPlant3D
              key={plant.id}
              stage={plant.stage}
              health={plant.health}
              position={plant.position}
              onClick={() => setSelectedPlant(plant.id)}
            />
          ))}

          {/* Controls */}
          <OrbitControls 
            enablePan={false} 
            enableZoom={true}
            enableRotate={true}
            maxPolarAngle={Math.PI / 2}
            minDistance={3}
            maxDistance={10}
          />

          {/* Room Label */}
          <Text
            position={[0, 2.5, -1]}
            fontSize={0.3}
            color="#1f2937"
            anchorX="center"
            anchorY="middle"
          >
            Cannabis Growing Room
          </Text>
        </Suspense>
      </Canvas>
    </div>
  );
};

// 3D NYC Map Component
interface Store3DMarkerProps {
  position: [number, number, number];
  status: 'open' | 'closed' | 'unknown';
  name: string;
  onClick?: () => void;
}

const Store3DMarker: React.FC<Store3DMarkerProps> = ({ position, status, name, onClick }) => {
  const meshRef = useRef<THREE.Group>(null);
  const [hovered, setHovered] = useState(false);

  useFrame(() => {
    if (meshRef.current) {
      meshRef.current.rotation.y += 0.01;
    }
  });

  const getStatusColor = () => {
    switch (status) {
      case 'open': return '#10b981';
      case 'closed': return '#ef4444';
      default: return '#6b7280';
    }
  };

  return (
    <group 
      ref={meshRef}
      position={position}
      onPointerOver={() => setHovered(true)}
      onPointerOut={() => setHovered(false)}
      onClick={onClick}
    >
      {/* Store Marker */}
      <Cylinder args={[0.05, 0.05, 0.3]} position={[0, 0.15, 0]}>
        <meshLambertMaterial color={getStatusColor()} />
      </Cylinder>
      
      <Sphere args={[0.08]} position={[0, 0.35, 0]}>
        <meshLambertMaterial color={getStatusColor()} />
      </Sphere>

      {/* Glow Effect */}
      <Sphere args={[0.15]} position={[0, 0.35, 0]}>
        <meshBasicMaterial 
          color={getStatusColor()} 
          transparent 
          opacity={hovered ? 0.3 : 0.1} 
        />
      </Sphere>

      {/* Store Info */}
      {hovered && (
        <Html distanceFactor={5}>
          <div className="bg-white shadow-lg rounded p-2 text-xs min-w-max">
            <div className="font-semibold">{name}</div>
            <div className={`text-xs ${status === 'open' ? 'text-green-600' : 'text-red-600'}`}>
              {status.charAt(0).toUpperCase() + status.slice(1)}
            </div>
          </div>
        </Html>
      )}
    </group>
  );
};

export const NYCMap3D: React.FC = () => {
  const [selectedStore, setSelectedStore] = useState<number | null>(null);
  const stores = [
    { id: 1, name: "Green Dreams", position: [-1, 0, -1] as [number, number, number], status: 'open' as const },
    { id: 2, name: "NYC Cannabis Co", position: [0, 0, 0] as [number, number, number], status: 'open' as const },
    { id: 3, name: "Empire State Smoke", position: [1, 0, 1] as [number, number, number], status: 'closed' as const },
    { id: 4, name: "Brooklyn Buds", position: [-2, 0, 1] as [number, number, number], status: 'open' as const },
    { id: 5, name: "Manhattan Mary Jane", position: [1.5, 0, -1.5] as [number, number, number], status: 'unknown' as const },
  ];

  return (
    <div className="w-full h-96 bg-gradient-to-b from-blue-400 to-blue-600 rounded-lg overflow-hidden">
      <Canvas camera={{ position: [3, 3, 3], fov: 50 }}>
        <Suspense fallback={null}>
          {/* Lighting */}
          <ambientLight intensity={0.6} />
          <directionalLight position={[5, 5, 5]} intensity={1} />

          {/* NYC Skyline (simplified) */}
          <group position={[0, 0, -3]}>
            {/* Buildings */}
            <Box args={[0.3, 1.5, 0.3]} position={[-1, 0.75, 0]}>
              <meshLambertMaterial color="#6b7280" />
            </Box>
            <Box args={[0.2, 2, 0.2]} position={[0, 1, 0]}>
              <meshLambertMaterial color="#6b7280" />
            </Box>
            <Box args={[0.4, 1.2, 0.4]} position={[1, 0.6, 0]}>
              <meshLambertMaterial color="#6b7280" />
            </Box>
            <Box args={[0.25, 1.8, 0.25]} position={[-0.7, 0.9, 0]}>
              <meshLambertMaterial color="#6b7280" />
            </Box>
          </group>

          {/* Ground/Streets */}
          <Box args={[4, 0.05, 4]} position={[0, -0.025, 0]}>
            <meshLambertMaterial color="#4b5563" />
          </Box>

          {/* Street Lines */}
          <Box args={[0.05, 0.06, 4]} position={[0, -0.02, 0]}>
            <meshLambertMaterial color="#fbbf24" />
          </Box>
          <Box args={[4, 0.06, 0.05]} position={[0, -0.02, 0]}>
            <meshLambertMaterial color="#fbbf24" />
          </Box>

          {/* Store Markers */}
          {stores.map((store) => (
            <Store3DMarker
              key={store.id}
              position={store.position}
              status={store.status}
              name={store.name}
              onClick={() => setSelectedStore(store.id)}
            />
          ))}

          {/* Controls */}
          <OrbitControls 
            enablePan={true} 
            enableZoom={true}
            enableRotate={true}
            minDistance={2}
            maxDistance={8}
          />

          {/* Map Title */}
          <Text
            position={[0, 2, -2]}
            fontSize={0.2}
            color="#ffffff"
            anchorX="center"
            anchorY="middle"
          >
            NYC Cannabis Store Map
          </Text>

          {/* Environment */}
          <Stars radius={100} depth={50} count={5000} factor={4} saturation={0} />
        </Suspense>
      </Canvas>
    </div>
  );
};

// 3D Data Visualization Component
interface DataVisualization3DProps {
  data: Array<{
    label: string;
    value: number;
    color: string;
  }>;
}

export const DataVisualization3D: React.FC<DataVisualization3DProps> = ({ data }) => {
  const maxValue = Math.max(...data.map(d => d.value));

  return (
    <div className="w-full h-96 bg-gradient-to-b from-gray-900 to-gray-700 rounded-lg overflow-hidden">
      <Canvas camera={{ position: [5, 5, 5], fov: 50 }}>
        <Suspense fallback={null}>
          {/* Lighting */}
          <ambientLight intensity={0.4} />
          <directionalLight position={[10, 10, 5]} intensity={1} />

          {/* 3D Bar Chart */}
          {data.map((item, index) => {
            const height = (item.value / maxValue) * 3;
            const x = (index - data.length / 2) * 0.8;
            
            return (
              <group key={item.label} position={[x, height / 2, 0]}>
                <Box args={[0.6, height, 0.6]}>
                  <meshLambertMaterial color={item.color} />
                </Box>
                
                <Text
                  position={[0, -height / 2 - 0.3, 0]}
                  fontSize={0.2}
                  color="#ffffff"
                  anchorX="center"
                  anchorY="middle"
                  rotation={[-Math.PI / 6, 0, 0]}
                >
                  {item.label}
                </Text>
                
                <Text
                  position={[0, height / 2 + 0.2, 0]}
                  fontSize={0.15}
                  color="#ffffff"
                  anchorX="center"
                  anchorY="middle"
                >
                  {item.value}
                </Text>
              </group>
            );
          })}

          {/* Grid Lines */}
          {[0, 1, 2, 3].map((i) => (
            <Box key={i} args={[data.length, 0.01, 0.01]} position={[0, i, 0]}>
              <meshLambertMaterial color="#ffffff" opacity={0.2} transparent />
            </Box>
          ))}

          <OrbitControls />
          <Environment preset="night" />
        </Suspense>
      </Canvas>
    </div>
  );
};

// Main 3D Graphics Dashboard
export const Graphics3DDashboard: React.FC = () => {
  const [activeView, setActiveView] = useState<'growing' | 'map' | 'data'>('growing');

  const sampleData = [
    { label: 'Open Stores', value: 45, color: '#10b981' },
    { label: 'Closed Stores', value: 12, color: '#ef4444' },
    { label: 'New Openings', value: 8, color: '#3b82f6' },
    { label: 'Under Review', value: 23, color: '#f59e0b' },
  ];

  return (
    <div className="max-w-7xl mx-auto p-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="mb-8"
      >
        <h1 className="text-3xl font-bold text-gray-900 mb-4">
          3D Visualization Dashboard
        </h1>
        <p className="text-gray-600">
          Immersive 3D graphics for cannabis cultivation and dispensary tracking
        </p>
      </motion.div>

      {/* View Selector */}
      <div className="flex space-x-4 mb-6">
        {[
          { key: 'growing', label: '🌱 Growing Room', desc: 'Virtual cannabis cultivation' },
          { key: 'map', label: '🗺️ NYC Map', desc: 'Interactive store locations' },
          { key: 'data', label: '📊 Data Viz', desc: '3D charts and analytics' }
        ].map((view) => (
          <button
            key={view.key}
            onClick={() => setActiveView(view.key as any)}
            className={`flex-1 p-4 rounded-lg text-left transition-all ${
              activeView === view.key
                ? 'bg-green-100 border-2 border-green-500 text-green-800'
                : 'bg-white border-2 border-gray-200 text-gray-600 hover:border-gray-300'
            }`}
          >
            <div className="font-semibold">{view.label}</div>
            <div className="text-sm opacity-70">{view.desc}</div>
          </button>
        ))}
      </div>

      {/* 3D Visualization */}
      <motion.div
        key={activeView}
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.3 }}
        className="bg-white rounded-2xl shadow-lg p-6"
      >
        {activeView === 'growing' && <GrowingRoom3D />}
        {activeView === 'map' && <NYCMap3D />}
        {activeView === 'data' && <DataVisualization3D data={sampleData} />}
      </motion.div>

      {/* Controls Info */}
      <div className="mt-6 bg-gray-50 rounded-lg p-4">
        <h3 className="font-semibold text-gray-800 mb-2">3D Controls</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
          <div>
            <span className="font-medium">Left Click + Drag:</span> Rotate view
          </div>
          <div>
            <span className="font-medium">Scroll Wheel:</span> Zoom in/out
          </div>
          <div>
            <span className="font-medium">Right Click + Drag:</span> Pan view
          </div>
        </div>
      </div>
    </div>
  );
};