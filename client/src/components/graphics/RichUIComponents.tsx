import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  ChartBarIcon, 
  MapIcon, 
  CubeIcon, 
  SparklesIcon,
  EyeIcon,
  ChevronRightIcon
} from '@heroicons/react/24/outline';

// Gradient Card Component with Hover Effects
interface GradientCardProps {
  title: string;
  description: string;
  icon: React.ReactNode;
  gradient: string;
  onClick?: () => void;
  stats?: Array<{label: string, value: string}>;
}

export const GradientCard: React.FC<GradientCardProps> = ({
  title,
  description,
  icon,
  gradient,
  onClick,
  stats
}) => {
  return (
    <motion.div
      className={`relative overflow-hidden rounded-2xl p-6 cursor-pointer bg-gradient-to-br ${gradient}`}
      whileHover={{ scale: 1.02, y: -5 }}
      whileTap={{ scale: 0.98 }}
      onClick={onClick}
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3 }}
    >
      {/* Background Pattern */}
      <div className="absolute inset-0 bg-white/5 backdrop-blur-sm"></div>
      <div className="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
      <div className="absolute -bottom-10 -left-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
      
      <div className="relative z-10">
        <div className="flex items-center justify-between mb-4">
          <div className="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
            {icon}
          </div>
          <ChevronRightIcon className="w-6 h-6 text-white/70" />
        </div>
        
        <h3 className="text-xl font-bold text-white mb-2">{title}</h3>
        <p className="text-white/80 text-sm mb-4 line-clamp-2">{description}</p>
        
        {stats && (
          <div className="grid grid-cols-2 gap-4">
            {stats.map((stat, index) => (
              <div key={index} className="bg-white/10 rounded-lg p-2 backdrop-blur-sm">
                <div className="text-white/60 text-xs">{stat.label}</div>
                <div className="text-white font-semibold text-lg">{stat.value}</div>
              </div>
            ))}
          </div>
        )}
      </div>
    </motion.div>
  );
};

// Animated Counter Component
interface AnimatedCounterProps {
  value: number;
  suffix?: string;
  prefix?: string;
  duration?: number;
}

export const AnimatedCounter: React.FC<AnimatedCounterProps> = ({
  value,
  suffix = '',
  prefix = '',
  duration = 2
}) => {
  const [displayValue, setDisplayValue] = useState(0);

  useEffect(() => {
    const startTime = Date.now();
    const endTime = startTime + duration * 1000;

    const updateCounter = () => {
      const now = Date.now();
      const progress = Math.min((now - startTime) / (duration * 1000), 1);
      const easeOutQuart = 1 - Math.pow(1 - progress, 4);
      
      setDisplayValue(Math.floor(easeOutQuart * value));

      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      }
    };

    updateCounter();
  }, [value, duration]);

  return (
    <motion.span
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      className="font-bold"
    >
      {prefix}{displayValue.toLocaleString()}{suffix}
    </motion.span>
  );
};

// Glass Morphism Panel
interface GlassPanelProps {
  children: React.ReactNode;
  className?: string;
}

export const GlassPanel: React.FC<GlassPanelProps> = ({ children, className = '' }) => {
  return (
    <motion.div
      className={`backdrop-blur-md bg-white/10 border border-white/20 rounded-2xl shadow-xl ${className}`}
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.3 }}
    >
      {children}
    </motion.div>
  );
};

// Floating Action Button with Ripple Effect
interface FloatingButtonProps {
  icon: React.ReactNode;
  label: string;
  onClick: () => void;
  color?: string;
}

export const FloatingButton: React.FC<FloatingButtonProps> = ({
  icon,
  label,
  onClick,
  color = 'bg-green-500'
}) => {
  const [ripples, setRipples] = useState<Array<{id: number, x: number, y: number}>>([]);

  const handleClick = (e: React.MouseEvent) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    
    const newRipple = { id: Date.now(), x, y };
    setRipples([...ripples, newRipple]);
    
    setTimeout(() => {
      setRipples(prev => prev.filter(ripple => ripple.id !== newRipple.id));
    }, 600);
    
    onClick();
  };

  return (
    <motion.button
      className={`relative overflow-hidden ${color} text-white p-4 rounded-full shadow-lg group`}
      whileHover={{ scale: 1.1 }}
      whileTap={{ scale: 0.9 }}
      onClick={handleClick}
      initial={{ scale: 0 }}
      animate={{ scale: 1 }}
      transition={{ type: 'spring', stiffness: 500, damping: 30 }}
    >
      {/* Ripple Effects */}
      {ripples.map((ripple) => (
        <motion.div
          key={ripple.id}
          className="absolute bg-white/30 rounded-full pointer-events-none"
          style={{
            left: ripple.x - 20,
            top: ripple.y - 20,
            width: 40,
            height: 40,
          }}
          initial={{ scale: 0, opacity: 1 }}
          animate={{ scale: 4, opacity: 0 }}
          transition={{ duration: 0.6 }}
        />
      ))}
      
      <div className="relative z-10 flex items-center gap-2">
        {icon}
        <span className="font-medium">{label}</span>
      </div>
      
      {/* Glow Effect */}
      <div className="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
    </motion.button>
  );
};

