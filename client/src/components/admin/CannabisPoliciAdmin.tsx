import React, { useState, useEffect } from 'react';
import { ChevronDownIcon, ChevronUpIcon, CheckIcon, XMarkIcon } from '@heroicons/react/24/outline';
import axios from 'axios';
import { toast } from 'react-hot-toast';

interface Politician {
  id: number;
  name: string;
  position: string;
  party: string;
  state: string;
  cannabis_stance: 'pro_cannabis' | 'anti_cannabis' | 'neutral' | 'unknown';
  cannabis_score: number | null;
  last_policy_update: string | null;
  policy_updated_by: number | null;
}

interface PolicyPosition {
  id: number;
  position_type: string;
  stance: string;
  confidence_level: string;
  source_type: string;
  source_url?: string;
  verified_by?: number;
  created_at: string;
}

interface VotingRecord {
  id: number;
  legislation_name: string;
  bill_number?: string;
  vote: string;
  vote_date: string;
  cannabis_impact: string;
  verified: boolean;
}

const CannabisPolicyAdmin: React.FC = () => {
  const [politicians, setPoliticians] = useState<Politician[]>([]);
  const [selectedPolitician, setSelectedPolitician] = useState<Politician | null>(null);
  const [policyPositions, setPolicyPositions] = useState<PolicyPosition[]>([]);
  const [votingRecords, setVotingRecords] = useState<VotingRecord[]>([]);
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [stanceFilter, setStanceFilter] = useState<string>('all');
  const [showAddForm, setShowAddForm] = useState(false);

  const [editForm, setEditForm] = useState({
    cannabis_stance: '' as Politician['cannabis_stance'],
    cannabis_score: '',
    reason: ''
  });

  const [newPositionForm, setNewPositionForm] = useState({
    position_type: '',
    stance: '',
    source_type: '',
    source_url: '',
    confidence_level: 'confirmed'
  });

  useEffect(() => {
    fetchPoliticians();
  }, [searchTerm, stanceFilter]);

  const fetchPoliticians = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (searchTerm) params.append('q', searchTerm);
      if (stanceFilter !== 'all') params.append('stance', stanceFilter);
      params.append('limit', '50');

      const response = await axios.get(`/api/cannabis_politics.php?action=cannabis-friendly&${params}`);
      setPoliticians(response.data.data || []);
    } catch (error) {
      console.error('Error fetching politicians:', error);
      toast.error('Failed to load politicians');
    } finally {
      setLoading(false);
    }
  };

  const fetchPoliticianDetails = async (politicianId: number) => {
    try {
      const response = await axios.get(`/api/cannabis_politics.php?action=politician-details&politician_id=${politicianId}`);
      const data = response.data.data;
      
      setSelectedPolitician(data);
      setPolicyPositions(data.policy_positions || []);
      setVotingRecords(data.recent_votes || []);
      
      setEditForm({
        cannabis_stance: data.cannabis_stance || 'unknown',
        cannabis_score: data.cannabis_score?.toString() || '',
        reason: ''
      });
    } catch (error) {
      console.error('Error fetching politician details:', error);
      toast.error('Failed to load politician details');
    }
  };

  const updateCannabisStance = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedPolitician) return;

    try {
      await axios.post('/api/cannabis_politics.php?action=update-stance', {
        politician_id: selectedPolitician.id,
        stance: editForm.cannabis_stance,
        score: editForm.cannabis_score ? parseInt(editForm.cannabis_score) : null,
        reason: editForm.reason
      });

      toast.success('Cannabis stance updated successfully');
      fetchPoliticians();
      fetchPoliticianDetails(selectedPolitician.id);
      setEditForm({ ...editForm, reason: '' });
    } catch (error: any) {
      console.error('Error updating stance:', error);
      toast.error(error.response?.data?.error || 'Failed to update stance');
    }
  };

  const addPolicyPosition = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedPolitician) return;

    try {
      await axios.post('/api/cannabis_politics.php?action=add-policy-position', {
        politician_id: selectedPolitician.id,
        ...newPositionForm
      });

      toast.success('Policy position added successfully');
      fetchPoliticianDetails(selectedPolitician.id);
      setNewPositionForm({
        position_type: '',
        stance: '',
        source_type: '',
        source_url: '',
        confidence_level: 'confirmed'
      });
      setShowAddForm(false);
    } catch (error: any) {
      console.error('Error adding policy position:', error);
      toast.error(error.response?.data?.error || 'Failed to add policy position');
    }
  };

  const calculateScoreColor = (score: number | null) => {
    if (score === null) return 'text-gray-400';
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-green-400';
    if (score >= 40) return 'text-yellow-500';
    if (score >= 20) return 'text-orange-500';
    return 'text-red-500';
  };

  const getStanceColor = (stance: string) => {
    switch (stance) {
      case 'pro_cannabis': return 'bg-green-100 text-green-800';
      case 'anti_cannabis': return 'bg-red-100 text-red-800';
      case 'neutral': return 'bg-yellow-100 text-yellow-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getStanceLabel = (stance: string) => {
    switch (stance) {
      case 'pro_cannabis': return 'Pro-Cannabis';
      case 'anti_cannabis': return 'Anti-Cannabis';
      case 'neutral': return 'Neutral';
      default: return 'Unknown';
    }
  };

  return (
    <div className="max-w-7xl mx-auto p-6">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-2">Cannabis Policy Administration</h1>
        <p className="text-gray-600">Manage politician cannabis policy classifications and track legislative positions</p>
      </div>

      {/* Search and Filters */}
      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Search Politicians
            </label>
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search by name, position, or party..."
              className="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Cannabis Stance
            </label>
            <select
              value={stanceFilter}
              onChange={(e) => setStanceFilter(e.target.value)}
              className="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent"
            >
              <option value="all">All Stances</option>
              <option value="pro_cannabis">Pro-Cannabis</option>
              <option value="anti_cannabis">Anti-Cannabis</option>
              <option value="neutral">Neutral</option>
              <option value="unknown">Unknown</option>
            </select>
          </div>
          <div className="flex items-end">
            <button
              onClick={fetchPoliticians}
              disabled={loading}
              className="w-full bg-green-600 text-white px-4 py-3 rounded-md hover:bg-green-700 disabled:opacity-50"
            >
              {loading ? 'Searching...' : 'Search'}
            </button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Politicians List */}
        <div className="bg-white rounded-lg shadow">
          <div className="p-6 border-b">
            <h2 className="text-xl font-semibold">Politicians ({politicians.length})</h2>
          </div>
          <div className="max-h-96 overflow-y-auto">
            {politicians.map((politician) => (
              <div
                key={politician.id}
                onClick={() => fetchPoliticianDetails(politician.id)}
                className={`p-4 border-b cursor-pointer hover:bg-gray-50 ${
                  selectedPolitician?.id === politician.id ? 'bg-green-50 border-green-200' : ''
                }`}
              >
                <div className="flex justify-between items-start">
                  <div className="flex-1">
                    <h3 className="font-medium text-gray-900">{politician.name}</h3>
                    <p className="text-sm text-gray-600">{politician.position}</p>
                    <p className="text-sm text-gray-500">{politician.party} • {politician.state}</p>
                  </div>
                  <div className="text-right">
                    <span className={`inline-flex px-2 py-1 text-xs rounded-full ${getStanceColor(politician.cannabis_stance)}`}>
                      {getStanceLabel(politician.cannabis_stance)}
                    </span>
                    <p className={`text-sm font-medium mt-1 ${calculateScoreColor(politician.cannabis_score)}`}>
                      Score: {politician.cannabis_score ?? 'N/A'}
                    </p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Politician Details */}
        <div className="bg-white rounded-lg shadow">
          {selectedPolitician ? (
            <div>
              <div className="p-6 border-b">
                <h2 className="text-xl font-semibold mb-2">{selectedPolitician.name}</h2>
                <p className="text-gray-600">{selectedPolitician.position}</p>
                <div className="flex items-center gap-4 mt-3">
                  <span className={`inline-flex px-3 py-1 text-sm rounded-full ${getStanceColor(selectedPolitician.cannabis_stance)}`}>
                    {getStanceLabel(selectedPolitician.cannabis_stance)}
                  </span>
                  <span className={`text-lg font-bold ${calculateScoreColor(selectedPolitician.cannabis_score)}`}>
                    Score: {selectedPolitician.cannabis_score ?? 'N/A'}
                  </span>
                </div>
              </div>

              {/* Edit Form */}
              <div className="p-6 border-b bg-gray-50">
                <h3 className="text-lg font-medium mb-4">Update Cannabis Policy</h3>
                <form onSubmit={updateCannabisStance} className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Cannabis Stance
                      </label>
                      <select
                        value={editForm.cannabis_stance}
                        onChange={(e) => setEditForm({...editForm, cannabis_stance: e.target.value as Politician['cannabis_stance']})}
                        className="w-full p-2 border border-gray-300 rounded-md"
                        required
                      >
                        <option value="pro_cannabis">Pro-Cannabis</option>
                        <option value="anti_cannabis">Anti-Cannabis</option>
                        <option value="neutral">Neutral</option>
                        <option value="unknown">Unknown</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-2">
                        Cannabis Score (0-100)
                      </label>
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={editForm.cannabis_score}
                        onChange={(e) => setEditForm({...editForm, cannabis_score: e.target.value})}
                        className="w-full p-2 border border-gray-300 rounded-md"
                        placeholder="Enter score..."
                      />
                    </div>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                      Reason for Change
                    </label>
                    <textarea
                      value={editForm.reason}
                      onChange={(e) => setEditForm({...editForm, reason: e.target.value})}
                      rows={2}
                      className="w-full p-2 border border-gray-300 rounded-md"
                      placeholder="Explain the reason for this update..."
                    />
                  </div>
                  <button
                    type="submit"
                    className="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700"
                  >
                    Update Policy
                  </button>
                </form>
              </div>

              {/* Policy Positions */}
              <div className="p-6">
                <div className="flex justify-between items-center mb-4">
                  <h3 className="text-lg font-medium">Policy Positions ({policyPositions.length})</h3>
                  <button
                    onClick={() => setShowAddForm(!showAddForm)}
                    className="text-green-600 hover:text-green-800 font-medium"
                  >
                    {showAddForm ? 'Cancel' : 'Add Position'}
                  </button>
                </div>

                {showAddForm && (
                  <form onSubmit={addPolicyPosition} className="mb-6 p-4 bg-gray-50 rounded-lg space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                      <select
                        value={newPositionForm.position_type}
                        onChange={(e) => setNewPositionForm({...newPositionForm, position_type: e.target.value})}
                        className="p-2 border border-gray-300 rounded-md"
                        required
                      >
                        <option value="">Select Position Type</option>
                        <option value="legalization">Legalization</option>
                        <option value="decriminalization">Decriminalization</option>
                        <option value="medical_only">Medical Only</option>
                        <option value="expungement">Expungement</option>
                        <option value="taxation">Taxation</option>
                        <option value="licensing">Licensing</option>
                        <option value="social_equity">Social Equity</option>
                        <option value="home_cultivation">Home Cultivation</option>
                        <option value="public_consumption">Public Consumption</option>
                      </select>
                      <select
                        value={newPositionForm.stance}
                        onChange={(e) => setNewPositionForm({...newPositionForm, stance: e.target.value})}
                        className="p-2 border border-gray-300 rounded-md"
                        required
                      >
                        <option value="">Select Stance</option>
                        <option value="strongly_support">Strongly Support</option>
                        <option value="support">Support</option>
                        <option value="neutral">Neutral</option>
                        <option value="oppose">Oppose</option>
                        <option value="strongly_oppose">Strongly Oppose</option>
                      </select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <select
                        value={newPositionForm.source_type}
                        onChange={(e) => setNewPositionForm({...newPositionForm, source_type: e.target.value})}
                        className="p-2 border border-gray-300 rounded-md"
                        required
                      >
                        <option value="">Select Source Type</option>
                        <option value="voting_record">Voting Record</option>
                        <option value="public_statement">Public Statement</option>
                        <option value="campaign_platform">Campaign Platform</option>
                        <option value="endorsement">Endorsement</option>
                        <option value="news_report">News Report</option>
                        <option value="interview">Interview</option>
                        <option value="survey_response">Survey Response</option>
                      </select>
                      <input
                        type="url"
                        value={newPositionForm.source_url}
                        onChange={(e) => setNewPositionForm({...newPositionForm, source_url: e.target.value})}
                        placeholder="Source URL (optional)"
                        className="p-2 border border-gray-300 rounded-md"
                      />
                    </div>
                    <div className="flex justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => setShowAddForm(false)}
                        className="px-3 py-2 text-gray-600 hover:text-gray-800"
                      >
                        Cancel
                      </button>
                      <button
                        type="submit"
                        className="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700"
                      >
                        Add Position
                      </button>
                    </div>
                  </form>
                )}

                <div className="space-y-3 max-h-64 overflow-y-auto">
                  {policyPositions.map((position) => (
                    <div key={position.id} className="border border-gray-200 rounded-lg p-3">
                      <div className="flex justify-between items-start">
                        <div>
                          <h4 className="font-medium capitalize">
                            {position.position_type.replace('_', ' ')}
                          </h4>
                          <p className={`text-sm mt-1 ${
                            position.stance.includes('support') ? 'text-green-600' : 
                            position.stance.includes('oppose') ? 'text-red-600' : 'text-yellow-600'
                          }`}>
                            {position.stance.replace('_', ' ')}
                          </p>
                        </div>
                        <div className="text-right text-xs text-gray-500">
                          <p>{position.confidence_level}</p>
                          <p>{position.source_type.replace('_', ' ')}</p>
                          {position.verified_by && <CheckIcon className="w-4 h-4 text-green-500 inline" />}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          ) : (
            <div className="p-6 text-center text-gray-500">
              <p>Select a politician to view and edit their cannabis policy information</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CannabisPolicyAdmin;