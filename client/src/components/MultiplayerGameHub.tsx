import React, { useState, useEffect } from 'react';
import {
  UserGroupIcon,
  BuildingOffice2Icon,
  ShoppingCartIcon,
  TrophyIcon,
  PlusIcon,
  EyeIcon,
  ChatBubbleLeftEllipsisIcon,
  BanknotesIcon,
  ClockIcon,
  UserIcon
} from '@heroicons/react/24/outline';
import { motion, AnimatePresence } from 'framer-motion';
import toast from 'react-hot-toast';
import axios from 'axios';

interface MultiplayerGameHubProps {
  isOpen: boolean;
  onClose: () => void;
}

interface Guild {
  id: string;
  name: string;
  description: string;
  guild_type: 'casual' | 'competitive' | 'professional';
  current_members: number;
  max_members: number;
  leader_name: string;
  role?: string;
}

interface CoopOperation {
  id: string;
  name: string;
  operation_type: string;
  status: 'planning' | 'active' | 'harvesting' | 'completed';
  total_investment: number;
  expected_yield: number;
  participants_count: number;
  time_remaining?: string;
}

interface PlayerTrade {
  id: string;
  item_type: string;
  item_name: string;
  quantity: number;
  asking_price: number;
  seller_name: string;
  expires_at: string;
  status: 'listed' | 'pending' | 'completed';
}

interface Competition {
  id: string;
  name: string;
  competition_type: string;
  status: 'upcoming' | 'registration_open' | 'active' | 'completed';
  prize_pool: number;
  current_participants: number;
  max_participants: number;
  starts_at: string;
  registration_fee: number;
}

