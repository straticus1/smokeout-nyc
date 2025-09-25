import React, { useState, useEffect, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  MapIcon,
  BuildingStorefrontIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClockIcon,
  TrendingUpIcon,
  TrendingDownIcon,
  EyeIcon,
  FunnelIcon,
  CalendarDaysIcon,
  ChartBarIcon,
  GlobeAltIcon
} from '@heroicons/react/24/outline';

// Interactive Heatmap Component
interface HeatmapData {
  borough: string;
  closures: number;
  active: number;
  enforcement: number;
  coordinates: [number, number];
}

interface HeatmapProps {
  data: HeatmapData[];
  selectedMetric: 'closures' | 'active' | 'enforcement';
  onMetricChange: (metric: 'closures' | 'active' | 'enforcement') => void;
}

export const InteractiveHeatmap: React.FC<HeatmapProps> = ({
  data,
  selectedMetric,
  onMetricChange
}) => {
  const [hoveredBorough, setHoveredBorough] = useState<string | null>(null);

  const maxValue = useMemo(() => 
    Math.max(...data.map(d => d[selectedMetric])), 
    [data, selectedMetric]
  );

  const getIntensity = (value: number) => value / maxValue;
  
  const getColor = (intensity: number) => {
    switch (selectedMetric) {
      case 'closures':
        return `rgba(239, 68, 68, ${0.2 + intensity * 0.8})`;
      case 'active':
        return `rgba(16, 185, 129, ${0.2 + intensity * 0.8})`;
      case 'enforcement':
        return `rgba(245, 158, 11, ${0.2 + intensity * 0.8})`;
      default:
        return `rgba(107, 114, 128, ${0.2 + intensity * 0.8})`;
    }
  };

  return (
    <div className="bg-white rounded-2xl shadow-lg p-6">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-semibold text-gray-900">NYC Cannabis Store Heatmap</h3>
        <div className="flex space-x-2">
          {[
            { key: 'active', label: 'Active Stores', color: 'bg-green-500' },
            { key: 'closures', label: 'Closures', color: 'bg-red-500' },
            { key: 'enforcement', label: 'Enforcement', color: 'bg-yellow-500' }
          ].map((metric) => (
            <button
              key={metric.key}
              onClick={() => onMetricChange(metric.key as any)}
              className={`flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                selectedMetric === metric.key
                  ? 'text-white shadow-lg'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              } ${selectedMetric === metric.key ? metric.color : ''}`}
            >
              <div className={`w-3 h-3 rounded-full ${metric.color}`}></div>
              <span>{metric.label}</span>
            </button>
          ))}
        </div>
      </div>

      {/* SVG Heatmap */}
      <div className="relative">
        <svg width="800" height="500" viewBox="0 0 800 500" className="w-full border rounded-lg">
          <defs>
            <pattern id="gridPattern" width="50" height="50" patternUnits="userSpaceOnUse">
              <path d="M 50 0 L 0 0 0 50" fill="none" stroke="#e5e7eb" strokeWidth="1" opacity="0.3"/>
            </pattern>
          </defs>
          
          {/* Background Grid */}
          <rect width="800" height="500" fill="url(#gridPattern)" />

          {/* Borough Areas */}
          {data.map((borough, index) => {
            const intensity = getIntensity(borough[selectedMetric]);
            const [x, y] = borough.coordinates;
            
            return (
              <motion.g key={borough.borough}>
                {/* Borough Shape (simplified rectangles for demo) */}
                <motion.rect
                  x={x - 60}
                  y={y - 40}
                  width={120}
                  height={80}
                  fill={getColor(intensity)}
                  stroke={hoveredBorough === borough.borough ? '#374151' : 'transparent'}
                  strokeWidth={2}
                  rx={8}
                  onMouseEnter={() => setHoveredBorough(borough.borough)}
                  onMouseLeave={() => setHoveredBorough(null)}
                  className="cursor-pointer"
                  whileHover={{ scale: 1.05 }}
                  initial={{ opacity: 0, scale: 0.8 }}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ delay: index * 0.1 }}
                />
                
                {/* Borough Label */}
                <text
                  x={x}
                  y={y - 10}
                  textAnchor="middle"
                  className="text-sm font-semibold fill-gray-800"
                >
                  {borough.borough}
                </text>
                
                {/* Metric Value */}
                <text
                  x={x}
                  y={y + 10}
                  textAnchor="middle"
                  className="text-lg font-bold fill-gray-900"
                >
                  {borough[selectedMetric]}
                </text>
                
                {/* Hover Info */}
                <AnimatePresence>
                  {hoveredBorough === borough.borough && (
                    <motion.g
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: 10 }}
                    >
                      <rect
                        x={x - 50}
                        y={y + 25}
                        width={100}
                        height={60}
                        fill="rgba(0,0,0,0.8)"
                        rx={4}
                      />
                      <text x={x} y={y + 40} textAnchor="middle" className="text-xs fill-white">
                        Active: {borough.active}
                      </text>
                      <text x={x} y={y + 52} textAnchor="middle" className="text-xs fill-white">
                        Closed: {borough.closures}
                      </text>
                      <text x={x} y={y + 64} textAnchor="middle" className="text-xs fill-white">
                        Enforce: {borough.enforcement}
                      </text>
                    </motion.g>
                  )}
                </AnimatePresence>
              </motion.g>
            );
          })}
        </svg>

        {/* Legend */}
        <div className="absolute bottom-4 left-4 bg-white/90 backdrop-blur-sm rounded-lg p-3">
          <div className="text-xs font-medium mb-2">Intensity Scale</div>
          <div className="flex items-center space-x-2">
            <span className="text-xs">Low</span>
            <div className="flex space-x-1">
              {[0.2, 0.4, 0.6, 0.8, 1.0].map((intensity, i) => (
                <div
                  key={i}
                  className="w-4 h-4 rounded"
                  style={{ backgroundColor: getColor(intensity) }}
                />
              ))}
            </div>
            <span className="text-xs">High</span>
          </div>
        </div>
      </div>
    </div>
  );
};

