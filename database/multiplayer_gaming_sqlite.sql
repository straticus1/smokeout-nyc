-- Multiplayer Gaming System Schema (SQLite)
-- Enables P2P and Player vs Computer/AI gameplay
-- SmokeoutNYC v2.5 - Multiplayer Expansion

-- Game Sessions for Multiplayer Matches
CREATE TABLE IF NOT EXISTS game_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_type TEXT CHECK(session_type IN ('p2p', 'pvc', 'tournament', 'league')) NOT NULL,
    game_mode TEXT CHECK(game_mode IN ('territory_wars', 'dealer_showdown', 'empire_clash', 'street_race', 'heist_coop')) NOT NULL,
    max_players INTEGER DEFAULT 2,
    current_players INTEGER DEFAULT 0,
    status TEXT CHECK(status IN ('waiting', 'active', 'paused', 'completed', 'cancelled')) DEFAULT 'waiting',
    host_user_id INTEGER NOT NULL,
    settings TEXT, -- JSON string for game-specific settings
    stakes REAL DEFAULT 0.00, -- Entry fee or bet amount
    prize_pool REAL DEFAULT 0.00,
    duration_minutes INTEGER DEFAULT 30,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    winner_id INTEGER NULL,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Players in Game Sessions
CREATE TABLE IF NOT EXISTS session_players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    user_id INTEGER, -- NULL for AI players
    is_ai INTEGER DEFAULT 0, -- SQLite boolean as integer
    ai_difficulty TEXT CHECK(ai_difficulty IN ('easy', 'medium', 'hard', 'expert')) DEFAULT 'medium',
    ai_personality TEXT, -- AI character name/type
    player_position INTEGER NOT NULL, -- Player 1, 2, 3, etc.
    status TEXT CHECK(status IN ('waiting', 'ready', 'active', 'disconnected', 'eliminated', 'finished')) DEFAULT 'waiting',
    score INTEGER DEFAULT 0,
    resources TEXT, -- JSON string for money, territories, reputation at game start
    current_stats TEXT, -- JSON string for real-time game stats
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ready_at DATETIME NULL,
    eliminated_at DATETIME NULL,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(session_id, user_id)
);

-- Real-time Game Actions and Moves
CREATE TABLE IF NOT EXISTS game_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    player_id INTEGER NOT NULL, -- session_players.id
    action_type TEXT CHECK(action_type IN ('move', 'attack', 'trade', 'build', 'negotiate', 'special_ability', 'surrender')) NOT NULL,
    target_player_id INTEGER, -- For actions targeting other players
    target_resource TEXT, -- Territory, dealer, asset being targeted
    action_data TEXT, -- JSON string for specific action parameters
    success INTEGER DEFAULT 1, -- SQLite boolean as integer
    consequences TEXT, -- JSON string for results of the action
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed INTEGER DEFAULT 0, -- SQLite boolean as integer
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES session_players(id) ON DELETE CASCADE,
    FOREIGN KEY (target_player_id) REFERENCES session_players(id) ON DELETE SET NULL
);

