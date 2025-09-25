import React, { useState, useEffect, useMemo, useCallback } from 'react';
import { motion, AnimatePresence, useReducedMotion } from 'framer-motion';
import { 
  ChartBarIcon, 
  MapIcon, 
  TrendingUpIcon, 
  TrendingDownIcon,
  CalendarIcon,
  UsersIcon,
  CurrencyDollarIcon,
  BuildingStorefrontIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';

// Utility functions for safety and accessibility
const safeNumber = (value: unknown): number => {
  const num = Number(value);
  return isNaN(num) || !isFinite(num) ? 0 : num;
};

const formatNumber = (value: number): string => {
  return new Intl.NumberFormat('en-US').format(Math.round(safeNumber(value)));
};

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(safeNumber(value));
};

// Enhanced Line Chart with Accessibility
interface LineChartProps {
  data: Array<{
    date: string;
    value: number;
    label?: string;
  }>;
  width?: number;
  height?: number;
  color?: string;
  animated?: boolean;
  title?: string;
  description?: string;
}

export const AccessibleLineChart: React.FC<LineChartProps> = ({
  data = [],
  width = 400,
  height = 200,
  color = '#10b981',
  animated = true,
  title = 'Line Chart',
  description
}) => {
  const [animationProgress, setAnimationProgress] = useState(0);
  const [focusedPoint, setFocusedPoint] = useState<number>(-1);
  const prefersReducedMotion = useReducedMotion();
  
  // Safely process data
  const validData = useMemo(() => {
    return data
      .filter(d => d && typeof d === 'object' && d.date && typeof d.value === 'number')
      .map(d => ({
        ...d,
        value: safeNumber(d.value),
        date: String(d.date)
      }));
  }, [data]);

  useEffect(() => {
    if (animated && !prefersReducedMotion) {
      const timer = setTimeout(() => setAnimationProgress(1), 100);
      return () => clearTimeout(timer);
    } else {
      setAnimationProgress(1);
    }
  }, [animated, prefersReducedMotion]);

  const { path, points, maxValue, minValue } = useMemo(() => {
    if (validData.length === 0) {
      return { path: '', points: [], maxValue: 0, minValue: 0 };
    }

    const values = validData.map(d => d.value);
    const maxVal = Math.max(...values);
    const minVal = Math.min(...values);
    const range = Math.max(maxVal - minVal, 1); // Prevent division by zero

    const pointsArray = validData.map((d, i) => {
      const x = validData.length > 1 ? (i / (validData.length - 1)) * width : width / 2;
      const y = height - ((d.value - minVal) / range) * height;
      return { x, y, value: d.value, date: d.date };
    });

    const pathString = pointsArray
      .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x},${p.y}`)
      .join(' ');

    return {
      path: pathString,
      points: pointsArray,
      maxValue: maxVal,
      minValue: minVal
    };
  }, [validData, width, height]);

  const handleKeyDown = useCallback((event: React.KeyboardEvent, pointIndex: number) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      setFocusedPoint(pointIndex === focusedPoint ? -1 : pointIndex);
    }
  }, [focusedPoint]);

  if (validData.length === 0) {
    return (
      <div 
        className="flex items-center justify-center p-8 bg-gray-50 rounded-lg border"
        role="img"
        aria-label="Chart data unavailable"
      >
        <div className="text-center text-gray-500">
          <ChartBarIcon className="w-12 h-12 mx-auto mb-2" />
          <p>No data available</p>
        </div>
      </div>
    );
  }

  return (
    <div 
      className="relative"
      role="img"
      aria-labelledby="chart-title"
      aria-describedby="chart-description"
    >
      {title && (
        <h3 id="chart-title" className="text-lg font-semibold mb-2 sr-only">
          {title}
        </h3>
      )}
      
      {description && (
        <p id="chart-description" className="text-sm text-gray-600 mb-4 sr-only">
          {description}
        </p>
      )}

      <svg 
        width={width} 
        height={height} 
        className="overflow-visible"
        role="presentation"
        aria-hidden="true"
      >
        {/* Grid Lines */}
        <defs>
          <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#e5e7eb" strokeWidth="1" opacity="0.3"/>
          </pattern>
        </defs>
        <rect width={width} height={height} fill="url(#grid)" />

        {/* Gradient Fill */}
        <defs>
          <linearGradient id="chartGradient" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stopColor={color} stopOpacity="0.3" />
            <stop offset="100%" stopColor={color} stopOpacity="0.1" />
          </linearGradient>
        </defs>

        {/* Area Fill */}
        {path && (
          <motion.path
            d={`${path} L ${width},${height} L 0,${height} Z`}
            fill="url(#chartGradient)"
            initial={{ pathLength: 0, opacity: 0 }}
            animate={{ 
              pathLength: animationProgress, 
              opacity: animationProgress 
            }}
            transition={{ duration: prefersReducedMotion ? 0 : 1.5, ease: "easeInOut" }}
          />
        )}

        {/* Line */}
        {path && (
          <motion.path
            d={path}
            fill="none"
            stroke={color}
            strokeWidth="3"
            strokeLinecap="round"
            initial={{ pathLength: 0 }}
            animate={{ pathLength: animationProgress }}
            transition={{ duration: prefersReducedMotion ? 0 : 1.5, ease: "easeInOut" }}
          />
        )}

        {/* Data Points */}
        {points.map((point, i) => (
          <g key={i}>
            <motion.circle
              cx={point.x}
              cy={point.y}
              r="6"
              fill={color}
              initial={{ scale: 0, opacity: 0 }}
              animate={{ 
                scale: animationProgress, 
                opacity: animationProgress 
              }}
              transition={{ 
                delay: prefersReducedMotion ? 0 : (i / points.length) * 1.5,
                duration: prefersReducedMotion ? 0 : 0.3 
              }}
              className={`hover:r-8 transition-all cursor-pointer ${
                focusedPoint === i ? 'stroke-4 stroke-blue-500' : ''
              }`}
              tabIndex={0}
              role="button"
              aria-label={`Data point ${i + 1}: ${formatNumber(point.value)} on ${new Date(point.date).toLocaleDateString()}`}
              onKeyDown={(e) => handleKeyDown(e, i)}
              onClick={() => setFocusedPoint(focusedPoint === i ? -1 : i)}
              onFocus={() => setFocusedPoint(i)}
              onBlur={() => setFocusedPoint(-1)}
            />
            
            {/* Tooltip for focused point */}
            {focusedPoint === i && (
              <g>
                <rect
                  x={point.x - 40}
                  y={point.y - 35}
                  width="80"
                  height="25"
                  fill="rgba(0,0,0,0.8)"
                  rx="4"
                />
                <text
                  x={point.x}
                  y={point.y - 20}
                  textAnchor="middle"
                  className="text-xs fill-white"
                  role="tooltip"
                >
                  {formatNumber(point.value)}
                </text>
              </g>
            )}
          </g>
        ))}
      </svg>

      {/* Accessible Data Table */}
      <table className="sr-only" aria-label="Chart data in table format">
        <thead>
          <tr>
            <th>Date</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody>
          {validData.map((item, index) => (
            <tr key={index}>
              <td>{new Date(item.date).toLocaleDateString()}</td>
              <td>{formatNumber(item.value)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* Y-axis Labels */}
      <div 
        className="absolute -left-12 top-0 h-full flex flex-col justify-between text-xs text-gray-500"
        role="presentation"
        aria-hidden="true"
      >
        <span>{formatNumber(maxValue)}</span>
        <span>{formatNumber((maxValue + minValue) / 2)}</span>
        <span>{formatNumber(minValue)}</span>
      </div>

      {/* X-axis Labels */}
      <div 
        className="absolute -bottom-6 left-0 w-full flex justify-between text-xs text-gray-500"
        role="presentation"
        aria-hidden="true"
      >
        {validData.map((d, i) => (
          <span 
            key={i} 
            className={i % Math.ceil(validData.length / 4) === 0 ? '' : 'hidden'}
          >
            {new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
          </span>
        ))}
      </div>
    </div>
  );
};

// Accessible Animated Counter
interface AnimatedCounterProps {
  value: number;
  suffix?: string;
  prefix?: string;
  duration?: number;
  title?: string;
}

export const AccessibleAnimatedCounter: React.FC<AnimatedCounterProps> = ({
  value,
  suffix = '',
  prefix = '',
  duration = 2,
  title
}) => {
  const [displayValue, setDisplayValue] = useState(0);
  const [isComplete, setIsComplete] = useState(false);
  const prefersReducedMotion = useReducedMotion();
  const safeValue = safeNumber(value);

  useEffect(() => {
    if (prefersReducedMotion) {
      setDisplayValue(safeValue);
      setIsComplete(true);
      return;
    }

    let mounted = true;
    const startTime = Date.now();
    const endTime = startTime + duration * 1000;

    const updateCounter = () => {
      if (!mounted) return;

      const now = Date.now();
      const progress = Math.min((now - startTime) / (duration * 1000), 1);
      const easeOutQuart = 1 - Math.pow(1 - progress, 4);
      
      setDisplayValue(Math.floor(easeOutQuart * safeValue));

      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      } else {
        setIsComplete(true);
      }
    };

    updateCounter();

    return () => {
      mounted = false;
    };
  }, [safeValue, duration, prefersReducedMotion]);

  const formattedValue = `${prefix}${formatNumber(displayValue)}${suffix}`;

  return (
    <span 
      className="font-bold"
      aria-live={isComplete ? "off" : "polite"}
      aria-label={title ? `${title}: ${formattedValue}` : formattedValue}
      title={title}
    >
      {formattedValue}
    </span>
  );
};

// Accessible Dashboard Stats
interface DashboardStatsProps {
  stats: Array<{
    label: string;
    value: number;
    change: number;
    icon: React.ReactNode;
    color: string;
    description?: string;
  }>;
}

export const AccessibleDashboardStats: React.FC<DashboardStatsProps> = ({ stats = [] }) => {
  const prefersReducedMotion = useReducedMotion();

  const validStats = useMemo(() => {
    return stats.filter(stat => 
      stat && 
      typeof stat === 'object' && 
      stat.label && 
      typeof stat.value === 'number' && 
      typeof stat.change === 'number'
    ).map(stat => ({
      ...stat,
      value: safeNumber(stat.value),
      change: safeNumber(stat.change)
    }));
  }, [stats]);

  if (validStats.length === 0) {
    return (
      <div 
        className="text-center p-8 text-gray-500"
        role="status"
        aria-live="polite"
      >
        <InformationCircleIcon className="w-12 h-12 mx-auto mb-4" />
        <p>No statistics available</p>
      </div>
    );
  }

  return (
    <section 
      className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"
      role="region"
      aria-label="Dashboard Statistics"
    >
      {validStats.map((stat, index) => {
        const isPositiveChange = stat.change > 0;
        const changeIcon = isPositiveChange ? TrendingUpIcon : TrendingDownIcon;
        const changeColor = isPositiveChange ? 'text-green-600' : 'text-red-600';
        const changeBgColor = isPositiveChange ? 'bg-green-100' : 'bg-red-100';

        return (
          <motion.article
            key={`${stat.label}-${index}`}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ 
              delay: prefersReducedMotion ? 0 : index * 0.1,
              duration: prefersReducedMotion ? 0 : 0.5
            }}
            className="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-shadow duration-300 focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2"
            tabIndex={0}
            role="article"
            aria-labelledby={`stat-title-${index}`}
            aria-describedby={`stat-description-${index}`}
          >
            <header className="flex items-center justify-between mb-4">
              <div 
                className={`p-3 ${stat.color} rounded-xl`}
                aria-hidden="true"
              >
                {stat.icon}
              </div>
              <div className={`text-sm font-medium px-2 py-1 rounded-full flex items-center gap-1 ${changeBgColor} ${changeColor}`}>
                <span className="sr-only">
                  {isPositiveChange ? 'Increased by' : 'Decreased by'}
                </span>
                {React.createElement(changeIcon, { 
                  className: 'w-4 h-4', 
                  'aria-hidden': 'true' 
                })}
                <span aria-label={`${Math.abs(stat.change)} percent`}>
                  {isPositiveChange ? '+' : ''}{stat.change}%
                </span>
              </div>
            </header>
            
            <div className="space-y-1">
              <div 
                className="text-2xl font-bold text-gray-900"
                aria-live="polite"
              >
                <AccessibleAnimatedCounter 
                  value={stat.value}
                  title={stat.label}
                />
              </div>
              <h3 
                id={`stat-title-${index}`}
                className="text-gray-600 text-sm font-medium"
              >
                {stat.label}
              </h3>
              {stat.description && (
                <p 
                  id={`stat-description-${index}`}
                  className="text-xs text-gray-500 sr-only"
                >
                  {stat.description}
                </p>
              )}
            </div>
          </motion.article>
        );
      })}
    </section>
  );
};

// Main Accessible Dashboard
export const AccessibleDataVisualizationDashboard: React.FC = () => {
  const [selectedPeriod, setSelectedPeriod] = useState<'7d' | '30d' | '90d'>('30d');
  const [selectedMetric, setSelectedMetric] = useState<string>('stores');
  const [error, setError] = useState<string | null>(null);

  const sampleStats = useMemo(() => [
    {
      label: "Active Stores",
      value: 1247,
      change: 12.5,
      icon: React.createElement(BuildingStorefrontIcon, { className: "w-6 h-6 text-white" }),
      color: "bg-blue-500",
      description: "Number of currently operating cannabis dispensaries"
    },
    {
      label: "Cannabis Score",
      value: 87.2,
      change: 3.2,
      icon: React.createElement(TrendingUpIcon, { className: "w-6 h-6 text-white" }),
      color: "bg-green-500",
      description: "Overall cannabis policy friendliness rating"
    },
    {
      label: "Total Donations",
      value: 125840,
      change: -2.1,
      icon: React.createElement(CurrencyDollarIcon, { className: "w-6 h-6 text-white" }),
      color: "bg-purple-500",
      description: "Total political donations made through the platform"
    },
    {
      label: "Active Users",
      value: 15234,
      change: 8.7,
      icon: React.createElement(UsersIcon, { className: "w-6 h-6 text-white" }),
      color: "bg-orange-500",
      description: "Number of registered and active users"
    }
  ], []);

  const chartData = useMemo(() => [
    { date: '2024-01-01', value: 1200 },
    { date: '2024-01-07', value: 1215 },
    { date: '2024-01-14', value: 1225 },
    { date: '2024-01-21', value: 1235 },
    { date: '2024-01-28', value: 1247 },
  ], []);

  const handlePeriodChange = useCallback((period: '7d' | '30d' | '90d') => {
    setSelectedPeriod(period);
    // In a real app, this would trigger data fetching
  }, []);

  const handleError = useCallback((error: string) => {
    setError(error);
    console.error('Dashboard error:', error);
  }, []);

  return (
    <main className="max-w-7xl mx-auto p-6 space-y-8">
      <header>
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-8"
        >
          <h1 className="text-3xl font-bold text-gray-900 mb-4">
            Cannabis Analytics Dashboard
          </h1>
          <p className="text-gray-600">
            Real-time insights into cannabis industry data and political landscape
          </p>
        </motion.div>
      </header>

      {error && (
        <div 
          className="bg-red-50 border border-red-200 rounded-lg p-4"
          role="alert"
          aria-live="assertive"
        >
          <div className="flex items-center">
            <InformationCircleIcon className="w-5 h-5 text-red-400 mr-2" />
            <span className="text-red-800">{error}</span>
          </div>
        </div>
      )}

      {/* Period Selector with Accessibility */}
      <section aria-labelledby="period-selector-title">
        <div className="flex justify-between items-center mb-6">
          <h2 id="period-selector-title" className="text-xl font-semibold text-gray-900">
            Key Metrics
          </h2>
          <fieldset className="flex space-x-2">
            <legend className="sr-only">Select time period</legend>
            {(['7d', '30d', '90d'] as const).map((period) => (
              <label key={period} className="cursor-pointer">
                <input
                  type="radio"
                  name="period"
                  value={period}
                  checked={selectedPeriod === period}
                  onChange={() => handlePeriodChange(period)}
                  className="sr-only"
                />
                <span
                  className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors focus-within:ring-2 focus-within:ring-green-500 focus-within:ring-offset-2 ${
                    selectedPeriod === period
                      ? 'bg-green-600 text-white'
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                  }`}
                >
                  {period.toUpperCase()}
                </span>
              </label>
            ))}
          </fieldset>
        </div>
      </section>

      {/* Dashboard Stats */}
      <AccessibleDashboardStats stats={sampleStats} />

      {/* Main Chart */}
      <section 
        className="bg-white rounded-2xl shadow-lg p-8"
        aria-labelledby="main-chart-title"
      >
        <div className="flex items-center justify-between mb-6">
          <h3 id="main-chart-title" className="text-xl font-semibold text-gray-900">
            Store Growth Trend
          </h3>
          <div className="flex items-center space-x-2 text-sm text-gray-500">
            <CalendarIcon className="w-4 h-4" aria-hidden="true" />
            <span>Last {selectedPeriod.toUpperCase()}</span>
          </div>
        </div>
        
        <div className="w-full">
          <AccessibleLineChart
            data={chartData}
            width={800}
            height={300}
            color="#10b981"
            animated={true}
            title="Store count over time"
            description="Line chart showing the growth of cannabis stores over the selected time period"
          />
        </div>
      </section>

      {/* Skip Link for Keyboard Users */}
      <a 
        href="#main-content"
        className="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-blue-600 text-white px-4 py-2 rounded-lg z-50"
      >
        Skip to main content
      </a>
    </main>
  );
};