// Timeline Component for Closure Tracking
interface TimelineEvent {
  date: string;
  type: 'closure' | 'reopening' | 'enforcement' | 'news';
  title: string;
  description: string;
  location: string;
  severity?: 'low' | 'medium' | 'high';
}

interface TimelineProps {
  events: TimelineEvent[];
  selectedDateRange: [Date, Date];
  onDateRangeChange: (range: [Date, Date]) => void;
}

export const ClosureTimeline: React.FC<TimelineProps> = ({
  events,
  selectedDateRange,
  onDateRangeChange
}) => {
  const [filter, setFilter] = useState<string>('all');

  const filteredEvents = useMemo(() => {
    return events.filter(event => {
      const eventDate = new Date(event.date);
      const inRange = eventDate >= selectedDateRange[0] && eventDate <= selectedDateRange[1];
      const matchesFilter = filter === 'all' || event.type === filter;
      return inRange && matchesFilter;
    });
  }, [events, selectedDateRange, filter]);

  const getEventIcon = (type: string) => {
    switch (type) {
      case 'closure':
        return <ExclamationTriangleIcon className="w-5 h-5 text-red-500" />;
      case 'reopening':
        return <CheckCircleIcon className="w-5 h-5 text-green-500" />;
      case 'enforcement':
        return <EyeIcon className="w-5 h-5 text-yellow-500" />;
      case 'news':
        return <ChartBarIcon className="w-5 h-5 text-blue-500" />;
      default:
        return <ClockIcon className="w-5 h-5 text-gray-500" />;
    }
  };

  const getEventColor = (type: string) => {
    switch (type) {
      case 'closure': return 'border-red-200 bg-red-50';
      case 'reopening': return 'border-green-200 bg-green-50';
      case 'enforcement': return 'border-yellow-200 bg-yellow-50';
      case 'news': return 'border-blue-200 bg-blue-50';
      default: return 'border-gray-200 bg-gray-50';
    }
  };

  return (
    <div className="bg-white rounded-2xl shadow-lg p-6">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-semibold text-gray-900">Closure Timeline</h3>
        
        {/* Filter Controls */}
        <div className="flex items-center space-x-4">
          <select
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
            className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent"
          >
            <option value="all">All Events</option>
            <option value="closure">Closures</option>
            <option value="reopening">Reopenings</option>
            <option value="enforcement">Enforcement</option>
            <option value="news">News</option>
          </select>
          
          <div className="text-sm text-gray-600">
            {filteredEvents.length} events
          </div>
        </div>
      </div>

      {/* Timeline */}
      <div className="relative">
        {/* Timeline Line */}
        <div className="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200"></div>
        
        {/* Events */}
        <div className="space-y-6">
          <AnimatePresence>
            {filteredEvents.map((event, index) => (
              <motion.div
                key={`${event.date}-${index}`}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -20 }}
                transition={{ delay: index * 0.05 }}
                className="relative flex items-start space-x-4"
              >
                {/* Timeline Dot */}
                <div className="flex-shrink-0 w-12 h-12 bg-white border-2 border-gray-300 rounded-full flex items-center justify-center shadow-sm">
                  {getEventIcon(event.type)}
                </div>
                
                {/* Event Card */}
                <motion.div 
                  className={`flex-1 border rounded-lg p-4 ${getEventColor(event.type)}`}
                  whileHover={{ scale: 1.02 }}
                  transition={{ type: 'spring', stiffness: 300 }}
                >
                  <div className="flex justify-between items-start mb-2">
                    <h4 className="font-semibold text-gray-900">{event.title}</h4>
                    <time className="text-sm text-gray-500">
                      {new Date(event.date).toLocaleDateString()}
                    </time>
                  </div>
                  
                  <p className="text-gray-700 text-sm mb-2">{event.description}</p>
                  
                  <div className="flex items-center justify-between">
                    <span className="text-xs text-gray-600 bg-gray-200 px-2 py-1 rounded">
                      {event.location}
                    </span>
                    
                    {event.severity && (
                      <span className={`text-xs px-2 py-1 rounded-full ${
                        event.severity === 'high' ? 'bg-red-100 text-red-700' :
                        event.severity === 'medium' ? 'bg-yellow-100 text-yellow-700' :
                        'bg-green-100 text-green-700'
                      }`}>
                        {event.severity} impact
                      </span>
                    )}
                  </div>
                </motion.div>
              </motion.div>
            ))}
          </AnimatePresence>
        </div>
        
        {filteredEvents.length === 0 && (
          <div className="text-center py-12 text-gray-500">
            <CalendarDaysIcon className="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>No events found for the selected criteria</p>
          </div>
        )}
      </div>
    </div>
  );
};