-- AI Opponents Configuration
CREATE TABLE IF NOT EXISTS ai_opponents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    personality_type TEXT CHECK(personality_type IN ('aggressive', 'defensive', 'opportunistic', 'cooperative', 'unpredictable')) NOT NULL,
    difficulty_level TEXT CHECK(difficulty_level IN ('easy', 'medium', 'hard', 'expert')) NOT NULL,
    specialties TEXT, -- JSON string for what this AI is good at
    weaknesses TEXT, -- JSON string for what this AI struggles with
    behavior_patterns TEXT, -- JSON string for decision-making algorithms
    backstory TEXT,
    avatar_image TEXT,
    win_rate REAL DEFAULT 0.00,
    games_played INTEGER DEFAULT 0,
    reputation_score INTEGER DEFAULT 1000,
    is_active INTEGER DEFAULT 1, -- SQLite boolean as integer
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Player vs Player Challenges
CREATE TABLE IF NOT EXISTS player_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    challenger_id INTEGER NOT NULL,
    challenged_id INTEGER NOT NULL,
    challenge_type TEXT CHECK(challenge_type IN ('duel', 'territory_dispute', 'reputation_match', 'high_stakes')) NOT NULL,
    stakes REAL DEFAULT 0.00,
    message TEXT,
    conditions TEXT, -- JSON string for special rules or conditions
    status TEXT CHECK(status IN ('pending', 'accepted', 'declined', 'active', 'completed', 'expired')) DEFAULT 'pending',
    session_id INTEGER NULL, -- Links to actual game session when accepted
    winner_id INTEGER NULL,
    loser_id INTEGER NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT (datetime('now', '+24 hours')),
    completed_at DATETIME NULL,
    FOREIGN KEY (challenger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenged_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (loser_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Leaderboards and Rankings
CREATE TABLE IF NOT EXISTS player_rankings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    ranking_type TEXT CHECK(ranking_type IN ('overall', 'weekly', 'monthly', 'p2p', 'pvc', 'territory_wars', 'dealer_showdown')) NOT NULL,
    rank_position INTEGER NOT NULL,
    rating REAL DEFAULT 1200.00, -- ELO-style rating
    games_played INTEGER DEFAULT 0,
    games_won INTEGER DEFAULT 0,
    games_lost INTEGER DEFAULT 0,
    win_streak INTEGER DEFAULT 0,
    best_win_streak INTEGER DEFAULT 0,
    total_earnings REAL DEFAULT 0.00,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, ranking_type)
);

-- Tournaments and Leagues
CREATE TABLE IF NOT EXISTS tournaments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    tournament_type TEXT CHECK(tournament_type IN ('single_elimination', 'double_elimination', 'round_robin', 'league', 'ladder')) NOT NULL,
    entry_fee REAL DEFAULT 0.00,
    prize_pool REAL DEFAULT 0.00,
    max_participants INTEGER DEFAULT 16,
    current_participants INTEGER DEFAULT 0,
    min_level INTEGER DEFAULT 1,
    status TEXT CHECK(status IN ('registration', 'active', 'completed', 'cancelled')) DEFAULT 'registration',
    registration_ends DATETIME,
    starts_at DATETIME,
    ends_at DATETIME,
    winner_id INTEGER NULL,
    rules TEXT, -- JSON string
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tournament Participants
CREATE TABLE IF NOT EXISTS tournament_participants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tournament_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    seed_number INTEGER,
    current_round INTEGER DEFAULT 1,
    status TEXT CHECK(status IN ('active', 'eliminated', 'bye', 'winner')) DEFAULT 'active',
    total_score INTEGER DEFAULT 0,
    matches_won INTEGER DEFAULT 0,
    matches_lost INTEGER DEFAULT 0,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    eliminated_at DATETIME NULL,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(tournament_id, user_id)
);