const MultiplayerGameHub: React.FC<MultiplayerGameHubProps> = ({ isOpen, onClose }) => {
  const [activeTab, setActiveTab] = useState<'guild' | 'coop' | 'trading' | 'competitions'>('guild');
  const [loading, setLoading] = useState(false);
  
  // Guild state
  const [userGuild, setUserGuild] = useState<Guild | null>(null);
  const [availableGuilds, setAvailableGuilds] = useState<Guild[]>([]);
  const [showCreateGuild, setShowCreateGuild] = useState(false);
  
  // Cooperative operations state
  const [userOperations, setUserOperations] = useState<CoopOperation[]>([]);
  const [availableOperations, setAvailableOperations] = useState<CoopOperation[]>([]);
  const [showCreateOperation, setShowCreateOperation] = useState(false);
  
  // Trading state
  const [marketplace, setMarketplace] = useState<PlayerTrade[]>([]);
  const [userTrades, setUserTrades] = useState<PlayerTrade[]>([]);
  const [showListItem, setShowListItem] = useState(false);
  
  // Competition state
  const [activeCompetitions, setActiveCompetitions] = useState<Competition[]>([]);
  const [userCompetitions, setUserCompetitions] = useState<Competition[]>([]);

  // Form state
  const [guildForm, setGuildForm] = useState({
    name: '',
    description: '',
    guild_type: 'casual' as const,
    is_public: true,
    max_members: 50
  });

  useEffect(() => {
    if (isOpen) {
      loadInitialData();
    }
  }, [isOpen, activeTab]);

  const loadInitialData = async () => {
    setLoading(true);
    try {
      switch (activeTab) {
        case 'guild':
          await loadGuildData();
          break;
        case 'coop':
          await loadCoopData();
          break;
        case 'trading':
          await loadTradingData();
          break;
        case 'competitions':
          await loadCompetitionData();
          break;
      }
    } catch (error) {
      console.error('Failed to load data:', error);
      toast.error('Failed to load data');
    } finally {
      setLoading(false);
    }
  };

  const loadGuildData = async () => {
    const [guildResponse, availableResponse] = await Promise.all([
      axios.get('/api/multiplayer-game/guilds/my-guild'),
      axios.get('/api/multiplayer-game/guilds/available')
    ]);
    
    setUserGuild(guildResponse.data.guild);
    setAvailableGuilds(availableResponse.data.guilds);
  };

  const loadCoopData = async () => {
    const [userOpsResponse, availableOpsResponse] = await Promise.all([
      axios.get('/api/multiplayer-game/coop-operations/my-operations'),
      axios.get('/api/multiplayer-game/coop-operations/available')
    ]);
    
    setUserOperations(userOpsResponse.data.operations);
    setAvailableOperations(availableOpsResponse.data.operations);
  };

  const loadTradingData = async () => {
    const [marketplaceResponse, userTradesResponse] = await Promise.all([
      axios.get('/api/multiplayer-game/player-trades/marketplace'),
      axios.get('/api/multiplayer-game/player-trades/my-trades')
    ]);
    
    setMarketplace(marketplaceResponse.data.marketplace);
    setUserTrades(userTradesResponse.data.trades);
  };

  const loadCompetitionData = async () => {
    const [activeResponse, userResponse] = await Promise.all([
      axios.get('/api/multiplayer-game/competitions/active'),
      axios.get('/api/multiplayer-game/competitions/my-competitions')
    ]);
    
    setActiveCompetitions(activeResponse.data.competitions);
    setUserCompetitions(userResponse.data.competitions);
  };

  const createGuild = async () => {
    try {
      await axios.post('/api/multiplayer-game/guilds/create', guildForm);
      toast.success('Guild created successfully!');
      setShowCreateGuild(false);
      loadGuildData();
      setGuildForm({
        name: '',
        description: '',
        guild_type: 'casual',
        is_public: true,
        max_members: 50
      });
    } catch (error) {
      toast.error('Failed to create guild');
    }
  };

  const joinGuild = async (guildId: string) => {
    try {
      await axios.post(`/api/multiplayer-game/guilds/${guildId}/join`);
      toast.success('Successfully joined guild!');
      loadGuildData();
    } catch (error) {
      toast.error('Failed to join guild');
    }
  };

  const joinCoopOperation = async (operationId: string) => {
    try {
      const investment = prompt('Enter investment amount (tokens):');
      if (!investment) return;

      await axios.post(`/api/multiplayer-game/coop-operations/${operationId}/join`, {
        role: 'investor',
        investment_amount: parseFloat(investment)
      });
      
      toast.success('Successfully joined cooperative operation!');
      loadCoopData();
    } catch (error) {
      toast.error('Failed to join operation');
    }
  };

  const purchaseItem = async (tradeId: string) => {
    try {
      await axios.post(`/api/multiplayer-game/player-trades/${tradeId}/purchase`);
      toast.success('Purchase completed!');
      loadTradingData();
    } catch (error) {
      toast.error('Failed to purchase item');
    }
  };

  const registerForCompetition = async (competitionId: string) => {
    try {
      await axios.post(`/api/multiplayer-game/competitions/${competitionId}/register`);
      toast.success('Successfully registered for competition!');
      loadCompetitionData();
    } catch (error) {
      toast.error('Failed to register for competition');
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
      case 'listed':
        return 'bg-green-100 text-green-800';
      case 'planning':
      case 'upcoming':
        return 'bg-yellow-100 text-yellow-800';
      case 'completed':
        return 'bg-blue-100 text-blue-800';
      case 'pending':
        return 'bg-orange-100 text-orange-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const formatTimeRemaining = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = date.getTime() - now.getTime();
    
    if (diff <= 0) return 'Expired';
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    
    if (days > 0) return `${days}d ${hours}h`;
    return `${hours}h`;
  };

  const tabs = [
    { key: 'guild', label: 'Guilds', icon: UserGroupIcon, count: userGuild ? 1 : 0 },
    { key: 'coop', label: 'Co-ops', icon: BuildingOffice2Icon, count: userOperations.length },
    { key: 'trading', label: 'Trading', icon: ShoppingCartIcon, count: marketplace.length },
    { key: 'competitions', label: 'Competitions', icon: TrophyIcon, count: activeCompetitions.length }
  ];

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black bg-opacity-50" onClick={onClose} />
      
      {/* Panel */}
      <motion.div
        initial={{ x: '100%' }}
        animate={{ x: 0 }}
        exit={{ x: '100%' }}
        transition={{ type: 'spring', damping: 20, stiffness: 300 }}
        className="absolute right-0 top-0 h-full w-full max-w-4xl bg-white shadow-2xl"
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white">
          <div className="flex items-center space-x-3">
            <UserGroupIcon className="w-8 h-8" />
            <div>
              <h2 className="text-xl font-bold">Multiplayer Game Hub</h2>
              <p className="text-sm opacity-90">Connect, collaborate, and compete</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-white hover:bg-opacity-20 rounded-full transition-colors"
          >
            ✕
          </button>
        </div>

        {/* Tabs */}
        <div className="flex border-b border-gray-200 bg-gray-50">
          {tabs.map(tab => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key as any)}
              className={`flex-1 flex items-center justify-center space-x-2 py-4 px-4 text-sm font-medium transition-all ${
                activeTab === tab.key
                  ? 'bg-white border-b-2 border-indigo-600 text-indigo-600'
                  : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
              }`}
            >
              <tab.icon className="w-5 h-5" />
              <span>{tab.label}</span>
              {tab.count > 0 && (
                <span className="ml-1 px-2 py-1 text-xs bg-indigo-100 text-indigo-600 rounded-full">
                  {tab.count}
                </span>
              )}
            </button>
          ))}
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6">
          {loading ? (
            <div className="flex items-center justify-center h-64">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
            </div>
          ) : (
            <div>
              {/* Guild Tab */}
              {activeTab === 'guild' && (
                <div className="space-y-6">
                  {/* User's Guild */}
                  {userGuild ? (
                    <div className="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-6 border border-indigo-200">
                      <div className="flex items-center justify-between mb-4">
                        <div>
                          <h3 className="text-xl font-bold text-gray-900">{userGuild.name}</h3>
                          <p className="text-gray-600">{userGuild.description}</p>
                        </div>
                        <div className="text-right">
                          <div className="text-sm text-gray-500">Your Role</div>
                          <div className="font-medium capitalize">{userGuild.role}</div>
                        </div>
                      </div>
                      <div className="flex items-center space-x-6 text-sm text-gray-600">
                        <div className="flex items-center space-x-2">
                          <UserIcon className="w-4 h-4" />
                          <span>{userGuild.current_members}/{userGuild.max_members} members</span>
                        </div>
                        <div className="capitalize">{userGuild.guild_type} guild</div>
                      </div>
                    </div>
                  ) : (
                    <div className="text-center py-8">
                      <UserGroupIcon className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                      <h3 className="text-lg font-medium text-gray-900 mb-2">
                        You're not in a guild yet
                      </h3>
                      <p className="text-gray-600 mb-4">
                        Join a guild to collaborate with other players and access exclusive features.
                      </p>
                      <button
                        onClick={() => setShowCreateGuild(true)}
                        className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                      >
                        Create Guild
                      </button>
                    </div>
                  )}

                  {/* Available Guilds */}
                  {!userGuild && (
                    <div>
                      <h4 className="text-lg font-semibold text-gray-900 mb-4">Available Guilds</h4>
                      <div className="grid gap-4">
                        {availableGuilds.map(guild => (
                          <div key={guild.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between">
                              <div>
                                <h5 className="font-medium text-gray-900">{guild.name}</h5>
                                <p className="text-sm text-gray-600 mt-1">{guild.description}</p>
                                <div className="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                                  <span>{guild.current_members}/{guild.max_members} members</span>
                                  <span className="capitalize">{guild.guild_type}</span>
                                  <span>Led by {guild.leader_name}</span>
                                </div>
                              </div>
                              <button
                                onClick={() => joinGuild(guild.id)}
                                className="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors"
                              >
                                Join
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Create Guild Modal */}
                  <AnimatePresence>
                    {showCreateGuild && (
                      <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-60"
                      >
                        <motion.div
                          initial={{ scale: 0.9, opacity: 0 }}
                          animate={{ scale: 1, opacity: 1 }}
                          exit={{ scale: 0.9, opacity: 0 }}
                          className="bg-white rounded-lg p-6 w-full max-w-md mx-4"
                        >
                          <h3 className="text-lg font-semibold text-gray-900 mb-4">Create New Guild</h3>
                          <div className="space-y-4">
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">
                                Guild Name
                              </label>
                              <input
                                type="text"
                                value={guildForm.name}
                                onChange={(e) => setGuildForm({ ...guildForm, name: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Enter guild name..."
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">
                                Description
                              </label>
                              <textarea
                                value={guildForm.description}
                                onChange={(e) => setGuildForm({ ...guildForm, description: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                rows={3}
                                placeholder="Describe your guild..."
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">
                                Guild Type
                              </label>
                              <select
                                value={guildForm.guild_type}
                                onChange={(e) => setGuildForm({ ...guildForm, guild_type: e.target.value as any })}
                                className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                              >
                                <option value="casual">Casual</option>
                                <option value="competitive">Competitive</option>
                                <option value="professional">Professional</option>
                              </select>
                            </div>
                          </div>
                          <div className="flex justify-end space-x-3 mt-6">
                            <button
                              onClick={() => setShowCreateGuild(false)}
                              className="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                            >
                              Cancel
                            </button>
                            <button
                              onClick={createGuild}
                              disabled={!guildForm.name.trim()}
                              className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                            >
                              Create Guild
                            </button>
                          </div>
                        </motion.div>
                      </motion.div>
                    )}
                  </AnimatePresence>
                </div>
              )}

              {/* Cooperative Operations Tab */}
              {activeTab === 'coop' && (
                <div className="space-y-6">
                  {/* User's Operations */}
                  {userOperations.length > 0 && (
                    <div>
                      <h4 className="text-lg font-semibold text-gray-900 mb-4">Your Operations</h4>
                      <div className="grid gap-4">
                        {userOperations.map(operation => (
                          <div key={operation.id} className="border border-gray-200 rounded-lg p-4">
                            <div className="flex items-center justify-between mb-2">
                              <h5 className="font-medium text-gray-900">{operation.name}</h5>
                              <span className={`px-2 py-1 text-xs rounded-full font-medium ${getStatusColor(operation.status)}`}>
                                {operation.status}
                              </span>
                            </div>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                              <div>
                                <div className="text-gray-500">Type</div>
                                <div className="capitalize">{operation.operation_type.replace('_', ' ')}</div>
                              </div>
                              <div>
                                <div className="text-gray-500">Investment</div>
                                <div>{operation.total_investment} tokens</div>
                              </div>
                              <div>
                                <div className="text-gray-500">Expected Yield</div>
                                <div>{operation.expected_yield} units</div>
                              </div>
                              <div>
                                <div className="text-gray-500">Participants</div>
                                <div>{operation.participants_count} players</div>
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Available Operations */}
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Available Operations</h4>
                    {availableOperations.length > 0 ? (
                      <div className="grid gap-4">
                        {availableOperations.map(operation => (
                          <div key={operation.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-2">
                              <h5 className="font-medium text-gray-900">{operation.name}</h5>
                              <button
                                onClick={() => joinCoopOperation(operation.id)}
                                className="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition-colors"
                              >
                                Join
                              </button>
                            </div>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                              <div>
                                <BuildingOffice2Icon className="w-4 h-4 inline mr-1" />
                                {operation.operation_type.replace('_', ' ')}
                              </div>
                              <div>
                                <BanknotesIcon className="w-4 h-4 inline mr-1" />
                                {operation.total_investment} tokens invested
                              </div>
                              <div>
                                <UserIcon className="w-4 h-4 inline mr-1" />
                                {operation.participants_count} participants
                              </div>
                              <div>
                                <TrophyIcon className="w-4 h-4 inline mr-1" />
                                {operation.expected_yield} expected yield
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <BuildingOffice2Icon className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                          No operations available
                        </h3>
                        <p className="text-gray-600">
                          Check back later or create your own cooperative operation.
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* Trading Tab */}
              {activeTab === 'trading' && (
                <div className="space-y-6">
                  {/* Marketplace */}
                  <div>
                    <div className="flex items-center justify-between mb-4">
                      <h4 className="text-lg font-semibold text-gray-900">Marketplace</h4>
                      <button
                        onClick={() => setShowListItem(true)}
                        className="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors"
                      >
                        <PlusIcon className="w-4 h-4 inline mr-1" />
                        List Item
                      </button>
                    </div>
                    
                    {marketplace.length > 0 ? (
                      <div className="grid gap-4">
                        {marketplace.map(trade => (
                          <div key={trade.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between">
                              <div>
                                <h5 className="font-medium text-gray-900">{trade.item_name}</h5>
                                <div className="flex items-center space-x-4 text-sm text-gray-600 mt-1">
                                  <span>Qty: {trade.quantity}</span>
                                  <span>Seller: {trade.seller_name}</span>
                                  <span className="flex items-center">
                                    <ClockIcon className="w-4 h-4 mr-1" />
                                    {formatTimeRemaining(trade.expires_at)}
                                  </span>
                                </div>
                              </div>
                              <div className="text-right">
                                <div className="text-lg font-bold text-green-600">
                                  {trade.asking_price} tokens
                                </div>
                                <button
                                  onClick={() => purchaseItem(trade.id)}
                                  className="mt-2 px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition-colors"
                                >
                                  Buy Now
                                </button>
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <ShoppingCartIcon className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                          No items for sale
                        </h3>
                        <p className="text-gray-600">
                          Be the first to list something in the marketplace!
                        </p>
                      </div>
                    )}
                  </div>

                  {/* User's Trades */}
                  {userTrades.length > 0 && (
                    <div>
                      <h4 className="text-lg font-semibold text-gray-900 mb-4">Your Listings</h4>
                      <div className="grid gap-4">
                        {userTrades.map(trade => (
                          <div key={trade.id} className="border border-gray-200 rounded-lg p-4">
                            <div className="flex items-center justify-between">
                              <div>
                                <h5 className="font-medium text-gray-900">{trade.item_name}</h5>
                                <div className="text-sm text-gray-600 mt-1">
                                  Qty: {trade.quantity} • Expires: {formatTimeRemaining(trade.expires_at)}
                                </div>
                              </div>
                              <div className="text-right">
                                <span className={`px-2 py-1 text-xs rounded-full font-medium ${getStatusColor(trade.status)}`}>
                                  {trade.status}
                                </span>
                                <div className="text-lg font-bold text-green-600 mt-1">
                                  {trade.asking_price} tokens
                                </div>
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Competitions Tab */}
              {activeTab === 'competitions' && (
                <div className="space-y-6">
                  {/* Active Competitions */}
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900 mb-4">Active Competitions</h4>
                    {activeCompetitions.length > 0 ? (
                      <div className="grid gap-4">
                        {activeCompetitions.map(competition => (
                          <div key={competition.id} className="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-3">
                              <h5 className="font-medium text-gray-900">{competition.name}</h5>
                              <span className={`px-2 py-1 text-xs rounded-full font-medium ${getStatusColor(competition.status)}`}>
                                {competition.status.replace('_', ' ')}
                              </span>
                            </div>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-4">
                              <div>
                                <div className="font-medium text-gray-900">Prize Pool</div>
                                <div>{competition.prize_pool} tokens</div>
                              </div>
                              <div>
                                <div className="font-medium text-gray-900">Participants</div>
                                <div>{competition.current_participants}/{competition.max_participants}</div>
                              </div>
                              <div>
                                <div className="font-medium text-gray-900">Entry Fee</div>
                                <div>{competition.registration_fee} tokens</div>
                              </div>
                              <div>
                                <div className="font-medium text-gray-900">Starts</div>
                                <div>{new Date(competition.starts_at).toLocaleDateString()}</div>
                              </div>
                            </div>
                            <div className="flex justify-between items-center">
                              <span className="text-sm text-gray-500 capitalize">
                                {competition.competition_type.replace('_', ' ')}
                              </span>
                              {competition.status === 'registration_open' && (
                                <button
                                  onClick={() => registerForCompetition(competition.id)}
                                  className="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition-colors"
                                >
                                  Register
                                </button>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-8">
                        <TrophyIcon className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                          No active competitions
                        </h3>
                        <p className="text-gray-600">
                          Check back soon for new competitions to join!
                        </p>
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </motion.div>
    </div>
  );
};

export default MultiplayerGameHub;
