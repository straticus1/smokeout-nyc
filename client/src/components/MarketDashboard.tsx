import React, { useState, useEffect } from 'react';
import {
  TrendingUpIcon,
  TrendingDownIcon,
  BarChart3Icon,
  DollarSignIcon,
  ActivityIcon,
  AlertCircleIcon,
  RefreshCwIcon,
  EyeIcon,
  ArrowUpIcon,
  ArrowDownIcon
} from 'lucide-react';

interface MarketPrice {
  strain_id: number;
  location_id: number;
  strain_name: string;
  location_name: string;
  rarity: string;
  price_modifier: number;
  demand_level: number;
  supply_level: number;
  current_price: number;
  price_change_24h: number;
  market_cap: number;
  volatility: number;
}

interface MarketEvent {
  id: number;
  event_type: string;
  description: string;
  price_effect: number;
  start_time: string;
  end_time: string;
  is_active: boolean;
}

interface MarketTrends {
  current_health: number;
  active_events: MarketEvent[];
  top_performers: any[];
  trending_up: any[];
  trending_down: any[];
  market_sentiment: string;
  volatility_index: number;
  trading_volume_24h: { trades: number; volume: number };
}

const MarketDashboard: React.FC = () => {
  const [marketData, setMarketData] = useState<MarketPrice[]>([]);
  const [trends, setTrends] = useState<MarketTrends | null>(null);
  const [selectedLocation, setSelectedLocation] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [sortBy, setSortBy] = useState<'price' | 'change' | 'volume' | 'demand'>('price');

  useEffect(() => {
    fetchMarketData();
    fetchTrends();
    
    let interval: NodeJS.Timeout;
    if (autoRefresh) {
      interval = setInterval(() => {
        fetchMarketData();
        fetchTrends();
      }, 60000); // Refresh every minute
    }
    
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [selectedLocation, autoRefresh]);

  const fetchMarketData = async () => {
    try {
      const params = new URLSearchParams();
      if (selectedLocation) {
        params.append('location_id', selectedLocation.toString());
      }
      
      const response = await fetch(`/api/market/prices?${params}`);
      if (!response.ok) throw new Error('Failed to fetch market data');
      
      const data = await response.json();
      setMarketData(data.prices || []);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load market data');
    } finally {
      setLoading(false);
    }
  };

  const fetchTrends = async () => {
    try {
      const response = await fetch('/api/market/trends');
      if (!response.ok) throw new Error('Failed to fetch trends');
      
      const data = await response.json();
      setTrends(data.trends);
    } catch (err) {
      console.error('Failed to fetch trends:', err);
    }
  };

  const sortedMarketData = marketData.sort((a, b) => {
    switch (sortBy) {
      case 'change':
        return Math.abs(b.price_change_24h) - Math.abs(a.price_change_24h);
      case 'volume':
        return b.market_cap - a.market_cap;
      case 'demand':
        return b.demand_level - a.demand_level;
      case 'price':
      default:
        return b.current_price - a.current_price;
    }
  });

  const getRarityColor = (rarity: string) => {
    switch (rarity) {
      case 'common': return 'text-gray-600 bg-gray-100';
      case 'uncommon': return 'text-green-600 bg-green-100';
      case 'rare': return 'text-blue-600 bg-blue-100';
      case 'epic': return 'text-purple-600 bg-purple-100';
      case 'legendary': return 'text-yellow-600 bg-yellow-100';
      default: return 'text-gray-600 bg-gray-100';
    }
  };

  const getSentimentColor = (sentiment: string) => {
    switch (sentiment) {
      case 'bullish': return 'text-green-600 bg-green-100';
      case 'bearish': return 'text-red-600 bg-red-100';
      default: return 'text-gray-600 bg-gray-100';
    }
  };

  const formatPrice = (price: number) => `$${price.toFixed(2)}`;
  const formatPercentage = (pct: number) => `${pct > 0 ? '+' : ''}${pct.toFixed(1)}%`;

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-1/3"></div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {[1, 2, 3].map(i => (
              <div key={i} className="h-32 bg-gray-200 rounded"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <BarChart3Icon className="w-8 h-8 text-blue-600" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Market Dashboard</h1>
            <p className="text-gray-600">Real-time cannabis market data and analytics</p>
          </div>
        </div>
        
        <div className="flex items-center space-x-4">
          <label className="flex items-center space-x-2">
            <input
              type="checkbox"
              checked={autoRefresh}
              onChange={(e) => setAutoRefresh(e.target.checked)}
              className="rounded"
            />
            <span className="text-sm">Auto-refresh</span>
          </label>
          
          <button
            onClick={() => { fetchMarketData(); fetchTrends(); }}
            className="flex items-center space-x-2 px-4 py-2 bg-blue-100 hover:bg-blue-200 rounded-lg transition-colors"
          >
            <RefreshCwIcon className="w-4 h-4" />
            <span>Refresh</span>
          </button>
        </div>
      </div>

      {/* Market Overview Cards */}
      {trends && (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Market Health</p>
                <p className="text-2xl font-bold text-green-600">{trends.current_health}/100</p>
              </div>
              <ActivityIcon className="w-8 h-8 text-green-500" />
            </div>
          </div>
          
          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">24h Volume</p>
                <p className="text-2xl font-bold text-blue-600">
                  ${trends.trading_volume_24h?.volume?.toFixed(0) || '0'}
                </p>
                <p className="text-sm text-gray-500">{trends.trading_volume_24h?.trades || 0} trades</p>
              </div>
              <DollarSignIcon className="w-8 h-8 text-blue-500" />
            </div>
          </div>
          
          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Market Sentiment</p>
                <span className={`inline-block px-3 py-1 rounded-full text-sm font-medium ${getSentimentColor(trends.market_sentiment)}`}>
                  {trends.market_sentiment}
                </span>
              </div>
              <TrendingUpIcon className="w-8 h-8 text-purple-500" />
            </div>
          </div>
          
          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600">Volatility Index</p>
                <p className="text-2xl font-bold text-orange-600">{trends.volatility_index}%</p>
              </div>
              <ActivityIcon className="w-8 h-8 text-orange-500" />
            </div>
          </div>
        </div>
      )}

      {/* Active Market Events */}
      {trends?.active_events && trends.active_events.length > 0 && (
        <div className="bg-white rounded-lg shadow">
          <div className="p-6 border-b border-gray-200">
            <h2 className="text-xl font-semibold flex items-center space-x-2">
              <AlertCircleIcon className="w-6 h-6 text-orange-500" />
              <span>Active Market Events</span>
            </h2>
          </div>
          <div className="p-6 space-y-4">
            {trends.active_events.map((event, index) => (
              <div key={index} className="flex items-start space-x-4 p-4 bg-orange-50 rounded-lg border-l-4 border-orange-400">
                <AlertCircleIcon className="w-5 h-5 text-orange-500 mt-0.5" />
                <div className="flex-1">
                  <h4 className="font-medium text-gray-900 capitalize">
                    {event.event_type.replace('_', ' ')}
                  </h4>
                  <p className="text-sm text-gray-600 mt-1">{event.description}</p>
                  <div className="flex items-center justify-between mt-2 text-sm">
                    <span className={`font-medium ${event.price_effect > 1 ? 'text-green-600' : 'text-red-600'}`}>
                      {event.price_effect > 1 ? '+' : ''}{((event.price_effect - 1) * 100).toFixed(1)}% price impact
                    </span>
                    <span className="text-gray-500">
                      Ends: {new Date(event.end_time).toLocaleString()}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Market Data Table */}
      <div className="bg-white rounded-lg shadow">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold">Market Prices</h2>
            
            <div className="flex items-center space-x-4">
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value as any)}
                className="border border-gray-300 rounded px-3 py-2 text-sm"
              >
                <option value="price">Sort by Price</option>
                <option value="change">Sort by Change</option>
                <option value="volume">Sort by Volume</option>
                <option value="demand">Sort by Demand</option>
              </select>
              
              <select
                value={selectedLocation || ''}
                onChange={(e) => setSelectedLocation(e.target.value ? Number(e.target.value) : null)}
                className="border border-gray-300 rounded px-3 py-2 text-sm"
              >
                <option value="">All Locations</option>
                {Array.from(new Set(marketData.map(item => ({ 
                  id: item.location_id, 
                  name: item.location_name 
                })))).map(location => (
                  <option key={location.id} value={location.id}>
                    {location.name}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
        
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Strain
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Location
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Price
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  24h Change
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Supply/Demand
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Market Cap
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {sortedMarketData.map((item, index) => (
                <tr key={`${item.strain_id}-${item.location_id}`} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center space-x-3">
                      <div>
                        <div className="text-sm font-medium text-gray-900">{item.strain_name}</div>
                        <span className={`inline-block px-2 py-1 rounded-full text-xs font-medium ${getRarityColor(item.rarity)}`}>
                          {item.rarity}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {item.location_name}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {formatPrice(item.current_price)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className={`flex items-center space-x-1 ${
                      item.price_change_24h > 0 ? 'text-green-600' : 
                      item.price_change_24h < 0 ? 'text-red-600' : 'text-gray-500'
                    }`}>
                      {item.price_change_24h > 0 ? (
                        <ArrowUpIcon className="w-4 h-4" />
                      ) : item.price_change_24h < 0 ? (
                        <ArrowDownIcon className="w-4 h-4" />
                      ) : null}
                      <span className="text-sm font-medium">
                        {formatPercentage(item.price_change_24h)}
                      </span>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center space-x-2">
                      <div className="flex-1 bg-gray-200 rounded-full h-2">
                        <div 
                          className="bg-blue-500 h-2 rounded-full" 
                          style={{ width: `${item.supply_level}%` }}
                        ></div>
                      </div>
                      <div className="flex-1 bg-gray-200 rounded-full h-2">
                        <div 
                          className="bg-green-500 h-2 rounded-full" 
                          style={{ width: `${item.demand_level}%` }}
                        ></div>
                      </div>
                      <span className="text-xs text-gray-500 w-16 text-right">
                        {item.supply_level}S/{item.demand_level}D
                      </span>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    ${item.market_cap.toLocaleString()}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          
          {sortedMarketData.length === 0 && !loading && (
            <div className="text-center py-12">
              <BarChart3Icon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">No Market Data</h3>
              <p className="text-gray-500">
                {error || 'No market data available for the selected filters.'}
              </p>
            </div>
          )}
        </div>
      </div>

      {/* Trending Strains */}
      {trends && (trends.trending_up.length > 0 || trends.trending_down.length > 0) && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Trending Up */}
          <div className="bg-white rounded-lg shadow">
            <div className="p-6 border-b border-gray-200">
              <h3 className="text-lg font-semibold flex items-center space-x-2">
                <TrendingUpIcon className="w-5 h-5 text-green-500" />
                <span>Trending Up</span>
              </h3>
            </div>
            <div className="p-6 space-y-3">
              {trends.trending_up.slice(0, 5).map((strain, index) => (
                <div key={index} className="flex items-center justify-between">
                  <span className="text-sm font-medium text-gray-900">{strain.name}</span>
                  <span className="text-sm font-medium text-green-600">
                    +{(strain.trend_change * 100).toFixed(1)}%
                  </span>
                </div>
              ))}
            </div>
          </div>
          
          {/* Trending Down */}
          <div className="bg-white rounded-lg shadow">
            <div className="p-6 border-b border-gray-200">
              <h3 className="text-lg font-semibold flex items-center space-x-2">
                <TrendingDownIcon className="w-5 h-5 text-red-500" />
                <span>Trending Down</span>
              </h3>
            </div>
            <div className="p-6 space-y-3">
              {trends.trending_down.slice(0, 5).map((strain, index) => (
                <div key={index} className="flex items-center justify-between">
                  <span className="text-sm font-medium text-gray-900">{strain.name}</span>
                  <span className="text-sm font-medium text-red-600">
                    {(strain.trend_change * 100).toFixed(1)}%
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MarketDashboard;