import React, { useState, useEffect } from 'react';
import { 
  FlaskConicalIcon,
  DnaIcon,
  SparklesIcon,
  ArrowRightIcon,
  HeartIcon,
  TrendingUpIcon,
  AlertTriangleIcon,
  CheckCircleIcon,
  XCircleIcon,
  RefreshCwIcon
} from 'lucide-react';

interface Genetics {
  id: number;
  name: string;
  generation: number;
  parent1_id?: number;
  parent2_id?: number;
  thc_min: number;
  thc_max: number;
  cbd_min: number;
  cbd_max: number;
  flowering_time_min: number;
  flowering_time_max: number;
  yield_indoor_min: number;
  yield_indoor_max: number;
  difficulty_level: number;
  flavor_profile: string;
  effects: string;
  terpenes: string;
  rarity: string;
  stability: number;
  vigor: number;
  disease_resistance: number;
  created_at: string;
  bred_by_player?: boolean;
  breeding_success_rate?: number;
}

interface BreedingResult {
  success: boolean;
  offspring?: Genetics;
  failure_reason?: string;
  breeding_notes?: string;
}

interface GeneticsLabProps {
  playerGenetics: Genetics[];
  onBreedGenetics: (parent1Id: number, parent2Id: number) => Promise<BreedingResult>;
  onRefreshGenetics: () => void;
}

