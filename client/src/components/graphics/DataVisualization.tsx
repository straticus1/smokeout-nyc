import React, { useState, useEffect, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  ChartBarIcon, 
  MapIcon, 
  TrendingUpIcon, 
  TrendingDownIcon,
  CalendarIcon,
  UsersIcon,
  CurrencyDollarIcon,
  BuildingStorefrontIcon
} from '@heroicons/react/24/outline';

// Custom SVG Chart Components
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
}

export const LineChart: React.FC<LineChartProps> = ({
  data,
  width = 400,
  height = 200,
  color = '#10b981',
  animated = true
}) => {
  const [animationProgress, setAnimationProgress] = useState(0);

  useEffect(() => {
    if (animated) {
      const timer = setTimeout(() => setAnimationProgress(1), 100);
      return () => clearTimeout(timer);
    } else {
      setAnimationProgress(1);
    }
  }, [animated]);

  const { path, points, maxValue, minValue } = useMemo(() => {
    if (data.length === 0) return { path: '', points: [], maxValue: 0, minValue: 0 };

    const values = data.map(d => d.value);
    const maxVal = Math.max(...values);
    const minVal = Math.min(...values);
    const range = maxVal - minVal || 1;

    const pointsArray = data.map((d, i) => {
      const x = (i / (data.length - 1)) * width;
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
  }, [data, width, height]);

  return (
    <div className="relative">
      <svg width={width} height={height} className="overflow-visible">
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
            transition={{ duration: 1.5, ease: "easeInOut" }}
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
            transition={{ duration: 1.5, ease: "easeInOut" }}
          />
        )}

        {/* Data Points */}
        {points.map((point, i) => (
          <motion.g key={i}>
            <motion.circle
              cx={point.x}
              cy={point.y}
              r="4"
              fill={color}
              initial={{ scale: 0, opacity: 0 }}
              animate={{ 
                scale: animationProgress, 
                opacity: animationProgress 
              }}
              transition={{ 
                delay: (i / points.length) * 1.5,
                duration: 0.3 
              }}
              className="hover:r-6 transition-all cursor-pointer"
            />
            <motion.circle
              cx={point.x}
              cy={point.y}
              r="8"
              fill={color}
              fillOpacity="0.2"
              initial={{ scale: 0 }}
              animate={{ scale: animationProgress }}
              transition={{ 
                delay: (i / points.length) * 1.5,
                duration: 0.3 
              }}
              className="hover:r-12 transition-all"
            />
          </motion.g>
        ))}
      </svg>

      {/* Y-axis Labels */}
      <div className="absolute -left-12 top-0 h-full flex flex-col justify-between text-xs text-gray-500">
        <span>{maxValue.toLocaleString()}</span>
        <span>{Math.round((maxValue + minValue) / 2).toLocaleString()}</span>
        <span>{minValue.toLocaleString()}</span>
      </div>

      {/* X-axis Labels */}
      <div className="absolute -bottom-6 left-0 w-full flex justify-between text-xs text-gray-500">
        {data.map((d, i) => (
          <span key={i} className={i % Math.ceil(data.length / 4) === 0 ? '' : 'hidden'}>
            {new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
          </span>
        ))}
      </div>
    </div>
  );
};

// Donut Chart Component
interface DonutChartProps {
  data: Array<{
    label: string;
    value: number;
    color: string;
  }>;
  size?: number;
  innerRadius?: number;
}

export const DonutChart: React.FC<DonutChartProps> = ({
  data,
  size = 200,
  innerRadius = 0.6
}) => {
  const [animationProgress, setAnimationProgress] = useState(0);

  useEffect(() => {
    const timer = setTimeout(() => setAnimationProgress(1), 200);
    return () => clearTimeout(timer);
  }, []);

  const total = data.reduce((sum, item) => sum + item.value, 0);
  const radius = size / 2 - 10;
  const innerR = radius * innerRadius;

  let cumulativeValue = 0;

  return (
    <div className="relative inline-block">
      <svg width={size} height={size}>
        {data.map((item, index) => {
          const startAngle = (cumulativeValue / total) * 2 * Math.PI - Math.PI / 2;
          const endAngle = ((cumulativeValue + item.value) / total) * 2 * Math.PI - Math.PI / 2;
          cumulativeValue += item.value;

          const largeArcFlag = endAngle - startAngle <= Math.PI ? "0" : "1";

          const x1 = size / 2 + radius * Math.cos(startAngle);
          const y1 = size / 2 + radius * Math.sin(startAngle);
          const x2 = size / 2 + radius * Math.cos(endAngle);
          const y2 = size / 2 + radius * Math.sin(endAngle);

          const x3 = size / 2 + innerR * Math.cos(endAngle);
          const y3 = size / 2 + innerR * Math.sin(endAngle);
          const x4 = size / 2 + innerR * Math.cos(startAngle);
          const y4 = size / 2 + innerR * Math.sin(startAngle);

          const pathData = [
            `M ${x1} ${y1}`,
            `A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2}`,
            `L ${x3} ${y3}`,
            `A ${innerR} ${innerR} 0 ${largeArcFlag} 0 ${x4} ${y4}`,
            'Z'
          ].join(' ');

          return (
            <motion.path
              key={index}
              d={pathData}
              fill={item.color}
              initial={{ pathLength: 0, opacity: 0 }}
              animate={{ 
                pathLength: animationProgress, 
                opacity: animationProgress 
              }}
              transition={{ 
                delay: index * 0.2,
                duration: 0.8,
                ease: "easeInOut"
              }}
              className="hover:opacity-80 transition-opacity cursor-pointer"
            />
          );
        })}
      </svg>

      {/* Center Text */}
      <div className="absolute inset-0 flex items-center justify-center">
        <div className="text-center">
          <div className="text-2xl font-bold text-gray-900">{total.toLocaleString()}</div>
          <div className="text-sm text-gray-500">Total</div>
        </div>
      </div>

      {/* Legend */}
      <div className="mt-4 space-y-2">
        {data.map((item, index) => (
          <motion.div
            key={index}
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.1 + 0.5 }}
            className="flex items-center gap-3"
          >
            <div
              className="w-3 h-3 rounded-full"
              style={{ backgroundColor: item.color }}
            />
            <span className="text-sm text-gray-700 flex-1">{item.label}</span>
            <span className="text-sm font-semibold text-gray-900">
              {item.value} ({Math.round((item.value / total) * 100)}%)
            </span>
          </motion.div>
        ))}
      </div>
    </div>
  );
};

