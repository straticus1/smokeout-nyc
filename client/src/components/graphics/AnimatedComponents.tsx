import React, { useState, useEffect, useRef } from 'react';
import { motion, useAnimation, useInView, AnimatePresence } from 'framer-motion';
import { 
  PlayIcon, 
  PauseIcon, 
  SparklesIcon,
  ArrowRightIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';

// Loading Animations
export const LoadingSpinner: React.FC<{ size?: number; color?: string }> = ({ 
  size = 40, 
  color = '#10b981' 
}) => {
  return (
    <div className="flex items-center justify-center">
      <motion.div
        className="rounded-full border-4 border-transparent"
        style={{ 
          width: size, 
          height: size,
          borderTopColor: color,
          borderRightColor: color
        }}
        animate={{ rotate: 360 }}
        transition={{
          duration: 1,
          repeat: Infinity,
          ease: "linear"
        }}
      />
    </div>
  );
};

export const LoadingDots: React.FC<{ color?: string }> = ({ color = '#10b981' }) => {
  return (
    <div className="flex space-x-2">
      {[0, 1, 2].map((i) => (
        <motion.div
          key={i}
          className="w-3 h-3 rounded-full"
          style={{ backgroundColor: color }}
          animate={{
            y: [-4, 4, -4],
            opacity: [0.4, 1, 0.4]
          }}
          transition={{
            duration: 1.2,
            repeat: Infinity,
            delay: i * 0.2,
            ease: "easeInOut"
          }}
        />
      ))}
    </div>
  );
};

export const LoadingWave: React.FC<{ color?: string }> = ({ color = '#10b981' }) => {
  return (
    <div className="flex items-center space-x-1">
      {[0, 1, 2, 3, 4].map((i) => (
        <motion.div
          key={i}
          className="w-2 rounded-full"
          style={{ backgroundColor: color }}
          animate={{
            height: [12, 24, 12],
          }}
          transition={{
            duration: 1,
            repeat: Infinity,
            delay: i * 0.1,
            ease: "easeInOut"
          }}
        />
      ))}
    </div>
  );
};

// Page Transition Components
interface PageTransitionProps {
  children: React.ReactNode;
  direction?: 'left' | 'right' | 'up' | 'down';
}

export const PageTransition: React.FC<PageTransitionProps> = ({ 
  children, 
  direction = 'right' 
}) => {
  const variants = {
    left: { x: -300, opacity: 0 },
    right: { x: 300, opacity: 0 },
    up: { y: -300, opacity: 0 },
    down: { y: 300, opacity: 0 }
  };

  return (
    <motion.div
      initial={variants[direction]}
      animate={{ x: 0, y: 0, opacity: 1 }}
      exit={variants[direction]}
      transition={{
        duration: 0.5,
        ease: "easeInOut"
      }}
    >
      {children}
    </motion.div>
  );
};

// Scroll Reveal Animation
interface ScrollRevealProps {
  children: React.ReactNode;
  direction?: 'up' | 'down' | 'left' | 'right';
  delay?: number;
  threshold?: number;
}

export const ScrollReveal: React.FC<ScrollRevealProps> = ({
  children,
  direction = 'up',
  delay = 0,
  threshold = 0.1
}) => {
  const ref = useRef(null);
  const isInView = useInView(ref, { once: true, amount: threshold });
  
  const variants = {
    up: { y: 50, opacity: 0 },
    down: { y: -50, opacity: 0 },
    left: { x: 50, opacity: 0 },
    right: { x: -50, opacity: 0 }
  };

  return (
    <motion.div
      ref={ref}
      initial={variants[direction]}
      animate={isInView ? { x: 0, y: 0, opacity: 1 } : variants[direction]}
      transition={{
        duration: 0.6,
        delay,
        ease: "easeOut"
      }}
    >
      {children}
    </motion.div>
  );
};

// Cannabis Leaf Animation
export const AnimatedCannabisLeaf: React.FC<{ size?: number; color?: string }> = ({
  size = 60,
  color = '#10b981'
}) => {
  return (
    <motion.svg
      width={size}
      height={size}
      viewBox="0 0 100 100"
      initial={{ scale: 0, rotate: -45 }}
      animate={{ 
        scale: 1, 
        rotate: 0,
        y: [0, -5, 0]
      }}
      transition={{
        scale: { duration: 0.8, ease: "backOut" },
        rotate: { duration: 0.8, ease: "backOut" },
        y: { duration: 2, repeat: Infinity, ease: "easeInOut" }
      }}
    >
      <path
        d="M50 10 C35 20, 20 40, 25 60 C30 45, 45 35, 50 40 C55 35, 70 45, 75 60 C80 40, 65 20, 50 10"
        fill={color}
        opacity={0.8}
      />
      <path
        d="M50 40 C35 50, 20 70, 25 90 C30 75, 45 65, 50 70 C55 65, 70 75, 75 90 C80 70, 65 50, 50 40"
        fill={color}
        opacity={0.6}
      />
      <path
        d="M50 20 L50 80"
        stroke={color}
        strokeWidth="2"
        fill="none"
      />
    </motion.svg>
  );
};

// Floating Particles Effect
export const FloatingParticles: React.FC<{ count?: number; color?: string }> = ({
  count = 20,
  color = '#10b981'
}) => {
  const [particles, setParticles] = useState<Array<{
    id: number;
    x: number;
    y: number;
    size: number;
    delay: number;
  }>>([]);

  useEffect(() => {
    const newParticles = Array.from({ length: count }, (_, i) => ({
      id: i,
      x: Math.random() * 100,
      y: Math.random() * 100,
      size: Math.random() * 6 + 2,
      delay: Math.random() * 2
    }));
    setParticles(newParticles);
  }, [count]);

  return (
    <div className="absolute inset-0 overflow-hidden pointer-events-none">
      {particles.map((particle) => (
        <motion.div
          key={particle.id}
          className="absolute rounded-full opacity-20"
          style={{
            left: `${particle.x}%`,
            top: `${particle.y}%`,
            width: particle.size,
            height: particle.size,
            backgroundColor: color
          }}
          animate={{
            y: [0, -30, 0],
            x: [0, Math.random() * 20 - 10, 0],
            opacity: [0.2, 0.8, 0.2],
            scale: [1, 1.2, 1]
          }}
          transition={{
            duration: 4 + Math.random() * 2,
            repeat: Infinity,
            delay: particle.delay,
            ease: "easeInOut"
          }}
        />
      ))}
    </div>
  );
};

// Morphing Button
interface MorphingButtonProps {
  children: React.ReactNode;
  onClick: () => void;
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger';
  size?: 'sm' | 'md' | 'lg';
  loading?: boolean;
  disabled?: boolean;
}

export const MorphingButton: React.FC<MorphingButtonProps> = ({
  children,
  onClick,
  variant = 'primary',
  size = 'md',
  loading = false,
  disabled = false
}) => {
  const [isPressed, setIsPressed] = useState(false);

  const variants = {
    primary: 'bg-gradient-to-r from-green-500 to-emerald-600 text-white',
    secondary: 'bg-gradient-to-r from-gray-500 to-gray-600 text-white',
    success: 'bg-gradient-to-r from-green-400 to-green-500 text-white',
    warning: 'bg-gradient-to-r from-yellow-500 to-orange-500 text-white',
    danger: 'bg-gradient-to-r from-red-500 to-red-600 text-white'
  };

  const sizes = {
    sm: 'px-4 py-2 text-sm',
    md: 'px-6 py-3 text-base',
    lg: 'px-8 py-4 text-lg'
  };

  return (
    <motion.button
      className={`
        relative overflow-hidden rounded-xl font-semibold
        ${variants[variant]} ${sizes[size]}
        ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
      `}
      onClick={!disabled && !loading ? onClick : undefined}
      onMouseDown={() => setIsPressed(true)}
      onMouseUp={() => setIsPressed(false)}
      onMouseLeave={() => setIsPressed(false)}
      whileHover={{ scale: disabled ? 1 : 1.05 }}
      whileTap={{ scale: disabled ? 1 : 0.95 }}
      animate={{
        boxShadow: isPressed 
          ? '0 4px 12px rgba(0,0,0,0.15)' 
          : '0 8px 25px rgba(0,0,0,0.15)'
      }}
      transition={{ type: 'spring', stiffness: 400, damping: 30 }}
      disabled={disabled || loading}
    >
      {/* Background ripple effect */}
      <motion.div
        className="absolute inset-0 bg-white/20"
        initial={{ scale: 0, opacity: 0 }}
        animate={{ 
          scale: isPressed ? 1 : 0, 
          opacity: isPressed ? 1 : 0 
        }}
        transition={{ duration: 0.2 }}
      />

      {/* Content */}
      <div className="relative z-10 flex items-center justify-center space-x-2">
        {loading ? <LoadingSpinner size={20} color="white" /> : children}
      </div>

      {/* Shine effect */}
      <motion.div
        className="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent -skew-x-12"
        initial={{ x: '-100%' }}
        animate={{ x: '100%' }}
        transition={{
          duration: 2,
          repeat: Infinity,
          repeatDelay: 3,
          ease: 'easeInOut'
        }}
      />
    </motion.button>
  );
};

// Toast Notifications
interface ToastProps {
  message: string;
  type: 'success' | 'error' | 'info' | 'warning';
  onClose: () => void;
}

export const AnimatedToast: React.FC<ToastProps> = ({ message, type, onClose }) => {
  const icons = {
    success: <CheckCircleIcon className="w-5 h-5" />,
    error: <ExclamationCircleIcon className="w-5 h-5" />,
    info: <InformationCircleIcon className="w-5 h-5" />,
    warning: <ExclamationCircleIcon className="w-5 h-5" />
  };

  const colors = {
    success: 'bg-green-500',
    error: 'bg-red-500',
    info: 'bg-blue-500',
    warning: 'bg-yellow-500'
  };

  useEffect(() => {
    const timer = setTimeout(onClose, 4000);
    return () => clearTimeout(timer);
  }, [onClose]);

  return (
    <motion.div
      initial={{ opacity: 0, x: 300, scale: 0.8 }}
      animate={{ opacity: 1, x: 0, scale: 1 }}
      exit={{ opacity: 0, x: 300, scale: 0.8 }}
      transition={{ duration: 0.4, ease: "backOut" }}
      className={`
        fixed top-4 right-4 z-50 flex items-center space-x-3
        ${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg
        min-w-96 max-w-md
      `}
    >
      <motion.div
        initial={{ rotate: -90, scale: 0 }}
        animate={{ rotate: 0, scale: 1 }}
        transition={{ delay: 0.2, duration: 0.3, ease: "backOut" }}
      >
        {icons[type]}
      </motion.div>
      
      <div className="flex-1">
        <p className="text-sm font-medium">{message}</p>
      </div>
      
      <button
        onClick={onClose}
        className="text-white/80 hover:text-white transition-colors"
      >
        ×
      </button>

      {/* Progress bar */}
      <motion.div
        className="absolute bottom-0 left-0 h-1 bg-white/30"
        initial={{ width: '100%' }}
        animate={{ width: '0%' }}
        transition={{ duration: 4, ease: 'linear' }}
      />
    </motion.div>
  );
};

// Stagger Animation Container
interface StaggerContainerProps {
  children: React.ReactNode;
  staggerDelay?: number;
  direction?: 'up' | 'down' | 'left' | 'right';
}

export const StaggerContainer: React.FC<StaggerContainerProps> = ({
  children,
  staggerDelay = 0.1,
  direction = 'up'
}) => {
  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: staggerDelay
      }
    }
  };

  const itemVariants = {
    hidden: {
      opacity: 0,
      ...(direction === 'up' && { y: 20 }),
      ...(direction === 'down' && { y: -20 }),
      ...(direction === 'left' && { x: 20 }),
      ...(direction === 'right' && { x: -20 })
    },
    visible: {
      opacity: 1,
      x: 0,
      y: 0,
      transition: {
        duration: 0.4,
        ease: "easeOut"
      }
    }
  };

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
    >
      {React.Children.map(children, (child, index) => (
        <motion.div key={index} variants={itemVariants}>
          {child}
        </motion.div>
      ))}
    </motion.div>
  );
};