-- Real-time Chat and Communication
CREATE TABLE IF NOT EXISTS game_chat (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    sender_id INTEGER, -- NULL for system messages
    message TEXT NOT NULL,
    message_type TEXT CHECK(message_type IN ('chat', 'emote', 'system', 'trade_offer', 'challenge')) DEFAULT 'chat',
    is_private INTEGER DEFAULT 0, -- SQLite boolean as integer
    recipient_id INTEGER NULL, -- For private messages
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES session_players(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES session_players(id) ON DELETE SET NULL
);

-- Spectator System
CREATE TABLE IF NOT EXISTS game_spectators (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    is_active INTEGER DEFAULT 1, -- SQLite boolean as integer
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(session_id, user_id)
);

-- Insert Sample AI Opponents
INSERT OR REPLACE INTO ai_opponents (name, personality_type, difficulty_level, specialties, weaknesses, behavior_patterns, backstory, reputation_score) VALUES
('Tommy "The Tank" Morrison', 'aggressive', 'medium', '["territory_expansion", "intimidation", "brute_force"]', '["diplomacy", "long_term_planning"]', '{"aggression": 0.8, "risk_tolerance": 0.7, "cooperation": 0.2}', 'Former boxer turned street boss. Prefers direct confrontation over subtle tactics.', 1200),
('Sofia "The Strategist" Chen', 'defensive', 'hard', '["resource_management", "defensive_tactics", "market_analysis"]', '["quick_decisions", "high_pressure_situations"]', '{"aggression": 0.3, "risk_tolerance": 0.4, "cooperation": 0.6}', 'Economics PhD who applies business strategy to street operations.', 1450),
('Marcus "Wildcard" Rodriguez', 'unpredictable', 'expert', '["psychological_warfare", "surprise_attacks", "adaptation"]', '["consistency", "predictable_patterns"]', '{"aggression": 0.6, "risk_tolerance": 0.9, "cooperation": 0.4}', 'Chaotic genius who keeps opponents guessing with unconventional moves.', 1650),
('Diana "The Diplomat" Washington', 'cooperative', 'medium', '["negotiation", "alliance_building", "information_gathering"]', '["solo_operations", "aggressive_expansion"]', '{"aggression": 0.4, "risk_tolerance": 0.5, "cooperation": 0.8}', 'Former political aide who excels at building mutually beneficial relationships.', 1350),
('Vincent "The Vulture" Nakamura', 'opportunistic', 'hard', '["timing", "exploitation", "resource_acquisition"]', '["early_game_pressure", "sustained_attacks"]', '{"aggression": 0.5, "risk_tolerance": 0.6, "cooperation": 0.3}', 'Patient predator who waits for the perfect moment to strike at weakened opponents.', 1500);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_sessions_status ON game_sessions(status, session_type, created_at);
CREATE INDEX IF NOT EXISTS idx_sessions_waiting ON game_sessions(status, max_players, current_players);
CREATE INDEX IF NOT EXISTS idx_players_session ON session_players(session_id, status, user_id);
CREATE INDEX IF NOT EXISTS idx_actions_session ON game_actions(session_id, timestamp DESC);
CREATE INDEX IF NOT EXISTS idx_ai_difficulty ON ai_opponents(difficulty_level, is_active);
CREATE INDEX IF NOT EXISTS idx_challenges_status ON player_challenges(challenged_id, status);
CREATE INDEX IF NOT EXISTS idx_rankings_type ON player_rankings(ranking_type, rating DESC);

-- Create views for game matchmaking (simplified for SQLite)
CREATE VIEW IF NOT EXISTS available_sessions AS
SELECT 
    gs.*,
    (gs.max_players - gs.current_players) as open_slots,
    u.username as host_username
FROM game_sessions gs
JOIN users u ON gs.host_user_id = u.id
WHERE gs.status = 'waiting' 
    AND gs.current_players < gs.max_players;

CREATE VIEW IF NOT EXISTS active_player_games AS
SELECT 
    gs.id as session_id,
    gs.session_type,
    gs.game_mode,
    gs.status,
    sp.user_id,
    sp.player_position,
    sp.score,
    sp.status as player_status,
    gs.created_at,
    gs.started_at
FROM game_sessions gs
JOIN session_players sp ON gs.id = sp.session_id
WHERE gs.status IN ('active', 'waiting') 
    AND sp.status IN ('active', 'ready', 'waiting');

CREATE VIEW IF NOT EXISTS multiplayer_leaderboard AS
SELECT 
    u.username,
    pr.rating,
    pr.games_played,
    pr.games_won,
    pr.games_lost,
    ROUND((CAST(pr.games_won AS REAL) / MAX(pr.games_played, 1)) * 100, 2) as win_percentage,
    pr.win_streak,
    pr.best_win_streak,
    pr.total_earnings,
    pr.rank_position
FROM player_rankings pr
JOIN users u ON pr.user_id = u.id
WHERE pr.ranking_type = 'overall'
ORDER BY pr.rank_position ASC;