// Progress Ring Component
interface ProgressRingProps {
  progress: number;
  size: number;
  strokeWidth: number;
  color: string;
  label?: string;
}

export const ProgressRing: React.FC<ProgressRingProps> = ({
  progress,
  size,
  strokeWidth,
  color,
  label
}) => {
  const center = size / 2;
  const radius = center - strokeWidth / 2;
  const circumference = 2 * Math.PI * radius;
  const strokeDasharray = circumference;
  const strokeDashoffset = circumference - (progress / 100) * circumference;

  return (
    <div className="relative inline-flex items-center justify-center">
      <svg width={size} height={size} className="transform -rotate-90">
        <circle
          cx={center}
          cy={center}
          r={radius}
          stroke="currentColor"
          strokeWidth={strokeWidth}
          fill="transparent"
          className="text-gray-200"
        />
        <motion.circle
          cx={center}
          cy={center}
          r={radius}
          stroke={color}
          strokeWidth={strokeWidth}
          fill="transparent"
          strokeLinecap="round"
          strokeDasharray={strokeDasharray}
          initial={{ strokeDashoffset: circumference }}
          animate={{ strokeDashoffset }}
          transition={{ duration: 1, ease: "easeInOut" }}
        />
      </svg>
      <div className="absolute inset-0 flex items-center justify-center">
        <div className="text-center">
          <div className="text-2xl font-bold">{progress}%</div>
          {label && <div className="text-xs text-gray-600">{label}</div>}
        </div>
      </div>
    </div>
  );
};

// Particle System Background
export const ParticleBackground: React.FC = () => {
  const [particles, setParticles] = useState<Array<{
    id: number;
    x: number;
    y: number;
    size: number;
    opacity: number;
    speed: number;
  }>>([]);

  useEffect(() => {
    const particleCount = 50;
    const newParticles = Array.from({ length: particleCount }, (_, i) => ({
      id: i,
      x: Math.random() * window.innerWidth,
      y: Math.random() * window.innerHeight,
      size: Math.random() * 4 + 1,
      opacity: Math.random() * 0.5 + 0.1,
      speed: Math.random() * 0.5 + 0.1
    }));
    setParticles(newParticles);

    const animate = () => {
      setParticles(prev => 
        prev.map(particle => ({
          ...particle,
          y: particle.y - particle.speed,
          x: particle.x + Math.sin(particle.y * 0.01) * 0.5,
          y: particle.y < -10 ? window.innerHeight + 10 : particle.y - particle.speed
        }))
      );
    };

    const interval = setInterval(animate, 50);
    return () => clearInterval(interval);
  }, []);

  return (
    <div className="fixed inset-0 pointer-events-none z-0">
      {particles.map(particle => (
        <motion.div
          key={particle.id}
          className="absolute bg-green-400 rounded-full"
          style={{
            width: particle.size,
            height: particle.size,
            left: particle.x,
            top: particle.y,
            opacity: particle.opacity
          }}
          animate={{
            y: particle.y,
            x: particle.x
          }}
          transition={{ duration: 0 }}
        />
      ))}
    </div>
  );
};

// Interactive Dashboard Stats
interface DashboardStatsProps {
  stats: Array<{
    label: string;
    value: number;
    change: number;
    icon: React.ReactNode;
    color: string;
  }>;
}

export const DashboardStats: React.FC<DashboardStatsProps> = ({ stats }) => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {stats.map((stat, index) => (
        <motion.div
          key={stat.label}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.1 }}
          className="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-shadow duration-300"
        >
          <div className="flex items-center justify-between mb-4">
            <div className={`p-3 ${stat.color} rounded-xl`}>
              {stat.icon}
            </div>
            <div className={`text-sm font-medium px-2 py-1 rounded-full ${
              stat.change > 0 ? 'text-green-600 bg-green-100' : 'text-red-600 bg-red-100'
            }`}>
              {stat.change > 0 ? '+' : ''}{stat.change}%
            </div>
          </div>
          
          <div className="space-y-1">
            <div className="text-2xl font-bold text-gray-900">
              <AnimatedCounter value={stat.value} />
            </div>
            <div className="text-gray-600 text-sm">{stat.label}</div>
          </div>
        </motion.div>
      ))}
    </div>
  );
};

