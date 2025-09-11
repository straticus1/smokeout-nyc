import React, { useState, useEffect } from 'react';
import {
  ShoppingCartIcon,
  HandshakeIcon,
  PlusIcon,
  XIcon,
  StarIcon,
  ClockIcon,
  UserIcon,
  DollarSignIcon,
  CheckCircleIcon,
  AlertTriangleIcon,
  SearchIcon,
  FilterIcon
} from 'lucide-react';

interface TradeItem {
  type: 'plant' | 'genetics' | 'tokens';
  id?: number;
  amount?: number;
  name?: string;
  rarity?: string;
  strain_name?: string;
  final_weight?: number;
  final_quality?: number;
  thc_min?: number;
  thc_max?: number;
}

interface TradeOffer {
  id: number;
  created_by_player_id: number;
  trader_username: string;
  trader_level: number;
  trader_reputation: number;
  trader_rating: number;
  completed_trades: number;
  items_offered: TradeItem[];
  items_requested: TradeItem[];
  tokens_requested: number;
  expires_at: string;
  description: string;
  trade_type: string;
  status: string;
  estimated_value: number;
  detailed_items_offered: TradeItem[];
  detailed_items_requested: TradeItem[];
}

interface TradingCenterProps {
  onTradeCompleted?: (tradeResult: any) => void;
}