const GeneticsLab: React.FC<GeneticsLabProps> = ({
  playerGenetics,
  onBreedGenetics,
  onRefreshGenetics
}) => {
  const [selectedParent1, setSelectedParent1] = useState<Genetics | null>(null);
  const [selectedParent2, setSelectedParent2] = useState<Genetics | null>(null);
  const [breeding, setBreeding] = useState(false);
  const [breedingResult, setBreedingResult] = useState<BreedingResult | null>(null);
  const [showDetails, setShowDetails] = useState(false);
  const [filter, setFilter] = useState<'all' | 'bred' | 'natural'>('all');
  const [sortBy, setSortBy] = useState<'name' | 'rarity' | 'thc' | 'generation'>('rarity');

  const filteredGenetics = playerGenetics
    .filter(genetics => {
      if (filter === 'bred') return genetics.bred_by_player;
      if (filter === 'natural') return !genetics.bred_by_player;
      return true;
    })
    .sort((a, b) => {
      switch (sortBy) {
        case 'name':
          return a.name.localeCompare(b.name);
        case 'thc':
          return (b.thc_max + b.thc_min) / 2 - (a.thc_max + a.thc_min) / 2;
        case 'generation':
          return b.generation - a.generation;
        case 'rarity':
        default:
          const rarityOrder = { 'common': 1, 'uncommon': 2, 'rare': 3, 'epic': 4, 'legendary': 5 };
          return (rarityOrder[b.rarity as keyof typeof rarityOrder] || 0) - 
                 (rarityOrder[a.rarity as keyof typeof rarityOrder] || 0);
      }
    });

  const handleBreeding = async () => {
    if (!selectedParent1 || !selectedParent2) return;
    
    setBreeding(true);
    setBreedingResult(null);
    
    try {
      const result = await onBreedGenetics(selectedParent1.id, selectedParent2.id);
      setBreedingResult(result);
      
      if (result.success) {
        // Clear selections and refresh genetics list
        setSelectedParent1(null);
        setSelectedParent2(null);
        setTimeout(() => {
          onRefreshGenetics();
        }, 1000);
      }
    } catch (error) {
      setBreedingResult({
        success: false,
        failure_reason: 'Breeding process failed. Please try again.'
      });
    } finally {
      setBreeding(false);
    }
  };

  const calculateBreedingProbability = () => {
    if (!selectedParent1 || !selectedParent2) return 0;
    
    // Base success rate
    let successRate = 60;
    
    // Stability bonus
    successRate += (selectedParent1.stability + selectedParent2.stability) / 2 * 10;
    
    // Generation penalty (harder to breed higher generations)
    const avgGeneration = (selectedParent1.generation + selectedParent2.generation) / 2;
    successRate -= avgGeneration * 5;
    
    // Same rarity bonus
    if (selectedParent1.rarity === selectedParent2.rarity) {
      successRate += 10;
    }
    
    // Disease resistance bonus
    successRate += (selectedParent1.disease_resistance + selectedParent2.disease_resistance) / 2 * 5;
    
    return Math.max(10, Math.min(90, Math.round(successRate)));
  };

  const predictOffspringTraits = () => {
    if (!selectedParent1 || !selectedParent2) return null;
    
    return {
      thc_range: {
        min: Math.min(selectedParent1.thc_min, selectedParent2.thc_min),
        max: Math.max(selectedParent1.thc_max, selectedParent2.thc_max)
      },
      cbd_range: {
        min: Math.min(selectedParent1.cbd_min, selectedParent2.cbd_min),
        max: Math.max(selectedParent1.cbd_max, selectedParent2.cbd_max)
      },
      flowering_time: {
        min: Math.min(selectedParent1.flowering_time_min, selectedParent2.flowering_time_min),
        max: Math.max(selectedParent1.flowering_time_max, selectedParent2.flowering_time_max)
      },
      yield: {
        min: Math.min(selectedParent1.yield_indoor_min, selectedParent2.yield_indoor_min),
        max: Math.max(selectedParent1.yield_indoor_max, selectedParent2.yield_indoor_max)
      }
    };
  };

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

  const GeneticsCard: React.FC<{ genetics: Genetics; selected: boolean; onClick: () => void; role?: 'parent1' | 'parent2' }> = 
    ({ genetics, selected, onClick, role }) => (
    <div 
      className={`border-2 rounded-lg p-4 cursor-pointer transition-all hover:shadow-lg ${
        selected 
          ? role === 'parent1' 
            ? 'border-blue-500 bg-blue-50' 
            : role === 'parent2'
            ? 'border-green-500 bg-green-50'
            : 'border-purple-500 bg-purple-50'
          : 'border-gray-200 hover:border-gray-300'
      }`}
      onClick={onClick}
    >
      <div className="flex items-start justify-between">
        <div className="flex-1">
          <div className="flex items-center space-x-2">
            <h3 className="font-medium text-gray-900">{genetics.name}</h3>
            <span className={`px-2 py-1 rounded-full text-xs font-medium ${getRarityColor(genetics.rarity)}`}>
              {genetics.rarity}
            </span>
            {genetics.bred_by_player && (
              <SparklesIcon className="w-4 h-4 text-purple-500" title="Player Bred" />
            )}
          </div>
          
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <div>
              <span className="text-gray-500">THC:</span>
              <span className="ml-1 font-medium">{genetics.thc_min}-{genetics.thc_max}%</span>
            </div>
            <div>
              <span className="text-gray-500">Gen:</span>
              <span className="ml-1 font-medium">{genetics.generation}</span>
            </div>
            <div>
              <span className="text-gray-500">Flowering:</span>
              <span className="ml-1 font-medium">{genetics.flowering_time_min}-{genetics.flowering_time_max} days</span>
            </div>
            <div>
              <span className="text-gray-500">Yield:</span>
              <span className="ml-1 font-medium">{genetics.yield_indoor_min}-{genetics.yield_indoor_max}g</span>
            </div>
          </div>
          
          <div className="mt-2 flex space-x-4 text-xs">
            <div className="flex items-center space-x-1">
              <HeartIcon className="w-3 h-3 text-red-500" />
              <span>{Math.round(genetics.vigor * 100)}% vigor</span>
            </div>
            <div className="flex items-center space-x-1">
              <TrendingUpIcon className="w-3 h-3 text-green-500" />
              <span>{Math.round(genetics.stability * 100)}% stability</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <FlaskConicalIcon className="w-8 h-8 text-purple-600" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Genetics Laboratory</h1>
            <p className="text-gray-600">Crossbreed your strains to create new genetics</p>
          </div>
        </div>
        
        <button
          onClick={onRefreshGenetics}
          className="flex items-center space-x-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
        >
          <RefreshCwIcon className="w-4 h-4" />
          <span>Refresh</span>
        </button>
      </div>

      {/* Breeding Interface */}
      <div className="bg-white rounded-lg shadow p-6">
        <h2 className="text-xl font-semibold mb-4 flex items-center space-x-2">
          <DnaIcon className="w-6 h-6 text-purple-600" />
          <span>Crossbreeding Chamber</span>
        </h2>
        
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Parent Selection */}
          <div className="lg:col-span-2">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <h3 className="font-medium text-blue-600 mb-2">Parent 1</h3>
                {selectedParent1 ? (
                  <GeneticsCard
                    genetics={selectedParent1}
                    selected={true}
                    onClick={() => setSelectedParent1(null)}
                    role="parent1"
                  />
                ) : (
                  <div className="border-2 border-dashed border-blue-300 rounded-lg p-8 text-center text-blue-600">
                    Select first parent
                  </div>
                )}
              </div>
              
              <div>
                <h3 className="font-medium text-green-600 mb-2">Parent 2</h3>
                {selectedParent2 ? (
                  <GeneticsCard
                    genetics={selectedParent2}
                    selected={true}
                    onClick={() => setSelectedParent2(null)}
                    role="parent2"
                  />
                ) : (
                  <div className="border-2 border-dashed border-green-300 rounded-lg p-8 text-center text-green-600">
                    Select second parent
                  </div>
                )}
              </div>
            </div>
          </div>
          
          {/* Breeding Prediction & Action */}
          <div className="space-y-4">
            {selectedParent1 && selectedParent2 && (
              <div className="bg-gray-50 rounded-lg p-4">
                <h4 className="font-medium mb-3 flex items-center space-x-2">
                  <SparklesIcon className="w-5 h-5 text-purple-500" />
                  <span>Breeding Prediction</span>
                </h4>
                
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span>Success Rate:</span>
                    <span className="font-medium text-green-600">
                      {calculateBreedingProbability()}%
                    </span>
                  </div>
                  
                  {(() => {
                    const prediction = predictOffspringTraits();
                    return prediction && (
                      <>
                        <div className="flex justify-between">
                          <span>THC Range:</span>
                          <span>{prediction.thc_range.min}-{prediction.thc_range.max}%</span>
                        </div>
                        <div className="flex justify-between">
                          <span>Flowering:</span>
                          <span>{prediction.flowering_time.min}-{prediction.flowering_time.max} days</span>
                        </div>
                        <div className="flex justify-between">
                          <span>Yield Range:</span>
                          <span>{prediction.yield.min}-{prediction.yield.max}g</span>
                        </div>
                      </>
                    );
                  })()}
                </div>
              </div>
            )}
            
            <button
              onClick={handleBreeding}
              disabled={!selectedParent1 || !selectedParent2 || breeding}
              className={`w-full flex items-center justify-center space-x-2 px-4 py-3 rounded-lg font-medium transition-colors ${
                selectedParent1 && selectedParent2 && !breeding
                  ? 'bg-purple-600 hover:bg-purple-700 text-white'
                  : 'bg-gray-300 text-gray-500 cursor-not-allowed'
              }`}
            >
              {breeding ? (
                <RefreshCwIcon className="w-5 h-5 animate-spin" />
              ) : (
                <FlaskConicalIcon className="w-5 h-5" />
              )}
              <span>{breeding ? 'Breeding...' : 'Start Crossbreeding'}</span>
              {selectedParent1 && selectedParent2 && (
                <ArrowRightIcon className="w-4 h-4" />
              )}
            </button>
          </div>
        </div>
        
        {/* Breeding Result */}
        {breedingResult && (
          <div className={`mt-6 p-4 rounded-lg ${
            breedingResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'
          }`}>
            <div className="flex items-start space-x-3">
              {breedingResult.success ? (
                <CheckCircleIcon className="w-6 h-6 text-green-600 mt-1" />
              ) : (
                <XCircleIcon className="w-6 h-6 text-red-600 mt-1" />
              )}
              <div className="flex-1">
                <h4 className={`font-medium ${
                  breedingResult.success ? 'text-green-900' : 'text-red-900'
                }`}>
                  {breedingResult.success ? 'Breeding Successful!' : 'Breeding Failed'}
                </h4>
                
                {breedingResult.success && breedingResult.offspring && (
                  <div className="mt-2">
                    <p className="text-green-800">
                      Created new strain: <strong>{breedingResult.offspring.name}</strong>
                    </p>
                    <div className="mt-2 text-sm text-green-700">
                      <div>THC: {breedingResult.offspring.thc_min}-{breedingResult.offspring.thc_max}%</div>
                      <div>Generation: {breedingResult.offspring.generation}</div>
                      <div>Rarity: {breedingResult.offspring.rarity}</div>
                    </div>
                  </div>
                )}
                
                {!breedingResult.success && (
                  <p className="text-red-800 mt-1">{breedingResult.failure_reason}</p>
                )}
                
                {breedingResult.breeding_notes && (
                  <p className="text-sm text-gray-600 mt-2">{breedingResult.breeding_notes}</p>
                )}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Genetics Collection */}
      <div className="bg-white rounded-lg shadow">
        <div className="p-6 border-b border-gray-200">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold">Your Genetics Collection</h2>
            <span className="text-sm text-gray-500">{playerGenetics.length} genetics available</span>
          </div>
          
          {/* Filters */}
          <div className="flex flex-wrap gap-4">
            <div className="flex items-center space-x-2">
              <label className="text-sm text-gray-600">Filter:</label>
              <select
                value={filter}
                onChange={(e) => setFilter(e.target.value as any)}
                className="border border-gray-300 rounded px-2 py-1 text-sm"
              >
                <option value="all">All Genetics</option>
                <option value="bred">Player Bred</option>
                <option value="natural">Natural Strains</option>
              </select>
            </div>
            
            <div className="flex items-center space-x-2">
              <label className="text-sm text-gray-600">Sort by:</label>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value as any)}
                className="border border-gray-300 rounded px-2 py-1 text-sm"
              >
                <option value="rarity">Rarity</option>
                <option value="name">Name</option>
                <option value="thc">THC Content</option>
                <option value="generation">Generation</option>
              </select>
            </div>
          </div>
        </div>
        
        <div className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredGenetics.map((genetics) => (
              <GeneticsCard
                key={genetics.id}
                genetics={genetics}
                selected={selectedParent1?.id === genetics.id || selectedParent2?.id === genetics.id}
                onClick={() => {
                  if (selectedParent1?.id === genetics.id) {
                    setSelectedParent1(null);
                  } else if (selectedParent2?.id === genetics.id) {
                    setSelectedParent2(null);
                  } else if (!selectedParent1) {
                    setSelectedParent1(genetics);
                  } else if (!selectedParent2) {
                    setSelectedParent2(genetics);
                  } else {
                    // Replace parent1 if both are selected
                    setSelectedParent1(genetics);
                  }
                }}
              />
            ))}
          </div>
          
          {filteredGenetics.length === 0 && (
            <div className="text-center py-12">
              <DnaIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">No Genetics Found</h3>
              <p className="text-gray-500">
                {filter !== 'all' 
                  ? `No genetics match the selected filter "${filter}".`
                  : 'You don\'t have any genetics yet. Start by growing some plants!'
                }
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default GeneticsLab;