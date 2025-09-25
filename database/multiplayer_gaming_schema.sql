-- Multiplayer Gaming System Schema
-- Enables P2P and Player vs Computer/AI gameplay
-- SmokeoutNYC v2.5 - Multiplayer Expansion

-- Game Sessions for Multiplayer Matches
CREATE TABLE IF NOT EXISTS game_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_type ENUM('p2p', 'pvc', 'tournament', 'league') NOT NULL,
    game_mode ENUM('territory_wars', 'dealer_showdown', 'empire_clash', 'street_race', 'heist_coop') NOT NULL,
    max_players INT DEFAULT 2,
    current_players INT DEFAULT 0,
    status ENUM('waiting', 'active', 'paused', 'completed', 'cancelled') DEFAULT 'waiting',
    host_user_id INT NOT NULL,
    settings JSON, -- Game-specific settings
    stakes DECIMAL(10,2) DEFAULT 0.00, -- Entry fee or bet amount
    prize_pool DECIMAL(10,2) DEFAULT 0.00,
    duration_minutes INT DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    winner_id INT NULL,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_status (status, session_type),
    INDEX idx_host (host_user_id),
    INDEX idx_game_mode (game_mode)
);

-- Players in Game Sessions
CREATE TABLE IF NOT EXISTS session_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT, -- NULL for AI players
    is_ai BOOLEAN DEFAULT FALSE,
    ai_difficulty ENUM('easy', 'medium', 'hard', 'expert') DEFAULT 'medium',
    ai_personality VARCHAR(100), -- AI character name/type
    player_position INT NOT NULL, -- Player 1, 2, 3, etc.
    status ENUM('waiting', 'ready', 'active', 'disconnected', 'eliminated', 'finished') DEFAULT 'waiting',
    score INT DEFAULT 0,
    resources JSON, -- Money, territories, reputation at game start
    current_stats JSON, -- Real-time game stats
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ready_at TIMESTAMP NULL,
    eliminated_at TIMESTAMP NULL,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_session_user (session_id, user_id),
    INDEX idx_session_players (session_id, status)
);

-- Real-time Game Actions and Moves
CREATE TABLE IF NOT EXISTS game_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    player_id INT NOT NULL, -- session_players.id
    action_type ENUM('move', 'attack', 'trade', 'build', 'negotiate', 'special_ability', 'surrender') NOT NULL,
    target_player_id INT, -- For actions targeting other players
    target_resource VARCHAR(100), -- Territory, dealer, asset being targeted
    action_data JSON, -- Specific action parameters
    success BOOLEAN DEFAULT TRUE,
    consequences JSON, -- Results of the action
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES session_players(id) ON DELETE CASCADE,
    FOREIGN KEY (target_player_id) REFERENCES session_players(id) ON DELETE SET NULL,
    INDEX idx_session_actions (session_id, timestamp),
    INDEX idx_player_actions (player_id, timestamp)
);

-- AI Opponents Configuration
CREATE TABLE IF NOT EXISTS ai_opponents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    personality_type ENUM('aggressive', 'defensive', 'opportunistic', 'cooperative', 'unpredictable') NOT NULL,
    difficulty_level ENUM('easy', 'medium', 'hard', 'expert') NOT NULL,
    specialties JSON, -- What this AI is good at
    weaknesses JSON, -- What this AI struggles with
    behavior_patterns JSON, -- Decision-making algorithms
    backstory TEXT,
    avatar_image VARCHAR(255),
    win_rate DECIMAL(5,2) DEFAULT 0.00,
    games_played INT DEFAULT 0,
    reputation_score INT DEFAULT 1000,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Player vs Player Challenges
CREATE TABLE IF NOT EXISTS player_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenger_id INT NOT NULL,
    challenged_id INT NOT NULL,
    challenge_type ENUM('duel', 'territory_dispute', 'reputation_match', 'high_stakes') NOT NULL,
    stakes DECIMAL(10,2) DEFAULT 0.00,
    message TEXT,
    conditions JSON, -- Special rules or conditions
    status ENUM('pending', 'accepted', 'declined', 'active', 'completed', 'expired') DEFAULT 'pending',
    session_id INT NULL, -- Links to actual game session when accepted
    winner_id INT NULL,
    loser_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (challenger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenged_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (loser_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pending_challenges (challenged_id, status),
    INDEX idx_challenge_type (challenge_type, status)
);

-- Leaderboards and Rankings
CREATE TABLE IF NOT EXISTS player_rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ranking_type ENUM('overall', 'weekly', 'monthly', 'p2p', 'pvc', 'territory_wars', 'dealer_showdown') NOT NULL,
    rank_position INT NOT NULL,
    rating DECIMAL(8,2) DEFAULT 1200.00, -- ELO-style rating
    games_played INT DEFAULT 0,
    games_won INT DEFAULT 0,
    games_lost INT DEFAULT 0,
    win_streak INT DEFAULT 0,
    best_win_streak INT DEFAULT 0,
    total_earnings DECIMAL(12,2) DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_user_ranking (user_id, ranking_type),
    INDEX idx_ranking (ranking_type, rank_position),
    INDEX idx_rating (ranking_type, rating DESC)
);