const TradingCenter: React.FC<TradingCenterProps> = ({ onTradeCompleted }) => {
  const [offers, setOffers] = useState<TradeOffer[]>([]);
  const [myOffers, setMyOffers] = useState<TradeOffer[]>([]);
  const [activeTab, setActiveTab] = useState<'browse' | 'my-offers' | 'create'>('browse');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedOffer, setSelectedOffer] = useState<TradeOffer | null>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [filters, setFilters] = useState({
    rarity: '',
    max_price: '',
    trade_type: ''
  });

  // Create offer form state
  const [createOfferForm, setCreateOfferForm] = useState({
    items_offered: [] as TradeItem[],
    items_requested: [] as TradeItem[],
    tokens_requested: 0,
    expires_hours: 24,
    description: '',
    trade_type: 'public'
  });

  useEffect(() => {
    fetchTradeOffers();
    fetchMyOffers();
  }, [filters]);

  const fetchTradeOffers = async () => {
    try {
      const params = new URLSearchParams();
      if (filters.rarity) params.append('rarity', filters.rarity);
      if (filters.max_price) params.append('max_price', filters.max_price);
      
      const response = await fetch(`/api/trading/offers?${params}`);
      if (!response.ok) throw new Error('Failed to fetch offers');
      
      const data = await response.json();
      setOffers(data.offers || []);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load trade offers');
    } finally {
      setLoading(false);
    }
  };

  const fetchMyOffers = async () => {
    try {
      const response = await fetch('/api/trading/my-offers');
      if (!response.ok) throw new Error('Failed to fetch your offers');
      
      const data = await response.json();
      setMyOffers(data.offers || []);
    } catch (err) {
      console.error('Failed to fetch your offers:', err);
    }
  };

  const acceptTradeOffer = async (offer: TradeOffer) => {
    try {
      const response = await fetch('/api/trading/accept-offer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          offer_id: offer.id,
          offered_items: offer.items_requested,
          offered_tokens: offer.tokens_requested
        })
      });
      
      if (!response.ok) throw new Error('Trade failed');
      
      const data = await response.json();
      
      if (data.success) {
        setSelectedOffer(null);
        fetchTradeOffers();
        fetchMyOffers();
        
        if (onTradeCompleted) {
          onTradeCompleted(data.trade_result);
        }
        
        alert('Trade completed successfully!');
      }
    } catch (err) {
      alert(`Trade failed: ${err instanceof Error ? err.message : 'Unknown error'}`);
    }
  };

  const cancelTradeOffer = async (offerId: number) => {
    try {
      const response = await fetch(`/api/trading/cancel-offer/${offerId}`, {
        method: 'DELETE'
      });
      
      if (!response.ok) throw new Error('Failed to cancel offer');
      
      const data = await response.json();
      if (data.success) {
        fetchMyOffers();
        alert('Offer cancelled successfully');
      }
    } catch (err) {
      alert(`Failed to cancel offer: ${err instanceof Error ? err.message : 'Unknown error'}`);
    }
  };

  const createTradeOffer = async () => {
    try {
      const response = await fetch('/api/trading/create-offer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(createOfferForm)
      });
      
      if (!response.ok) throw new Error('Failed to create offer');
      
      const data = await response.json();
      if (data.success) {
        setCreateOfferForm({
          items_offered: [],
          items_requested: [],
          tokens_requested: 0,
          expires_hours: 24,
          description: '',
          trade_type: 'public'
        });
        
        setActiveTab('my-offers');
        fetchMyOffers();
        alert('Trade offer created successfully!');
      }
    } catch (err) {
      alert(`Failed to create offer: ${err instanceof Error ? err.message : 'Unknown error'}`);
    }
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

  const formatTimeRemaining = (expiresAt: string) => {
    const now = new Date().getTime();
    const expires = new Date(expiresAt).getTime();
    const diff = expires - now;
    
    if (diff <= 0) return 'Expired';
    
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (hours > 0) {
      return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
  };

  const TradeItemCard: React.FC<{ item: TradeItem; detailed?: boolean }> = ({ item, detailed = false }) => (
    <div className="border border-gray-200 rounded-lg p-3">
      {item.type === 'plant' && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-medium text-sm">{item.strain_name || 'Plant'}</h4>
            {item.rarity && (
              <span className={`px-2 py-1 rounded-full text-xs font-medium ${getRarityColor(item.rarity)}`}>
                {item.rarity}
              </span>
            )}
          </div>
          {detailed && (
            <div className="text-xs text-gray-600 space-y-1">
              <div>Weight: {item.final_weight}g</div>
              <div>Quality: {(item.final_quality || 0 * 100).toFixed(1)}%</div>
            </div>
          )}
        </div>
      )}
      
      {item.type === 'genetics' && (
        <div>
          <div className="flex items-center justify-between mb-2">
            <h4 className="font-medium text-sm">{item.name || 'Genetics'}</h4>
            {item.rarity && (
              <span className={`px-2 py-1 rounded-full text-xs font-medium ${getRarityColor(item.rarity)}`}>
                {item.rarity}
              </span>
            )}
          </div>
          {detailed && (
            <div className="text-xs text-gray-600 space-y-1">
              <div>THC: {item.thc_min}-{item.thc_max}%</div>
            </div>
          )}
        </div>
      )}
      
      {item.type === 'tokens' && (
        <div className="flex items-center space-x-2">
          <DollarSignIcon className="w-4 h-4 text-green-500" />
          <span className="font-medium">{item.amount} tokens</span>
        </div>
      )}
    </div>
  );

  const OfferCard: React.FC<{ offer: TradeOffer; showActions?: boolean; isMyOffer?: boolean }> = ({ 
    offer, 
    showActions = true, 
    isMyOffer = false 
  }) => (
    <div className="border border-gray-200 rounded-lg p-6 hover:shadow-lg transition-shadow">
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center space-x-3">
          <UserIcon className="w-8 h-8 text-gray-400" />
          <div>
            <h3 className="font-medium text-gray-900">{offer.trader_username}</h3>
            <div className="flex items-center space-x-2 text-sm text-gray-500">
              <span>Level {offer.trader_level}</span>
              <span>•</span>
              <div className="flex items-center space-x-1">
                <StarIcon className="w-3 h-3 fill-current text-yellow-400" />
                <span>{offer.trader_rating?.toFixed(1) || 'N/A'}</span>
              </div>
              <span>•</span>
              <span>{offer.completed_trades} trades</span>
            </div>
          </div>
        </div>
        
        <div className="text-right">
          <div className="flex items-center space-x-1 text-sm text-gray-500">
            <ClockIcon className="w-4 h-4" />
            <span>{formatTimeRemaining(offer.expires_at)}</span>
          </div>
          <div className="text-sm text-green-600 font-medium">
            ~${offer.estimated_value} value
          </div>
        </div>
      </div>

      {offer.description && (
        <p className="text-sm text-gray-600 mb-4">{offer.description}</p>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
          <h4 className="font-medium text-gray-900 mb-2">Offering:</h4>
          <div className="space-y-2 max-h-32 overflow-y-auto">
            {offer.detailed_items_offered?.map((item, index) => (
              <TradeItemCard key={index} item={item} detailed />
            ))}
            {offer.detailed_items_offered?.length === 0 && (
              <p className="text-sm text-gray-500">No items offered</p>
            )}
          </div>
        </div>
        
        <div>
          <h4 className="font-medium text-gray-900 mb-2">Requesting:</h4>
          <div className="space-y-2 max-h-32 overflow-y-auto">
            {offer.detailed_items_requested?.map((item, index) => (
              <TradeItemCard key={index} item={item} detailed />
            ))}
            {offer.tokens_requested > 0 && (
              <TradeItemCard item={{ type: 'tokens', amount: offer.tokens_requested }} />
            )}
            {offer.detailed_items_requested?.length === 0 && offer.tokens_requested === 0 && (
              <p className="text-sm text-gray-500">Open to offers</p>
            )}
          </div>
        </div>
      </div>

      {showActions && (
        <div className="flex justify-between items-center pt-4 border-t border-gray-200">
          <button
            onClick={() => setSelectedOffer(offer)}
            className="text-blue-600 hover:text-blue-800 text-sm font-medium"
          >
            View Details
          </button>
          
          {isMyOffer ? (
            <div className="space-x-2">
              <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                offer.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
              }`}>
                {offer.status}
              </span>
              {offer.status === 'active' && (
                <button
                  onClick={() => cancelTradeOffer(offer.id)}
                  className="px-3 py-1 bg-red-100 hover:bg-red-200 text-red-800 text-xs font-medium rounded-full transition-colors"
                >
                  Cancel
                </button>
              )}
            </div>
          ) : (
            <button
              onClick={() => acceptTradeOffer(offer)}
              className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
            >
              Accept Trade
            </button>
          )}
        </div>
      )}
    </div>
  );

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 bg-gray-200 rounded w-1/3"></div>
          <div className="space-y-3">
            {[1, 2, 3].map(i => (
              <div key={i} className="h-32 bg-gray-200 rounded"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto p-6">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center space-x-3">
          <HandshakeIcon className="w-8 h-8 text-blue-600" />
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Trading Center</h1>
            <p className="text-gray-600">Trade plants, genetics, and tokens with other players</p>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex space-x-1 bg-gray-100 p-1 rounded-lg mb-6">
        {[
          { key: 'browse', label: 'Browse Offers', icon: SearchIcon },
          { key: 'my-offers', label: 'My Offers', icon: UserIcon },
          { key: 'create', label: 'Create Offer', icon: PlusIcon }
        ].map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key as any)}
            className={`flex items-center space-x-2 px-4 py-2 rounded-md transition-colors ${
              activeTab === tab.key 
                ? 'bg-white text-blue-600 shadow-sm' 
                : 'text-gray-600 hover:text-gray-900'
            }`}
          >
            <tab.icon className="w-4 h-4" />
            <span>{tab.label}</span>
          </button>
        ))}
      </div>

      {/* Browse Offers Tab */}
      {activeTab === 'browse' && (
        <div className="space-y-6">
          {/* Filters */}
          <div className="bg-white rounded-lg shadow p-4">
            <div className="flex items-center space-x-4">
              <div className="flex-1">
                <input
                  type="text"
                  placeholder="Search offers..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full border border-gray-300 rounded-lg px-3 py-2"
                />
              </div>
              
              <select
                value={filters.rarity}
                onChange={(e) => setFilters(prev => ({ ...prev, rarity: e.target.value }))}
                className="border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="">All Rarities</option>
                <option value="common">Common</option>
                <option value="uncommon">Uncommon</option>
                <option value="rare">Rare</option>
                <option value="epic">Epic</option>
                <option value="legendary">Legendary</option>
              </select>
              
              <input
                type="number"
                placeholder="Max price"
                value={filters.max_price}
                onChange={(e) => setFilters(prev => ({ ...prev, max_price: e.target.value }))}
                className="border border-gray-300 rounded-lg px-3 py-2 w-32"
              />
            </div>
          </div>

          {/* Offers List */}
          <div className="space-y-4">
            {offers.filter(offer => 
              !searchTerm || 
              offer.trader_username.toLowerCase().includes(searchTerm.toLowerCase()) ||
              offer.description.toLowerCase().includes(searchTerm.toLowerCase())
            ).map(offer => (
              <OfferCard key={offer.id} offer={offer} />
            ))}
            
            {offers.length === 0 && (
              <div className="text-center py-12 bg-white rounded-lg shadow">
                <HandshakeIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <h3 className="text-lg font-medium text-gray-900 mb-2">No Trade Offers</h3>
                <p className="text-gray-500">No active trade offers match your criteria.</p>
              </div>
            )}
          </div>
        </div>
      )}

      {/* My Offers Tab */}
      {activeTab === 'my-offers' && (
        <div className="space-y-4">
          {myOffers.map(offer => (
            <OfferCard key={offer.id} offer={offer} isMyOffer />
          ))}
          
          {myOffers.length === 0 && (
            <div className="text-center py-12 bg-white rounded-lg shadow">
              <UserIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-gray-900 mb-2">No Active Offers</h3>
              <p className="text-gray-500">You haven't created any trade offers yet.</p>
              <button
                onClick={() => setActiveTab('create')}
                className="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
              >
                Create Your First Offer
              </button>
            </div>
          )}
        </div>
      )}

      {/* Create Offer Tab */}
      {activeTab === 'create' && (
        <div className="bg-white rounded-lg shadow p-6 space-y-6">
          <h2 className="text-xl font-semibold">Create Trade Offer</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Items You're Offering
              </label>
              <div className="border-2 border-dashed border-gray-300 rounded-lg p-4 min-h-32">
                {createOfferForm.items_offered.length === 0 ? (
                  <p className="text-gray-500 text-center">Click to add items you want to trade</p>
                ) : (
                  <div className="space-y-2">
                    {createOfferForm.items_offered.map((item, index) => (
                      <TradeItemCard key={index} item={item} />
                    ))}
                  </div>
                )}
              </div>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Items You Want
              </label>
              <div className="border-2 border-dashed border-gray-300 rounded-lg p-4 min-h-32">
                {createOfferForm.items_requested.length === 0 && createOfferForm.tokens_requested === 0 ? (
                  <p className="text-gray-500 text-center">Specify what you want in return</p>
                ) : (
                  <div className="space-y-2">
                    {createOfferForm.items_requested.map((item, index) => (
                      <TradeItemCard key={index} item={item} />
                    ))}
                    {createOfferForm.tokens_requested > 0 && (
                      <TradeItemCard item={{ type: 'tokens', amount: createOfferForm.tokens_requested }} />
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Tokens Requested
              </label>
              <input
                type="number"
                value={createOfferForm.tokens_requested}
                onChange={(e) => setCreateOfferForm(prev => ({ 
                  ...prev, 
                  tokens_requested: parseInt(e.target.value) || 0 
                }))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
                min="0"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Expires In (Hours)
              </label>
              <input
                type="number"
                value={createOfferForm.expires_hours}
                onChange={(e) => setCreateOfferForm(prev => ({ 
                  ...prev, 
                  expires_hours: parseInt(e.target.value) || 24 
                }))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
                min="1"
                max="168"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Trade Type
              </label>
              <select
                value={createOfferForm.trade_type}
                onChange={(e) => setCreateOfferForm(prev => ({ 
                  ...prev, 
                  trade_type: e.target.value 
                }))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2"
              >
                <option value="public">Public</option>
                <option value="private">Private</option>
              </select>
            </div>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Description
            </label>
            <textarea
              value={createOfferForm.description}
              onChange={(e) => setCreateOfferForm(prev => ({ 
                ...prev, 
                description: e.target.value 
              }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2"
              rows={3}
              placeholder="Describe your trade offer..."
            />
          </div>
          
          <div className="flex justify-end space-x-4">
            <button
              onClick={() => setCreateOfferForm({
                items_offered: [],
                items_requested: [],
                tokens_requested: 0,
                expires_hours: 24,
                description: '',
                trade_type: 'public'
              })}
              className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
            >
              Reset
            </button>
            
            <button
              onClick={createTradeOffer}
              disabled={createOfferForm.items_offered.length === 0}
              className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
              Create Offer
            </button>
          </div>
        </div>
      )}

      {/* Trade Offer Details Modal */}
      {selectedOffer && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg p-6 max-w-2xl w-full max-h-90vh overflow-y-auto">
            <div className="flex justify-between items-start mb-4">
              <h2 className="text-xl font-semibold">Trade Offer Details</h2>
              <button
                onClick={() => setSelectedOffer(null)}
                className="text-gray-400 hover:text-gray-600"
              >
                <XIcon className="w-6 h-6" />
              </button>
            </div>
            
            <OfferCard offer={selectedOffer} showActions={false} />
            
            <div className="flex justify-end space-x-4 mt-6">
              <button
                onClick={() => setSelectedOffer(null)}
                className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
              >
                Close
              </button>
              
              <button
                onClick={() => acceptTradeOffer(selectedOffer)}
                className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
              >
                Accept Trade
              </button>
            </div>
          </div>
        </div>
      )}

      {error && (
        <div className="fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg">
          <div className="flex items-center space-x-2">
            <AlertTriangleIcon className="w-5 h-5" />
            <span>{error}</span>
          </div>
        </div>
      )}
    </div>
  );
};

export default TradingCenter;