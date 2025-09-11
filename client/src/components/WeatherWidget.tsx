import React, { useState, useEffect } from 'react';
import { 
  CloudIcon, 
  SunIcon, 
  CloudRainIcon, 
  SnowflakeIcon,
  ThermometerSunIcon,
  ThermometerSnowflakeIcon,
  WindIcon,
  EyeIcon
} from 'lucide-react';

interface WeatherEffect {
  id: number;
  type: string;
  severity: string;
  start_time: string;
  end_time: string;
  temperature_modifier: number;
  humidity_modifier: number;
  light_modifier: number;
  growth_modifier: number;
  yield_modifier: number;
  disease_risk_modifier: number;
  description: string;
}

interface WeatherSummary {
  dominant_weather: string;
  overall_growth_impact: number;
  overall_yield_impact: number;
  disease_risk: number;
}

interface WeatherData {
  current_time: string;
  active_effects: WeatherEffect[];
  effects_count: number;
  summary: WeatherSummary;
}

interface WeatherWidgetProps {
  showDetailed?: boolean;
  refreshInterval?: number;
}

const WeatherWidget: React.FC<WeatherWidgetProps> = ({ 
  showDetailed = false, 
  refreshInterval = 60000 
}) => {
  const [weather, setWeather] = useState<WeatherData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showDetails, setShowDetails] = useState(false);

  useEffect(() => {
    fetchWeatherData();
    const interval = setInterval(fetchWeatherData, refreshInterval);
    return () => clearInterval(interval);
  }, [refreshInterval]);

  const fetchWeatherData = async () => {
    try {
      const response = await fetch('/api/weather/current');
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const data = await response.json();
      setWeather(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch weather');
    } finally {
      setLoading(false);
    }
  };

  const getWeatherIcon = (type: string, severity: string) => {
    const iconClass = `w-6 h-6 ${getSeverityColor(severity)}`;
    
    switch (type) {
      case 'heat_wave':
        return <ThermometerSunIcon className={iconClass} />;
      case 'cold_snap':
        return <ThermometerSnowflakeIcon className={iconClass} />;
      case 'rain_storm':
        return <CloudRainIcon className={iconClass} />;
      case 'drought':
        return <SunIcon className={`${iconClass} text-yellow-600`} />;
      case 'sunny':
        return <SunIcon className={`${iconClass} text-yellow-400`} />;
      case 'overcast':
        return <CloudIcon className={iconClass} />;
      case 'windy':
        return <WindIcon className={iconClass} />;
      case 'snow':
        return <SnowflakeIcon className={iconClass} />;
      default:
        return <CloudIcon className={iconClass} />;
    }
  };

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'mild':
        return 'text-green-500';
      case 'moderate':
        return 'text-yellow-500';
      case 'severe':
        return 'text-orange-500';
      case 'extreme':
        return 'text-red-500';
      default:
        return 'text-gray-500';
    }
  };

  const getImpactColor = (impact: number) => {
    if (impact > 10) return 'text-green-500';
    if (impact > 0) return 'text-green-400';
    if (impact === 0) return 'text-gray-500';
    if (impact > -10) return 'text-yellow-500';
    if (impact > -20) return 'text-orange-500';
    return 'text-red-500';
  };

  const formatTimeRemaining = (endTime: string) => {
    const now = new Date().getTime();
    const end = new Date(endTime).getTime();
    const diff = end - now;
    
    if (diff <= 0) return 'Ending soon';
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (hours > 0) {
      return `${hours}h ${minutes}m remaining`;
    }
    return `${minutes}m remaining`;
  };

  if (loading) {
    return (
      <div className="bg-white rounded-lg shadow p-4 animate-pulse">
        <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
        <div className="h-4 bg-gray-200 rounded w-1/2"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <div className="flex items-center">
          <div className="text-red-600 text-sm">
            Failed to load weather: {error}
          </div>
        </div>
      </div>
    );
  }

  if (!weather) {
    return null;
  }

  return (
    <div className="bg-white rounded-lg shadow">
      <div 
        className="p-4 cursor-pointer" 
        onClick={() => setShowDetails(!showDetails)}
      >
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            {weather.active_effects.length > 0 ? (
              <div className="flex space-x-1">
                {weather.active_effects.slice(0, 3).map((effect, index) => (
                  <div key={index}>
                    {getWeatherIcon(effect.type, effect.severity)}
                  </div>
                ))}
                {weather.active_effects.length > 3 && (
                  <div className="text-gray-400 text-sm">
                    +{weather.active_effects.length - 3}
                  </div>
                )}
              </div>
            ) : (
              <SunIcon className="w-6 h-6 text-yellow-400" />
            )}
            
            <div>
              <h3 className="font-medium text-gray-900">
                {weather.summary.dominant_weather}
              </h3>
              {weather.active_effects.length > 0 && (
                <p className="text-sm text-gray-500">
                  {weather.effects_count} active effect{weather.effects_count !== 1 ? 's' : ''}
                </p>
              )}
            </div>
          </div>
          
          <EyeIcon className={`w-5 h-5 text-gray-400 transform transition-transform ${showDetails ? 'rotate-180' : ''}`} />
        </div>

        {/* Growth Impact Summary */}
        <div className="mt-3 grid grid-cols-3 gap-4 text-sm">
          <div className="text-center">
            <div className={`font-medium ${getImpactColor(weather.summary.overall_growth_impact)}`}>
              {weather.summary.overall_growth_impact > 0 ? '+' : ''}
              {weather.summary.overall_growth_impact.toFixed(1)}%
            </div>
            <div className="text-gray-500">Growth</div>
          </div>
          <div className="text-center">
            <div className={`font-medium ${getImpactColor(weather.summary.overall_yield_impact)}`}>
              {weather.summary.overall_yield_impact > 0 ? '+' : ''}
              {weather.summary.overall_yield_impact.toFixed(1)}%
            </div>
            <div className="text-gray-500">Yield</div>
          </div>
          <div className="text-center">
            <div className={`font-medium ${weather.summary.disease_risk > 10 ? 'text-red-500' : 'text-gray-600'}`}>
              {weather.summary.disease_risk.toFixed(1)}%
            </div>
            <div className="text-gray-500">Disease Risk</div>
          </div>
        </div>
      </div>

      {/* Detailed View */}
      {showDetails && (
        <div className="border-t border-gray-200">
          <div className="p-4 space-y-4">
            <h4 className="font-medium text-gray-900">Active Weather Events</h4>
            
            {weather.active_effects.length === 0 ? (
              <p className="text-gray-500 text-sm">No active weather events</p>
            ) : (
              <div className="space-y-3">
                {weather.active_effects.map((effect, index) => (
                  <div key={index} className="border rounded-lg p-3">
                    <div className="flex items-start justify-between">
                      <div className="flex items-center space-x-3">
                        {getWeatherIcon(effect.type, effect.severity)}
                        <div>
                          <h5 className="font-medium capitalize">
                            {effect.type.replace('_', ' ')} 
                            <span className={`text-sm ml-2 ${getSeverityColor(effect.severity)}`}>
                              ({effect.severity})
                            </span>
                          </h5>
                          <p className="text-sm text-gray-600">{effect.description}</p>
                          <p className="text-xs text-gray-500 mt-1">
                            {formatTimeRemaining(effect.end_time)}
                          </p>
                        </div>
                      </div>
                    </div>
                    
                    {showDetailed && (
                      <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div className="flex justify-between">
                          <span>Growth:</span>
                          <span className={getImpactColor(effect.growth_modifier * 100)}>
                            {effect.growth_modifier > 0 ? '+' : ''}
                            {(effect.growth_modifier * 100).toFixed(1)}%
                          </span>
                        </div>
                        <div className="flex justify-between">
                          <span>Yield:</span>
                          <span className={getImpactColor(effect.yield_modifier * 100)}>
                            {effect.yield_modifier > 0 ? '+' : ''}
                            {(effect.yield_modifier * 100).toFixed(1)}%
                          </span>
                        </div>
                        <div className="flex justify-between">
                          <span>Temp:</span>
                          <span className="text-gray-600">
                            {effect.temperature_modifier > 0 ? '+' : ''}
                            {effect.temperature_modifier}°F
                          </span>
                        </div>
                        <div className="flex justify-between">
                          <span>Humidity:</span>
                          <span className="text-gray-600">
                            {effect.humidity_modifier > 0 ? '+' : ''}
                            {effect.humidity_modifier}%
                          </span>
                        </div>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
            
            <div className="text-xs text-gray-500 pt-2 border-t">
              Last updated: {new Date(weather.current_time).toLocaleTimeString()}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default WeatherWidget;