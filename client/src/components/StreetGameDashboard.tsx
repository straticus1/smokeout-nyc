import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    Shield, 
    Zap, 
    Users, 
    MapPin, 
    Crown, 
    AlertTriangle,
    DollarSign,
    Target
} from 'lucide-react';

interface PlayerStats {
    id: number;
    current_level: number;
    experience_points: number;
    total_experience: number;
    reputation_score: number;
    street_cred: number;
    respect_level: string;
    bodyguard_level: number;
    security_budget: number;
    corrupt_cop_network: number;
    territories_controlled: number;
    daily_territory_revenue: number;
}

interface Dealer {
    id: number;
    name: string;
    nickname: string;
    territory_name: string;
    borough: string;
    aggression_level: string;
    violence_tendency: number;
    customer_base: number;
    recent_actions: number;
}

interface DealerAction {
    id: number;
    dealer_name: string;
    nickname: string;
    territory_name: string;
    action_type: string;
    severity: string;
    outcome_description: string;
    player_response: string;
    occurred_at: string;
}

interface Territory {
    id: number;
    name: string;
    borough: string;
    police_presence: string;
    customer_demand: number;
    player_control: number;
    control_status: string;
    daily_revenue: number;
    active_dealers: number;
}

const StreetGameDashboard: React.FC = () => {
    const [playerStats, setPlayerStats] = useState<PlayerStats | null>(null);
    const [dealers, setDealers] = useState<Dealer[]>([]);
    const [dealerActions, setDealerActions] = useState<DealerAction[]>([]);
    const [territories, setTerritories] = useState<Territory[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const API_BASE = 'http://localhost:8080/api/street_game.php';
    const AUTH_TOKEN = 'user_1'; // For testing

    useEffect(() => {
        fetchGameData();
    }, []);

    const fetchGameData = async () => {
        try {
            setLoading(true);
            const headers = {
                'Authorization': `Bearer ${AUTH_TOKEN}`,
                'Content-Type': 'application/json'
            };

            // Fetch player stats
            const statsResponse = await fetch(`${API_BASE}?action=get_player_stats`, { headers });
            if (statsResponse.ok) {
                setPlayerStats(await statsResponse.json());
            }

            // Fetch dealers
            const dealersResponse = await fetch(`${API_BASE}?action=get_active_dealers`, { headers });
            if (dealersResponse.ok) {
                setDealers(await dealersResponse.json());
            }

            // Fetch dealer actions
            const actionsResponse = await fetch(`${API_BASE}?action=get_dealer_actions`, { headers });
            if (actionsResponse.ok) {
                setDealerActions(await actionsResponse.json());
            }

            // Fetch territories
            const territoriesResponse = await fetch(`${API_BASE}?action=get_territories`, { headers });
            if (territoriesResponse.ok) {
                setTerritories(await territoriesResponse.json());
            }
        } catch (err) {
            setError('Failed to load game data');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    const handleAddExperience = async () => {
        try {
            const response = await fetch(`${API_BASE}?action=add_experience`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${AUTH_TOKEN}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    experience: 500,
                    reason: 'Demo activity'
                })
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    fetchGameData(); // Refresh data
                }
            }
        } catch (err) {
            console.error('Failed to add experience:', err);
        }
    };

    const handleRespondToDealer = async (actionId: number, response: string) => {
        try {
            const res = await fetch(`${API_BASE}?action=respond_to_dealer`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${AUTH_TOKEN}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action_id: actionId,
                    response: response
                })
            });

            if (res.ok) {
                fetchGameData(); // Refresh data
            }
        } catch (err) {
            console.error('Failed to respond to dealer:', err);
        }
    };

    const handleExpandTerritory = async (territoryId: number) => {
        try {
            const res = await fetch(`${API_BASE}?action=expand_territory`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${AUTH_TOKEN}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    territory_id: territoryId,
                    investment: 1000
                })
            });

            if (res.ok) {
                fetchGameData(); // Refresh data
            }
        } catch (err) {
            console.error('Failed to expand territory:', err);
        }
    };

    if (loading) return <div className="p-4">Loading street game...</div>;
    if (error) return <Alert><AlertDescription>{error}</AlertDescription></Alert>;
    if (!playerStats) return <div className="p-4">No player data available</div>;

    const getAggressionColor = (level: string) => {
        switch (level) {
            case 'passive': return 'bg-green-100 text-green-800';
            case 'moderate': return 'bg-yellow-100 text-yellow-800';
            case 'aggressive': return 'bg-red-100 text-red-800';
            case 'violent': return 'bg-red-200 text-red-900';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getSeverityColor = (severity: string) => {
        switch (severity) {
            case 'minor': return 'bg-blue-100 text-blue-800';
            case 'moderate': return 'bg-yellow-100 text-yellow-800';
            case 'serious': return 'bg-orange-100 text-orange-800';
            case 'severe': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    return (
        <div className="container mx-auto p-6 space-y-6">
            <h1 className="text-3xl font-bold mb-6">NYC Street Game Dashboard</h1>

            {/* Player Stats */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Crown className="w-5 h-5" />
                        Player Profile
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="text-center">
                            <div className="text-2xl font-bold">{playerStats.current_level}</div>
                            <div className="text-sm text-gray-600">Level</div>
                            <Badge variant="outline">{playerStats.respect_level}</Badge>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold">{playerStats.experience_points}</div>
                            <div className="text-sm text-gray-600">Experience</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold">{playerStats.reputation_score}</div>
                            <div className="text-sm text-gray-600">Reputation</div>
                        </div>
                        <div className="text-center">
                            <div className="text-2xl font-bold">{playerStats.territories_controlled}</div>
                            <div className="text-sm text-gray-600">Territories</div>
                        </div>
                    </div>
                    <Button onClick={handleAddExperience} className="mt-4">
                        <Zap className="w-4 h-4 mr-2" />
                        Add Experience (Demo)
                    </Button>
                </CardContent>
            </Card>

            {/* Street Dealers */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Users className="w-5 h-5" />
                        Street Dealers ({dealers.length})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4">
                        {dealers.map(dealer => (
                            <div key={dealer.id} className="border rounded-lg p-4">
                                <div className="flex justify-between items-start">
                                    <div>
                                        <h3 className="font-semibold">{dealer.name} "{dealer.nickname}"</h3>
                                        <p className="text-sm text-gray-600">
                                            {dealer.territory_name}, {dealer.borough}
                                        </p>
                                        <div className="flex gap-2 mt-2">
                                            <Badge className={getAggressionColor(dealer.aggression_level)}>
                                                {dealer.aggression_level}
                                            </Badge>
                                            <Badge variant="outline">
                                                Violence: {dealer.violence_tendency}%
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-sm">
                                            {dealer.customer_base} customers
                                        </div>
                                        <div className="text-sm text-red-600">
                                            {dealer.recent_actions} recent actions
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                        {dealers.length === 0 && (
                            <p className="text-gray-600">No active dealers in your area yet.</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Dealer Actions */}
            {dealerActions.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5" />
                            Recent Dealer Actions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {dealerActions.slice(0, 5).map(action => (
                                <div key={action.id} className="border rounded-lg p-4">
                                    <div className="flex justify-between items-start mb-2">
                                        <h4 className="font-semibold">
                                            {action.dealer_name} "{action.nickname}"
                                        </h4>
                                        <Badge className={getSeverityColor(action.severity)}>
                                            {action.severity}
                                        </Badge>
                                    </div>
                                    <p className="text-sm mb-3">{action.outcome_description}</p>
                                    {action.player_response === 'ignore' && (
                                        <div className="flex gap-2 flex-wrap">
                                            <Button size="sm" onClick={() => handleRespondToDealer(action.id, 'negotiate')}>
                                                Negotiate
                                            </Button>
                                            <Button size="sm" variant="destructive" onClick={() => handleRespondToDealer(action.id, 'retaliate')}>
                                                Retaliate
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => handleRespondToDealer(action.id, 'bribe_cops')}>
                                                Bribe Cops
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => handleRespondToDealer(action.id, 'flee')}>
                                                Flee
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Territory Control */}
            {playerStats.current_level >= 20 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MapPin className="w-5 h-5" />
                            Territory Control
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4">
                            {territories.slice(0, 6).map(territory => (
                                <div key={territory.id} className="border rounded-lg p-4">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <h3 className="font-semibold">{territory.name}</h3>
                                            <p className="text-sm text-gray-600">{territory.borough}</p>
                                            <div className="flex gap-2 mt-2">
                                                <Badge variant="outline">
                                                    Police: {territory.police_presence}
                                                </Badge>
                                                <Badge variant="outline">
                                                    Demand: {territory.customer_demand}
                                                </Badge>
                                                {territory.active_dealers > 0 && (
                                                    <Badge variant="destructive">
                                                        {territory.active_dealers} dealers
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-lg font-bold">
                                                {territory.player_control}% control
                                            </div>
                                            {territory.daily_revenue > 0 && (
                                                <div className="text-sm text-green-600">
                                                    ${territory.daily_revenue}/day
                                                </div>
                                            )}
                                            <Button 
                                                size="sm" 
                                                className="mt-2"
                                                onClick={() => handleExpandTerritory(territory.id)}
                                            >
                                                <Target className="w-4 h-4 mr-1" />
                                                Expand ($1000)
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Level Progression Info */}
            {playerStats.current_level < 10 && (
                <Alert>
                    <AlertTriangle className="w-4 h-4" />
                    <AlertDescription>
                        You're still building your reputation. Street dealers will start appearing at level 10.
                        Keep growing and making moves to level up!
                    </AlertDescription>
                </Alert>
            )}

            {playerStats.current_level >= 10 && playerStats.current_level < 15 && (
                <Alert>
                    <AlertTriangle className="w-4 h-4" />
                    <AlertDescription>
                        Street dealers are now active in your area. At level 15, you'll be able to access corrupt cops for protection.
                    </AlertDescription>
                </Alert>
            )}

            {playerStats.current_level >= 15 && playerStats.current_level < 20 && (
                <Alert>
                    <Shield className="w-4 h-4" />
                    <AlertDescription>
                        You can now bribe corrupt cops for protection. Territory control becomes available at level 20.
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
};

export default StreetGameDashboard;