// Pulse Animation
export const PulseEffect: React.FC<{ 
  children: React.ReactNode;
  color?: string;
  intensity?: number;
}> = ({ 
  children, 
  color = '#10b981',
  intensity = 0.2 
}) => {
  return (
    <motion.div
      className="relative"
      animate={{
        boxShadow: [
          `0 0 0 0 ${color}${Math.floor(intensity * 255).toString(16).padStart(2, '0')}`,
          `0 0 0 10px ${color}00`,
          `0 0 0 0 ${color}00`
        ]
      }}
      transition={{
        duration: 2,
        repeat: Infinity,
        ease: "easeInOut"
      }}
    >
      {children}
    </motion.div>
  );
};

// Demo component showcasing all animations
export const AnimationShowcase: React.FC = () => {
  const [toasts, setToasts] = useState<Array<{
    id: number;
    message: string;
    type: 'success' | 'error' | 'info' | 'warning';
  }>>([]);

  const addToast = (message: string, type: 'success' | 'error' | 'info' | 'warning') => {
    const id = Date.now();
    setToasts(prev => [...prev, { id, message, type }]);
  };

  const removeToast = (id: number) => {
    setToasts(prev => prev.filter(toast => toast.id !== id));
  };

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-12">
      <ScrollReveal>
        <div className="text-center mb-12">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            Animated Components Showcase
          </h1>
          <p className="text-gray-600 text-lg">
            Rich animations and micro-interactions for SmokeoutNYC
          </p>
        </div>
      </ScrollReveal>

      {/* Loading Animations */}
      <ScrollReveal delay={0.1}>
        <section className="bg-white rounded-2xl shadow-lg p-8">
          <h2 className="text-2xl font-semibold mb-6">Loading Animations</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div className="text-center space-y-4">
              <h3 className="font-medium">Spinner</h3>
              <LoadingSpinner />
            </div>
            <div className="text-center space-y-4">
              <h3 className="font-medium">Dots</h3>
              <LoadingDots />
            </div>
            <div className="text-center space-y-4">
              <h3 className="font-medium">Wave</h3>
              <LoadingWave />
            </div>
          </div>
        </section>
      </ScrollReveal>

      {/* Interactive Elements */}
      <ScrollReveal delay={0.2}>
        <section className="bg-white rounded-2xl shadow-lg p-8 relative overflow-hidden">
          <FloatingParticles count={15} />
          <h2 className="text-2xl font-semibold mb-6">Interactive Components</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div className="text-center space-y-4">
              <h3 className="font-medium">Cannabis Animation</h3>
              <div className="flex justify-center">
                <AnimatedCannabisLeaf />
              </div>
            </div>
            <div className="text-center space-y-4">
              <h3 className="font-medium">Morphing Buttons</h3>
              <div className="space-y-2">
                <MorphingButton 
                  onClick={() => addToast('Success!', 'success')}
                  variant="primary"
                >
                  Click me!
                </MorphingButton>
                <MorphingButton 
                  onClick={() => addToast('Warning message', 'warning')}
                  variant="warning"
                  size="sm"
                >
                  Warning
                </MorphingButton>
              </div>
            </div>
            <div className="text-center space-y-4">
              <h3 className="font-medium">Pulse Effect</h3>
              <div className="flex justify-center">
                <PulseEffect>
                  <div className="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center text-white">
                    <SparklesIcon className="w-8 h-8" />
                  </div>
                </PulseEffect>
              </div>
            </div>
          </div>
        </section>
      </ScrollReveal>

      {/* Stagger Animation */}
      <ScrollReveal delay={0.3}>
        <section className="bg-white rounded-2xl shadow-lg p-8">
          <h2 className="text-2xl font-semibold mb-6">Stagger Animations</h2>
          <StaggerContainer staggerDelay={0.1}>
            {[1, 2, 3, 4, 5, 6].map((num) => (
              <div key={num} className="bg-gray-100 rounded-lg p-4 mb-3">
                <div className="flex items-center space-x-3">
                  <div className="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-bold">
                    {num}
                  </div>
                  <div>
                    <h4 className="font-medium">Animated Item {num}</h4>
                    <p className="text-gray-600 text-sm">This item animates in sequence</p>
                  </div>
                </div>
              </div>
            ))}
          </StaggerContainer>
        </section>
      </ScrollReveal>

      {/* Toast Notifications */}
      <AnimatePresence>
        {toasts.map((toast) => (
          <AnimatedToast
            key={toast.id}
            message={toast.message}
            type={toast.type}
            onClose={() => removeToast(toast.id)}
          />
        ))}
      </AnimatePresence>
    </div>
  );
};