import React, { useState, useEffect } from 'react';
import { 
  HeartIcon, 
  ExclamationTriangleIcon, 
  MagnifyingGlassIcon,
  CheckBadgeIcon,
  XCircleIcon,
  InformationCircleIcon,
  FunnelIcon
} from '@heroicons/react/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/react/24/solid';
import axios from 'axios';
import { toast } from 'react-hot-toast';

interface Politician {
  id: number;
  name: string;
  position: string;
  party: string;
  state: string;
  city?: string;
  office_level: string;
  cannabis_stance: 'pro_cannabis' | 'anti_cannabis' | 'neutral' | 'unknown';
  cannabis_score: number | null;
  policy_positions_count: number;
  votes_count: number;
  endorsements_count: number;
  avg_vote_impact: number;
  photo_url?: string;
  website_url?: string;
  donation_allowed?: boolean;
  donation_restrictions?: string[];
}

interface UserPreferences {
  only_cannabis_friendly: boolean;
  minimum_cannabis_score: number | null;
  blocked_politicians: number[];
  notification_preferences: any;
}

const CannabisChampions: React.FC = () => {
  const [cannabisFriendly, setCannabis_friendly] = useState<Politician[]>([]);
  const [antiCannabis, setAntiCannabis] = useState<Politician[]>([]);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<'friendly' | 'hostile'>('friendly');
  const [searchTerm, setSearchTerm] = useState('');
  const [filters, setFilters] = useState({
    office_level: '',
    state: '',
    min_score: 60
  });
  const [userPrefs, setUserPrefs] = useState<UserPreferences>({
    only_cannabis_friendly: false,
    minimum_cannabis_score: null,
    blocked_politicians: [],
    notification_preferences: {}
  });
  const [selectedPolitician, setSelectedPolitician] = useState<Politician | null>(null);
  const [showPreferences, setShowPreferences] = useState(false);

  useEffect(() => {
    fetchPoliticians();
  }, [activeTab, filters, searchTerm]);

  const fetchPoliticians = async () => {
    setLoading(true);
    try {
      if (activeTab === 'friendly') {
        const response = await axios.get('/api/cannabis_politics.php', {
          params: {
            action: 'cannabis-friendly',
            stance: 'pro_cannabis',
            min_score: filters.min_score,
            office_level: filters.office_level || undefined,
            state: filters.state || undefined,
            limit: 50
          }
        });
        setCannabis_friendly(response.data.data || []);
      } else {
        const response = await axios.get('/api/cannabis_politics.php', {
          params: {
            action: 'anti-cannabis',
            max_score: 30,
            office_level: filters.office_level || undefined,
            state: filters.state || undefined,
            limit: 50
          }
        });
        setAntiCannabis(response.data.data || []);
      }
    } catch (error) {
      console.error('Error fetching politicians:', error);
      toast.error('Failed to load politicians');
    } finally {
      setLoading(false);
    }
  };

  const searchCandidates = async () => {
    if (!searchTerm.trim()) {
      fetchPoliticians();
      return;
    }

    setLoading(true);
    try {
      const response = await axios.get('/api/cannabis_politics.php', {
        params: {
          action: 'search-candidates',
          q: searchTerm,
          cannabis_friendly: activeTab === 'friendly',
          office_level: filters.office_level || undefined,
          state: filters.state || undefined,
          limit: 50
        }
      });

      if (activeTab === 'friendly') {
        setCannabis_friendly(response.data.data || []);
      } else {
        setAntiCannabis(response.data.data || []);
      }
    } catch (error) {
      console.error('Error searching politicians:', error);
      toast.error('Failed to search politicians');
    } finally {
      setLoading(false);
    }
  };

  const checkDonationEligibility = async (politicianId: number) => {
    try {
      const response = await axios.get('/api/cannabis_politics.php', {
        params: {
          action: 'donation-eligibility',
          politician_id: politicianId,
          user_id: 1 // TODO: Get from auth context
        }
      });
      return response.data.data;
    } catch (error) {
      console.error('Error checking donation eligibility:', error);
      return { eligible: false, restrictions: ['Unable to verify eligibility'] };
    }
  };

  const updateUserPreferences = async (newPrefs: Partial<UserPreferences>) => {
    try {
      await axios.post('/api/cannabis_politics.php?action=set-user-preferences', {
        ...userPrefs,
        ...newPrefs
      });
      setUserPrefs({ ...userPrefs, ...newPrefs });
      toast.success('Preferences updated successfully');
    } catch (error) {
      console.error('Error updating preferences:', error);
      toast.error('Failed to update preferences');
    }
  };

  const blockPolitician = (politicianId: number) => {
    const updatedBlocked = [...userPrefs.blocked_politicians, politicianId];
    updateUserPreferences({ blocked_politicians: updatedBlocked });
  };

  const unblockPolitician = (politicianId: number) => {
    const updatedBlocked = userPrefs.blocked_politicians.filter(id => id !== politicianId);
    updateUserPreferences({ blocked_politicians: updatedBlocked });
  };

  const getScoreColor = (score: number | null) => {
    if (score === null) return 'text-gray-400';
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-green-400';
    if (score >= 40) return 'text-yellow-500';
    if (score >= 20) return 'text-orange-500';
    return 'text-red-500';
  };

  const getScoreBadge = (score: number | null) => {
    if (score === null) return { label: 'Unrated', color: 'bg-gray-100 text-gray-800' };
    if (score >= 90) return { label: 'Cannabis Champion', color: 'bg-green-600 text-white' };
    if (score >= 80) return { label: 'Strong Ally', color: 'bg-green-500 text-white' };
    if (score >= 60) return { label: 'Cannabis Friendly', color: 'bg-green-400 text-white' };
    if (score >= 40) return { label: 'Mixed Record', color: 'bg-yellow-500 text-white' };
    if (score >= 20) return { label: 'Unfriendly', color: 'bg-orange-500 text-white' };
    return { label: 'Cannabis Enemy', color: 'bg-red-600 text-white' };
  };

  const renderPoliticianCard = (politician: Politician) => {
    const scoreBadge = getScoreBadge(politician.cannabis_score);
    const isBlocked = userPrefs.blocked_politicians.includes(politician.id);

    return (
      <div
        key={politician.id}
        className={`bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 ${
          isBlocked ? 'opacity-50' : ''
        }`}
      >
        <div className="flex items-start justify-between mb-4">
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-2">
              <h3 className="text-lg font-semibold text-gray-900">{politician.name}</h3>
              {politician.cannabis_stance === 'pro_cannabis' && (
                <HeartSolidIcon className="w-5 h-5 text-red-500" />
              )}
              {politician.cannabis_stance === 'anti_cannabis' && (
                <XCircleIcon className="w-5 h-5 text-red-500" />
              )}
            </div>
            <p className="text-gray-600 text-sm">{politician.position}</p>
            <p className="text-gray-500 text-xs">
              {politician.party} • {politician.state}
              {politician.city && ` • ${politician.city}`}
            </p>
          </div>
          
          {politician.photo_url && (
            <img
              src={politician.photo_url}
              alt={politician.name}
              className="w-12 h-12 rounded-full object-cover"
            />
          )}
        </div>

        <div className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <span className={`inline-flex px-3 py-1 text-xs font-medium rounded-full ${scoreBadge.color}`}>
              {scoreBadge.label}
            </span>
            <span className={`text-lg font-bold ${getScoreColor(politician.cannabis_score)}`}>
              {politician.cannabis_score ?? 'N/A'}
            </span>
          </div>

          <div className="flex gap-4 text-xs text-gray-500">
            <span>{politician.policy_positions_count} positions</span>
            <span>{politician.votes_count} votes</span>
            <span>{politician.endorsements_count} endorsements</span>
          </div>
        </div>

        <div className="flex gap-2">
          <button
            onClick={() => setSelectedPolitician(politician)}
            className="flex-1 bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700 transition-colors"
          >
            View Details
          </button>
          
          {activeTab === 'friendly' ? (
            <button
              onClick={async () => {
                const eligibility = await checkDonationEligibility(politician.id);
                if (eligibility.eligible) {
                  // TODO: Open donation modal
                  toast.success('Donation interface would open here');
                } else {
                  toast.error(eligibility.restrictions.join(', '));
                }
              }}
              className="bg-green-600 text-white px-4 py-2 rounded-md text-sm hover:bg-green-700 transition-colors"
            >
              Donate
            </button>
          ) : (
            <button
              onClick={() => isBlocked ? unblockPolitician(politician.id) : blockPolitician(politician.id)}
              className={`px-4 py-2 rounded-md text-sm transition-colors ${
                isBlocked 
                  ? 'bg-gray-600 text-white hover:bg-gray-700' 
                  : 'bg-red-600 text-white hover:bg-red-700'
              }`}
            >
              {isBlocked ? 'Unblock' : 'Block'}
            </button>
          )}
        </div>
      </div>
    );
  };

  const currentPoliticians = activeTab === 'friendly' ? cannabisFriendly : antiCannabis;

  return (
    <div className="max-w-7xl mx-auto p-6">
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-2">
          {activeTab === 'friendly' ? '🌿 Cannabis Champions' : '🚫 Cannabis Opposition'}
        </h1>
        <p className="text-gray-600">
          {activeTab === 'friendly' 
            ? 'Politicians who support cannabis reform and legalization efforts'
            : 'Politicians who oppose cannabis reform - know where they stand'
          }
        </p>
      </div>

      {/* Tabs */}
      <div className="border-b border-gray-200 mb-6">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('friendly')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'friendly'
                ? 'border-green-500 text-green-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <HeartIcon className="w-5 h-5 inline mr-2" />
            Cannabis Champions ({cannabisFriendly.length})
          </button>
          <button
            onClick={() => setActiveTab('hostile')}
            className={`py-2 px-1 border-b-2 font-medium text-sm ${
              activeTab === 'hostile'
                ? 'border-red-500 text-red-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            <ExclamationTriangleIcon className="w-5 h-5 inline mr-2" />
            Cannabis Opposition ({antiCannabis.length})
          </button>
        </nav>
      </div>

      {/* Search and Filters */}
      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div className="md:col-span-2">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Search Politicians
            </label>
            <div className="relative">
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                onKeyPress={(e) => e.key === 'Enter' && searchCandidates()}
                placeholder="Search by name, position, or party..."
                className="w-full p-3 pl-10 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent"
              />
              <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-3.5 text-gray-400" />
            </div>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Office Level
            </label>
            <select
              value={filters.office_level}
              onChange={(e) => setFilters({...filters, office_level: e.target.value})}
              className="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent"
            >
              <option value="">All Offices</option>
              <option value="federal">Federal</option>
              <option value="state">State</option>
              <option value="county">County</option>
              <option value="city">City</option>
              <option value="local">Local</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              State
            </label>
            <select
              value={filters.state}
              onChange={(e) => setFilters({...filters, state: e.target.value})}
              className="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent"
            >
              <option value="">All States</option>
              <option value="NY">New York</option>
              <option value="CA">California</option>
              <option value="CO">Colorado</option>
              <option value="WA">Washington</option>
              <option value="OR">Oregon</option>
              {/* Add more states */}
            </select>
          </div>
        </div>

        <div className="flex justify-between items-center">
          {activeTab === 'friendly' && (
            <div className="flex items-center gap-4">
              <label className="flex items-center">
                <span className="text-sm text-gray-700 mr-2">Minimum Score:</span>
                <input
                  type="range"
                  min="0"
                  max="100"
                  value={filters.min_score}
                  onChange={(e) => setFilters({...filters, min_score: parseInt(e.target.value)})}
                  className="w-24"
                />
                <span className="ml-2 text-sm font-medium">{filters.min_score}</span>
              </label>
            </div>
          )}

          <div className="flex gap-2">
            <button
              onClick={() => setShowPreferences(!showPreferences)}
              className="flex items-center gap-2 text-gray-600 hover:text-gray-800"
            >
              <FunnelIcon className="w-5 h-5" />
              Preferences
            </button>
            <button
              onClick={searchCandidates}
              disabled={loading}
              className="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 disabled:opacity-50"
            >
              {loading ? 'Searching...' : 'Search'}
            </button>
          </div>
        </div>

        {/* User Preferences Panel */}
        {showPreferences && (
          <div className="mt-4 p-4 bg-gray-50 rounded-lg">
            <h3 className="font-medium mb-3">Your Cannabis Donation Preferences</h3>
            <div className="space-y-3">
              <label className="flex items-center">
                <input
                  type="checkbox"
                  checked={userPrefs.only_cannabis_friendly}
                  onChange={(e) => updateUserPreferences({ only_cannabis_friendly: e.target.checked })}
                  className="rounded border-gray-300 text-green-600 focus:ring-green-500"
                />
                <span className="ml-2 text-sm">Only allow donations to cannabis-friendly politicians</span>
              </label>
              <div className="flex items-center gap-4">
                <label className="text-sm">Minimum cannabis score for donations:</label>
                <input
                  type="number"
                  min="0"
                  max="100"
                  value={userPrefs.minimum_cannabis_score || ''}
                  onChange={(e) => updateUserPreferences({ 
                    minimum_cannabis_score: e.target.value ? parseInt(e.target.value) : null 
                  })}
                  className="w-20 p-1 border border-gray-300 rounded"
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Results */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {loading ? (
          <div className="col-span-full text-center py-12">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-green-500 mx-auto"></div>
            <p className="mt-4 text-gray-500">Loading politicians...</p>
          </div>
        ) : currentPoliticians.length === 0 ? (
          <div className="col-span-full text-center py-12">
            <InformationCircleIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
            <p className="text-gray-500">
              No politicians found matching your criteria.
              {searchTerm && ' Try adjusting your search terms or filters.'}
            </p>
          </div>
        ) : (
          currentPoliticians.map(renderPoliticianCard)
        )}
      </div>

      {/* Politician Detail Modal would go here */}
      {selectedPolitician && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-y-auto">
            <div className="p-6">
              <div className="flex justify-between items-start mb-4">
                <h2 className="text-2xl font-bold">{selectedPolitician.name}</h2>
                <button
                  onClick={() => setSelectedPolitician(null)}
                  className="text-gray-400 hover:text-gray-600"
                >
                  <XCircleIcon className="w-6 h-6" />
                </button>
              </div>
              <p className="text-gray-600 mb-4">{selectedPolitician.position}</p>
              
              {/* Detailed politician information would go here */}
              <div className="space-y-4">
                <div className="bg-gray-50 p-4 rounded-lg">
                  <h3 className="font-medium mb-2">Cannabis Policy Summary</h3>
                  <div className="flex items-center gap-4">
                    <span className={`inline-flex px-3 py-1 text-sm rounded-full ${
                      getScoreBadge(selectedPolitician.cannabis_score).color
                    }`}>
                      {getScoreBadge(selectedPolitician.cannabis_score).label}
                    </span>
                    <span className={`text-lg font-bold ${getScoreColor(selectedPolitician.cannabis_score)}`}>
                      Score: {selectedPolitician.cannabis_score ?? 'N/A'}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CannabisChampions;