-- Tournaments and Leagues
CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    tournament_type ENUM('single_elimination', 'double_elimination', 'round_robin', 'league', 'ladder') NOT NULL,
    entry_fee DECIMAL(10,2) DEFAULT 0.00,
    prize_pool DECIMAL(12,2) DEFAULT 0.00,
    max_participants INT DEFAULT 16,
    current_participants INT DEFAULT 0,
    min_level INT DEFAULT 1,
    status ENUM('registration', 'active', 'completed', 'cancelled') DEFAULT 'registration',
    registration_ends TIMESTAMP,
    starts_at TIMESTAMP,
    ends_at TIMESTAMP,
    winner_id INT NULL,
    rules JSON,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (winner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tournament_status (status, starts_at)
);

-- Tournament Participants
CREATE TABLE IF NOT EXISTS tournament_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    user_id INT NOT NULL,
    seed_number INT,
    current_round INT DEFAULT 1,
    status ENUM('active', 'eliminated', 'bye', 'winner') DEFAULT 'active',
    total_score INT DEFAULT 0,
    matches_won INT DEFAULT 0,
    matches_lost INT DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminated_at TIMESTAMP NULL,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tournament_user (tournament_id, user_id),
    INDEX idx_tournament_standings (tournament_id, total_score DESC)
);

-- Real-time Chat and Communication
CREATE TABLE IF NOT EXISTS game_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    sender_id INT, -- NULL for system messages
    message TEXT NOT NULL,
    message_type ENUM('chat', 'emote', 'system', 'trade_offer', 'challenge') DEFAULT 'chat',
    is_private BOOLEAN DEFAULT FALSE,
    recipient_id INT NULL, -- For private messages
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES session_players(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES session_players(id) ON DELETE SET NULL,
    INDEX idx_session_chat (session_id, timestamp),
    INDEX idx_private_chat (recipient_id, is_private)
);

-- Spectator System
CREATE TABLE IF NOT EXISTS game_spectators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uk_session_spectator (session_id, user_id),
    INDEX idx_active_spectators (session_id, is_active)
);

-- Insert Sample AI Opponents
INSERT INTO ai_opponents (name, personality_type, difficulty_level, specialties, weaknesses, behavior_patterns, backstory, reputation_score) VALUES
('Tommy "The Tank" Morrison', 'aggressive', 'medium', '["territory_expansion", "intimidation", "brute_force"]', '["diplomacy", "long_term_planning"]', '{"aggression": 0.8, "risk_tolerance": 0.7, "cooperation": 0.2}', 'Former boxer turned street boss. Prefers direct confrontation over subtle tactics.', 1200),
('Sofia "The Strategist" Chen', 'defensive', 'hard', '["resource_management", "defensive_tactics", "market_analysis"]', '["quick_decisions", "high_pressure_situations"]', '{"aggression": 0.3, "risk_tolerance": 0.4, "cooperation": 0.6}', 'Economics PhD who applies business strategy to street operations.', 1450),
('Marcus "Wildcard" Rodriguez', 'unpredictable', 'expert', '["psychological_warfare", "surprise_attacks", "adaptation"]', '["consistency", "predictable_patterns"]', '{"aggression": 0.6, "risk_tolerance": 0.9, "cooperation": 0.4}', 'Chaotic genius who keeps opponents guessing with unconventional moves.', 1650),
('Diana "The Diplomat" Washington', 'cooperative', 'medium', '["negotiation", "alliance_building", "information_gathering"]', '["solo_operations", "aggressive_expansion"]', '{"aggression": 0.4, "risk_tolerance": 0.5, "cooperation": 0.8}', 'Former political aide who excels at building mutually beneficial relationships.', 1350),
('Vincent "The Vulture" Nakamura', 'opportunistic', 'hard', '["timing", "exploitation", "resource_acquisition"]', '["early_game_pressure", "sustained_attacks"]', '{"aggression": 0.5, "risk_tolerance": 0.6, "cooperation": 0.3}', 'Patient predator who waits for the perfect moment to strike at weakened opponents.', 1500);

-- Create indexes for performance
CREATE INDEX idx_active_sessions ON game_sessions(status, game_mode, created_at);
CREATE INDEX idx_waiting_sessions ON game_sessions(status, session_type, max_players, current_players);
CREATE INDEX idx_player_stats ON session_players(user_id, status, score);
CREATE INDEX idx_recent_actions ON game_actions(session_id, timestamp DESC);
CREATE INDEX idx_ai_difficulty ON ai_opponents(difficulty_level, is_active);

-- Create views for game matchmaking
CREATE OR REPLACE VIEW available_sessions AS
SELECT 
    gs.*,
    (gs.max_players - gs.current_players) as open_slots,
    u.username as host_username,
    AVG(pr.rating) as avg_player_rating
FROM game_sessions gs
JOIN users u ON gs.host_user_id = u.id
LEFT JOIN session_players sp ON gs.id = sp.session_id
LEFT JOIN player_rankings pr ON sp.user_id = pr.user_id AND pr.ranking_type = 'overall'
WHERE gs.status = 'waiting' 
    AND gs.current_players < gs.max_players
GROUP BY gs.id;

CREATE OR REPLACE VIEW active_player_games AS
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

CREATE OR REPLACE VIEW multiplayer_leaderboard AS
SELECT 
    u.username,
    pr.rating,
    pr.games_played,
    pr.games_won,
    pr.games_lost,
    ROUND((pr.games_won / GREATEST(pr.games_played, 1)) * 100, 2) as win_percentage,
    pr.win_streak,
    pr.best_win_streak,
    pr.total_earnings,
    pr.rank_position
FROM player_rankings pr
JOIN users u ON pr.user_id = u.id
WHERE pr.ranking_type = 'overall'
ORDER BY pr.rank_position ASC;