// Real-time Status Board
interface StoreStatus {
  id: string;
  name: string;
  address: string;
  status: 'open' | 'closed' | 'under_review' | 'reopened';
  lastUpdate: string;
  reason?: string;
  coordinates: [number, number];
}

export const RealTimeStatusBoard: React.FC<{ stores: StoreStatus[] }> = ({ stores }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [sortBy, setSortBy] = useState<'name' | 'status' | 'updated'>('updated');

  const filteredStores = useMemo(() => {
    let filtered = stores.filter(store => {
      const matchesSearch = store.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                           store.address.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesFilter = statusFilter === 'all' || store.status === statusFilter;
      return matchesSearch && matchesFilter;
    });

    // Sort
    filtered.sort((a, b) => {
      switch (sortBy) {
        case 'name':
          return a.name.localeCompare(b.name);
        case 'status':
          return a.status.localeCompare(b.status);
        case 'updated':
          return new Date(b.lastUpdate).getTime() - new Date(a.lastUpdate).getTime();
        default:
          return 0;
      }
    });

    return filtered;
  }, [stores, searchTerm, statusFilter, sortBy]);

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'open': return 'bg-green-100 text-green-800 border-green-200';
      case 'closed': return 'bg-red-100 text-red-800 border-red-200';
      case 'under_review': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'reopened': return 'bg-blue-100 text-blue-800 border-blue-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'open': return <CheckCircleIcon className="w-4 h-4" />;
      case 'closed': return <ExclamationTriangleIcon className="w-4 h-4" />;
      case 'under_review': return <ClockIcon className="w-4 h-4" />;
      case 'reopened': return <TrendingUpIcon className="w-4 h-4" />;
      default: return <BuildingStorefrontIcon className="w-4 h-4" />;
    }
  };

  const statusCounts = useMemo(() => {
    return stores.reduce((acc, store) => {
      acc[store.status] = (acc[store.status] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);
  }, [stores]);

  return (
    <div className="bg-white rounded-2xl shadow-lg p-6">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-semibold text-gray-900">Real-Time Store Status</h3>
        <div className="flex items-center space-x-2">
          <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
          <span className="text-sm text-gray-600">Live Updates</span>
        </div>
      </div>

      {/* Status Summary */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {Object.entries(statusCounts).map(([status, count]) => (
          <motion.div
            key={status}
            className={`p-4 rounded-lg border-2 ${getStatusColor(status)}`}
            whileHover={{ scale: 1.05 }}
            transition={{ type: 'spring', stiffness: 300 }}
          >
            <div className="flex items-center space-x-2">
              {getStatusIcon(status)}
              <div>
                <div className="text-2xl font-bold">{count}</div>
                <div className="text-sm capitalize">{status.replace('_', ' ')}</div>
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      {/* Controls */}
      <div className="flex flex-col md:flex-row gap-4 mb-6">
        <input
          type="text"
          placeholder="Search stores..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
        />
        
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
        >
          <option value="all">All Status</option>
          <option value="open">Open</option>
          <option value="closed">Closed</option>
          <option value="under_review">Under Review</option>
          <option value="reopened">Reopened</option>
        </select>
        
        <select
          value={sortBy}
          onChange={(e) => setSortBy(e.target.value as any)}
          className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
        >
          <option value="updated">Last Updated</option>
          <option value="name">Name</option>
          <option value="status">Status</option>
        </select>
      </div>

      {/* Store List */}
      <div className="space-y-3 max-h-96 overflow-y-auto">
        <AnimatePresence>
          {filteredStores.map((store, index) => (
            <motion.div
              key={store.id}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -20 }}
              transition={{ delay: index * 0.02 }}
              className="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <div className="flex-1">
                <h4 className="font-semibold text-gray-900">{store.name}</h4>
                <p className="text-sm text-gray-600">{store.address}</p>
                {store.reason && (
                  <p className="text-xs text-gray-500 mt-1">{store.reason}</p>
                )}
              </div>
              
              <div className="text-right">
                <div className={`inline-flex items-center space-x-1 px-3 py-1 rounded-full border text-sm font-medium ${getStatusColor(store.status)}`}>
                  {getStatusIcon(store.status)}
                  <span className="capitalize">{store.status.replace('_', ' ')}</span>
                </div>
                <div className="text-xs text-gray-500 mt-1">
                  Updated {new Date(store.lastUpdate).toLocaleString()}
                </div>
              </div>
            </motion.div>
          ))}
        </AnimatePresence>
      </div>
    </div>
  );
};

// Main Cannabis Tracking Dashboard
export const CannabisTrackingDashboard: React.FC = () => {
  const [selectedMetric, setSelectedMetric] = useState<'closures' | 'active' | 'enforcement'>('active');
  const [dateRange, setDateRange] = useState<[Date, Date]>([
    new Date(Date.now() - 30 * 24 * 60 * 60 * 1000),
    new Date()
  ]);

  // Sample data
  const heatmapData: HeatmapData[] = [
    { borough: 'Manhattan', closures: 15, active: 45, enforcement: 8, coordinates: [200, 150] },
    { borough: 'Brooklyn', closures: 23, active: 67, enforcement: 12, coordinates: [400, 300] },
    { borough: 'Queens', closures: 18, active: 52, enforcement: 9, coordinates: [600, 200] },
    { borough: 'Bronx', closures: 12, active: 34, enforcement: 6, coordinates: [300, 100] },
    { borough: 'Staten Island', closures: 5, active: 18, enforcement: 2, coordinates: [500, 400] },
  ];

  const timelineEvents: TimelineEvent[] = [
    {
      date: '2024-01-28',
      type: 'closure',
      title: 'Green Dreams NYC - Temporary Closure',
      description: 'Store closed due to Operation Smokeout enforcement action',
      location: 'Manhattan',
      severity: 'high'
    },
    {
      date: '2024-01-25',
      type: 'enforcement',
      title: 'NYPD Compliance Check',
      description: 'Routine compliance inspection conducted',
      location: 'Brooklyn',
      severity: 'low'
    },
    {
      date: '2024-01-22',
      type: 'reopening',
      title: 'Empire Cannabis - Reopened',
      description: 'Store reopened after compliance review',
      location: 'Queens',
      severity: 'medium'
    },
    {
      date: '2024-01-20',
      type: 'news',
      title: 'New Cannabis Regulations Announced',
      description: 'NYC announces updated licensing requirements',
      location: 'City-wide'
    }
  ];

  const storeStatuses: StoreStatus[] = [
    {
      id: '1',
      name: 'Green Dreams NYC',
      address: '123 Broadway, Manhattan',
      status: 'closed',
      lastUpdate: '2024-01-28T10:30:00Z',
      reason: 'Operation Smokeout enforcement',
      coordinates: [40.7589, -73.9851]
    },
    {
      id: '2',
      name: 'Brooklyn Buds',
      address: '456 Atlantic Ave, Brooklyn',
      status: 'open',
      lastUpdate: '2024-01-28T09:15:00Z',
      coordinates: [40.6892, -73.9442]
    },
    {
      id: '3',
      name: 'Queens Cannabis Co',
      address: '789 Northern Blvd, Queens',
      status: 'under_review',
      lastUpdate: '2024-01-27T16:45:00Z',
      reason: 'Pending license renewal',
      coordinates: [40.7282, -73.7949]
    },
    {
      id: '4',
      name: 'Empire Cannabis',
      address: '321 Forest Ave, Staten Island',
      status: 'reopened',
      lastUpdate: '2024-01-27T11:20:00Z',
      reason: 'Compliance issues resolved',
      coordinates: [40.6378, -74.0851]
    }
  ];

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-8">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="mb-8"
      >
        <h1 className="text-3xl font-bold text-gray-900 mb-4">
          Cannabis Store Tracking Dashboard
        </h1>
        <p className="text-gray-600">
          Interactive visualization and real-time monitoring of NYC cannabis dispensary operations
        </p>
      </motion.div>

      {/* Interactive Heatmap */}
      <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ delay: 0.1 }}
      >
        <InteractiveHeatmap
          data={heatmapData}
          selectedMetric={selectedMetric}
          onMetricChange={setSelectedMetric}
        />
      </motion.div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Timeline */}
        <motion.div
          initial={{ opacity: 0, x: -20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.2 }}
        >
          <ClosureTimeline
            events={timelineEvents}
            selectedDateRange={dateRange}
            onDateRangeChange={setDateRange}
          />
        </motion.div>

        {/* Real-time Status Board */}
        <motion.div
          initial={{ opacity: 0, x: 20 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.3 }}
        >
          <RealTimeStatusBoard stores={storeStatuses} />
        </motion.div>
      </div>
    </div>
  );
};