// Interactive Dashboard Component
interface DashboardMetric {
  title: string;
  value: string;
  change: number;
  icon: React.ReactNode;
  color: string;
  trend: Array<{ date: string; value: number }>;
}

export const InteractiveDashboard: React.FC = () => {
  const [selectedPeriod, setSelectedPeriod] = useState<'7d' | '30d' | '90d'>('30d');
  const [selectedMetric, setSelectedMetric] = useState<string>('stores');

  const metrics: DashboardMetric[] = [
    {
      title: 'Active Stores',
      value: '1,247',
      change: 12.5,
      icon: <BuildingStorefrontIcon className="w-6 h-6" />,
      color: '#3b82f6',
      trend: [
        { date: '2024-01-01', value: 1200 },
        { date: '2024-01-07', value: 1215 },
        { date: '2024-01-14', value: 1225 },
        { date: '2024-01-21', value: 1235 },
        { date: '2024-01-28', value: 1247 },
      ]
    },
    {
      title: 'Cannabis Score',
      value: '87.2',
      change: 3.2,
      icon: <TrendingUpIcon className="w-6 h-6" />,
      color: '#10b981',
      trend: [
        { date: '2024-01-01', value: 84.5 },
        { date: '2024-01-07', value: 85.1 },
        { date: '2024-01-14', value: 86.2 },
        { date: '2024-01-21', value: 86.8 },
        { date: '2024-01-28', value: 87.2 },
      ]
    },
    {
      title: 'Total Donations',
      value: '$125,840',
      change: -2.1,
      icon: <CurrencyDollarIcon className="w-6 h-6" />,
      color: '#8b5cf6',
      trend: [
        { date: '2024-01-01', value: 128500 },
        { date: '2024-01-07', value: 127200 },
        { date: '2024-01-14', value: 126800 },
        { date: '2024-01-21', value: 126100 },
        { date: '2024-01-28', value: 125840 },
      ]
    },
    {
      title: 'Active Users',
      value: '15,234',
      change: 8.7,
      icon: <UsersIcon className="w-6 h-6" />,
      color: '#f59e0b',
      trend: [
        { date: '2024-01-01', value: 14200 },
        { date: '2024-01-07', value: 14450 },
        { date: '2024-01-14', value: 14720 },
        { date: '2024-01-21', value: 14980 },
        { date: '2024-01-28', value: 15234 },
      ]
    }
  ];

  const storeStatusData = [
    { label: 'Open', value: 1247, color: '#10b981' },
    { label: 'Closed (Operation Smokeout)', value: 342, color: '#ef4444' },
    { label: 'Temporarily Closed', value: 89, color: '#f59e0b' },
    { label: 'Reopened', value: 156, color: '#6366f1' },
  ];

  const cannabisPolicyData = [
    { label: 'Pro-Cannabis Politicians', value: 87, color: '#10b981' },
    { label: 'Anti-Cannabis Politicians', value: 23, color: '#ef4444' },
    { label: 'Neutral/Unknown', value: 145, color: '#6b7280' },
  ];

  const selectedMetricData = metrics.find(m => 
    m.title.toLowerCase().includes(selectedMetric)
  ) || metrics[0];

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-8">
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

      {/* Period Selector */}
      <div className="flex justify-between items-center">
        <h2 className="text-xl font-semibold text-gray-900">Key Metrics</h2>
        <div className="flex space-x-2">
          {['7d', '30d', '90d'].map((period) => (
            <button
              key={period}
              onClick={() => setSelectedPeriod(period as any)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                selectedPeriod === period
                  ? 'bg-green-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {period.toUpperCase()}
            </button>
          ))}
        </div>
      </div>

      {/* Metrics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {metrics.map((metric, index) => (
          <motion.div
            key={metric.title}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            onClick={() => setSelectedMetric(metric.title.split(' ')[0].toLowerCase())}
            className={`bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all cursor-pointer ${
              selectedMetricData.title === metric.title ? 'ring-2 ring-green-500' : ''
            }`}
          >
            <div className="flex items-center justify-between mb-4">
              <div 
                className="p-3 rounded-xl text-white"
                style={{ backgroundColor: metric.color }}
              >
                {metric.icon}
              </div>
              <div className={`text-sm font-medium px-2 py-1 rounded-full ${
                metric.change > 0 
                  ? 'text-green-600 bg-green-100' 
                  : 'text-red-600 bg-red-100'
              }`}>
                {metric.change > 0 ? (
                  <TrendingUpIcon className="w-4 h-4 inline mr-1" />
                ) : (
                  <TrendingDownIcon className="w-4 h-4 inline mr-1" />
                )}
                {Math.abs(metric.change)}%
              </div>
            </div>
            
            <div className="space-y-1">
              <div className="text-2xl font-bold text-gray-900">{metric.value}</div>
              <div className="text-gray-600 text-sm">{metric.title}</div>
            </div>

            {/* Mini trend chart */}
            <div className="mt-4">
              <LineChart
                data={metric.trend}
                width={200}
                height={40}
                color={metric.color}
                animated={false}
              />
            </div>
          </motion.div>
        ))}
      </div>

      {/* Main Chart */}
      <div className="bg-white rounded-2xl shadow-lg p-8">
        <div className="flex items-center justify-between mb-6">
          <h3 className="text-xl font-semibold text-gray-900">
            {selectedMetricData.title} Trend
          </h3>
          <div className="flex items-center space-x-2 text-sm text-gray-500">
            <CalendarIcon className="w-4 h-4" />
            <span>Last {selectedPeriod.toUpperCase()}</span>
          </div>
        </div>
        
        <div className="w-full">
          <LineChart
            data={selectedMetricData.trend}
            width={800}
            height={300}
            color={selectedMetricData.color}
            animated={true}
          />
        </div>
      </div>

      {/* Charts Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Store Status Chart */}
        <motion.div
          initial={{ opacity: 0, x: -20 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-white rounded-2xl shadow-lg p-8"
        >
          <h3 className="text-xl font-semibold text-gray-900 mb-6">
            Store Status Distribution
          </h3>
          <div className="flex justify-center">
            <DonutChart data={storeStatusData} size={300} />
          </div>
        </motion.div>

        {/* Cannabis Policy Chart */}
        <motion.div
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-white rounded-2xl shadow-lg p-8"
        >
          <h3 className="text-xl font-semibold text-gray-900 mb-6">
            Political Cannabis Stance
          </h3>
          <div className="flex justify-center">
            <DonutChart data={cannabisPolicyData} size={300} />
          </div>
        </motion.div>
      </div>

      {/* Real-time Updates */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl shadow-lg p-8 text-white"
      >
        <h3 className="text-xl font-semibold mb-4">Real-Time Updates</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-white/20 backdrop-blur-sm rounded-lg p-4">
            <div className="text-2xl font-bold">Live</div>
            <div className="text-sm opacity-90">System Status</div>
          </div>
          <div className="bg-white/20 backdrop-blur-sm rounded-lg p-4">
            <div className="text-2xl font-bold">24/7</div>
            <div className="text-sm opacity-90">Monitoring</div>
          </div>
          <div className="bg-white/20 backdrop-blur-sm rounded-lg p-4">
            <div className="text-2xl font-bold">99.9%</div>
            <div className="text-sm opacity-90">Uptime</div>
          </div>
        </div>
      </motion.div>
    </div>
  );
};