// Demo Component showcasing all rich UI elements
export const RichGraphicsDemo: React.FC = () => {
  const [selectedCard, setSelectedCard] = useState<string | null>(null);

  const sampleStats = [
    {
      label: "Active Stores",
      value: 1247,
      change: 12,
      icon: <MapIcon className="w-6 h-6 text-white" />,
      color: "bg-blue-500"
    },
    {
      label: "Cannabis Score",
      value: 87,
      change: 5,
      icon: <SparklesIcon className="w-6 h-6 text-white" />,
      color: "bg-green-500"
    },
    {
      label: "Total Donations",
      value: 25840,
      change: -2,
      icon: <ChartBarIcon className="w-6 h-6 text-white" />,
      color: "bg-purple-500"
    },
    {
      label: "VR Sessions",
      value: 342,
      change: 28,
      icon: <EyeIcon className="w-6 h-6 text-white" />,
      color: "bg-orange-500"
    }
  ];

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 p-6 relative">
      <ParticleBackground />
      
      <div className="relative z-10 max-w-7xl mx-auto space-y-8">
        <motion.div
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center mb-12"
        >
          <h1 className="text-4xl font-bold text-white mb-4">
            SmokeoutNYC Rich Graphics Interface
          </h1>
          <p className="text-white/70 text-lg">
            Experience the next generation cannabis platform with immersive visuals
          </p>
        </motion.div>

        {/* Dashboard Stats */}
        <DashboardStats stats={sampleStats} />

        {/* Feature Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <GradientCard
            title="3D Cannabis Growing"
            description="Immersive 3D cultivation simulation with realistic plant growth"
            icon={<CubeIcon className="w-6 h-6 text-white" />}
            gradient="from-green-500 to-emerald-600"
            onClick={() => setSelectedCard('3d-growing')}
            stats={[
              { label: "Plants", value: "156" },
              { label: "Harvest", value: "89%" }
            ]}
          />
          
          <GradientCard
            title="Interactive Map"
            description="Real-time dispensary tracking with rich visual indicators"
            icon={<MapIcon className="w-6 h-6 text-white" />}
            gradient="from-blue-500 to-cyan-600"
            onClick={() => setSelectedCard('interactive-map')}
            stats={[
              { label: "Stores", value: "1.2K" },
              { label: "Online", value: "847" }
            ]}
          />
          
          <GradientCard
            title="AR/VR Experience"
            description="Virtual reality cannabis education and visualization"
            icon={<EyeIcon className="w-6 h-6 text-white" />}
            gradient="from-purple-500 to-pink-600"
            onClick={() => setSelectedCard('ar-vr')}
            stats={[
              { label: "Sessions", value: "342" },
              { label: "Rating", value: "4.8★" }
            ]}
          />
        </div>

        {/* Glass Panel Example */}
        <GlassPanel className="p-8">
          <h2 className="text-2xl font-bold text-white mb-4">Cannabis Policy Analytics</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div className="text-center">
              <ProgressRing 
                progress={87} 
                size={120} 
                strokeWidth={8} 
                color="#10b981"
                label="Pro-Cannabis"
              />
            </div>
            <div className="text-center">
              <ProgressRing 
                progress={23} 
                size={120} 
                strokeWidth={8} 
                color="#ef4444"
                label="Anti-Cannabis"
              />
            </div>
            <div className="text-center">
              <ProgressRing 
                progress={65} 
                size={120} 
                strokeWidth={8} 
                color="#3b82f6"
                label="Neutral"
              />
            </div>
          </div>
        </GlassPanel>

        {/* Floating Action Buttons */}
        <div className="fixed bottom-8 right-8 space-y-4">
          <FloatingButton
            icon={<SparklesIcon className="w-6 h-6" />}
            label="AI Assistant"
            onClick={() => console.log('AI Assistant clicked')}
            color="bg-purple-500"
          />
          <FloatingButton
            icon={<CubeIcon className="w-6 h-6" />}
            label="3D View"
            onClick={() => console.log('3D View clicked')}
            color="bg-green-500"
          />
        </div>

        {/* Modal for Selected Card */}
        <AnimatePresence>
          {selectedCard && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
              onClick={() => setSelectedCard(null)}
            >
              <motion.div
                initial={{ scale: 0.8, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                exit={{ scale: 0.8, opacity: 0 }}
                className="bg-white rounded-2xl p-8 max-w-2xl w-full"
                onClick={(e) => e.stopPropagation()}
              >
                <h3 className="text-2xl font-bold mb-4">
                  {selectedCard === '3d-growing' && '3D Cannabis Growing Simulation'}
                  {selectedCard === 'interactive-map' && 'Interactive Dispensary Map'}
                  {selectedCard === 'ar-vr' && 'AR/VR Cannabis Experience'}
                </h3>
                <p className="text-gray-600 mb-6">
                  This feature would open a rich, interactive interface with advanced graphics
                  and real-time data visualization.
                </p>
                <button
                  onClick={() => setSelectedCard(null)}
                  className="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors"
                >
                  Close
                </button>
              </motion.div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
    </div>
  );
};