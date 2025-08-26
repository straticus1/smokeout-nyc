-- Social Trading Database Schema
-- Enables users to copy successful growers' strategies

-- Trading strategies created by users
CREATE TABLE IF NOT EXISTS trading_strategies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    creator_user_id INT NOT NULL,
    strategy_name VARCHAR(150) NOT NULL,
    description TEXT,
    strategy_category ENUM('conservative', 'aggressive', 'balanced', 'high_yield', 'fast_growth', 'premium_strains') DEFAULT 'balanced',
    strategy_config JSON, -- Strategy rules and parameters
    is_public BOOLEAN DEFAULT TRUE,
    copy_fee_percentage DECIMAL(5,2) DEFAULT 5.00, -- Fee charged to copiers
    total_trades INT DEFAULT 0,
    successful_trades INT DEFAULT 0,
    win_rate DECIMAL(5,2) DEFAULT 0.00,
    total_invested DECIMAL(12,2) DEFAULT 0.00,
    total_profit DECIMAL(12,2) DEFAULT 0.00,
    max_drawdown DECIMAL(5,2) DEFAULT 0.00,
    performance_history JSON, -- Historical performance data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Users copying trading strategies
CREATE TABLE IF NOT EXISTS trading_copiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    copier_user_id INT NOT NULL,
    strategy_id INT NOT NULL,
    investment_amount DECIMAL(10,2) NOT NULL,
    copy_percentage DECIMAL(5,2) DEFAULT 100.00, -- % of trades to copy
    total_invested DECIMAL(12,2) DEFAULT 0.00,
    current_value DECIMAL(12,2) DEFAULT 0.00,
    total_profit DECIMAL(12,2) DEFAULT 0.00,
    final_value DECIMAL(12,2) NULL, -- Value when position closed
    performance_rating INT NULL, -- 1-5 rating given by copier
    status ENUM('active', 'paused', 'closed') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    FOREIGN KEY (copier_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (strategy_id) REFERENCES trading_strategies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_copier_strategy (copier_user_id, strategy_id, status)
);

-- Individual trades executed by strategies
CREATE TABLE IF NOT EXISTS strategy_trades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    strategy_id INT NOT NULL,
    trade_type ENUM('plant', 'sell', 'buy_seeds', 'upgrade') NOT NULL,
    strain_id INT NULL,
    location_id INT NULL,
    quantity DECIMAL(8,2),
    price_per_unit DECIMAL(8,2),
    total_amount DECIMAL(10,2),
    profit_loss DECIMAL(10,2) DEFAULT 0.00,
    trade_reason TEXT, -- Why this trade was made
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (strategy_id) REFERENCES trading_strategies(id) ON DELETE CASCADE,
    FOREIGN KEY (strain_id) REFERENCES strains(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
);

-- Copied trades executed for followers
CREATE TABLE IF NOT EXISTS copied_trades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    copier_id INT NOT NULL,
    original_trade_id INT NOT NULL,
    executed_amount DECIMAL(10,2), -- Actual amount executed (may be scaled)
    execution_price DECIMAL(8,2),
    profit_loss DECIMAL(10,2) DEFAULT 0.00,
    execution_delay_seconds INT DEFAULT 0, -- Delay from original trade
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (copier_id) REFERENCES trading_copiers(id) ON DELETE CASCADE,
    FOREIGN KEY (original_trade_id) REFERENCES strategy_trades(id) ON DELETE CASCADE
);

-- Strategy performance metrics
CREATE TABLE IF NOT EXISTS strategy_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    strategy_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_trades INT DEFAULT 0,
    winning_trades INT DEFAULT 0,
    total_invested DECIMAL(12,2) DEFAULT 0.00,
    total_profit DECIMAL(12,2) DEFAULT 0.00,
    max_drawdown DECIMAL(5,2) DEFAULT 0.00,
    sharpe_ratio DECIMAL(6,3) DEFAULT 0.000,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (strategy_id) REFERENCES trading_strategies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_strategy_period (strategy_id, period_start, period_end)
);

-- Social features for strategies
CREATE TABLE IF NOT EXISTS strategy_social (
    id INT PRIMARY KEY AUTO_INCREMENT,
    strategy_id INT NOT NULL,
    user_id INT NOT NULL,
    action_type ENUM('like', 'follow', 'comment', 'share') NOT NULL,
    comment_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (strategy_id) REFERENCES trading_strategies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_strategy_action (user_id, strategy_id, action_type)
);

-- Strategy alerts and notifications
CREATE TABLE IF NOT EXISTS strategy_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    strategy_id INT NOT NULL,
    alert_type ENUM('new_trade', 'performance_milestone', 'drawdown_warning', 'strategy_update') NOT NULL,
    alert_message TEXT NOT NULL,
    alert_data JSON, -- Additional alert data
    is_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (strategy_id) REFERENCES trading_strategies(id) ON DELETE CASCADE
);

-- Insert sample trading strategies
INSERT INTO trading_strategies (creator_user_id, strategy_name, description, strategy_category, strategy_config, copy_fee_percentage) VALUES
(1, 'High Yield Indica Master', 'Focus on high-yielding indica strains with premium locations', 'high_yield', 
 '{"preferred_strains": ["indica"], "min_yield": 50, "preferred_locations": ["premium"], "risk_tolerance": "medium", "reinvestment_rate": 0.7}', 7.5),

(1, 'Fast Growth Sativa Rush', 'Quick turnaround sativa strategy for active traders', 'fast_growth',
 '{"preferred_strains": ["sativa"], "growth_speed_priority": true, "max_growth_days": 45, "profit_target": 0.3}', 5.0),

(2, 'Balanced Portfolio Approach', 'Diversified strategy across strains and locations', 'balanced',
 '{"strain_diversity": true, "location_diversity": true, "risk_management": true, "stop_loss": 0.15}', 4.0),

(2, 'Premium Strain Collector', 'Focus on rare and exclusive strains for maximum profit', 'premium_strains',
 '{"min_rarity": "rare", "premium_locations_only": true, "patience_strategy": true, "min_roi": 0.5}', 10.0);

-- Insert sample performance data
INSERT INTO strategy_performance (strategy_id, period_start, period_end, total_trades, winning_trades, total_invested, total_profit, max_drawdown, sharpe_ratio) VALUES
(1, '2024-01-01', '2024-01-31', 25, 18, 5000.00, 1250.00, 8.5, 1.45),
(1, '2024-02-01', '2024-02-29', 22, 16, 4800.00, 1100.00, 6.2, 1.38),
(2, '2024-01-01', '2024-01-31', 35, 22, 3500.00, 875.00, 12.1, 1.12),
(2, '2024-02-01', '2024-02-29', 28, 19, 3200.00, 720.00, 9.8, 1.08),
(3, '2024-01-01', '2024-01-31', 18, 14, 2500.00, 500.00, 5.5, 1.25),
(4, '2024-01-01', '2024-01-31', 12, 10, 8000.00, 2400.00, 15.2, 1.67);

-- Update strategies with calculated performance
UPDATE trading_strategies SET 
    total_trades = (SELECT SUM(total_trades) FROM strategy_performance WHERE strategy_id = trading_strategies.id),
    successful_trades = (SELECT SUM(winning_trades) FROM strategy_performance WHERE strategy_id = trading_strategies.id),
    win_rate = (SELECT AVG(winning_trades / NULLIF(total_trades, 0) * 100) FROM strategy_performance WHERE strategy_id = trading_strategies.id),
    total_invested = (SELECT SUM(total_invested) FROM strategy_performance WHERE strategy_id = trading_strategies.id),
    total_profit = (SELECT SUM(total_profit) FROM strategy_performance WHERE strategy_id = trading_strategies.id),
    max_drawdown = (SELECT MAX(max_drawdown) FROM strategy_performance WHERE strategy_id = trading_strategies.id);

-- Create indexes for performance
CREATE INDEX idx_trading_strategies_category ON trading_strategies(strategy_category);
CREATE INDEX idx_trading_strategies_public ON trading_strategies(is_public);
CREATE INDEX idx_trading_copiers_status ON trading_copiers(status);
CREATE INDEX idx_strategy_trades_strategy ON strategy_trades(strategy_id);
CREATE INDEX idx_strategy_trades_executed ON strategy_trades(executed_at);
CREATE INDEX idx_copied_trades_copier ON copied_trades(copier_id);
CREATE INDEX idx_strategy_performance_period ON strategy_performance(period_start, period_end);

-- Create stored procedure for executing copied trades
DELIMITER //
CREATE PROCEDURE ExecuteCopiedTrade(
    IN p_copier_id INT,
    IN p_original_trade_id INT,
    IN p_scale_factor DECIMAL(5,4) DEFAULT 1.0000
)
BEGIN
    DECLARE v_copier_user_id INT;
    DECLARE v_strategy_id INT;
    DECLARE v_trade_amount DECIMAL(10,2);
    DECLARE v_scaled_amount DECIMAL(10,2);
    DECLARE v_player_id INT;
    DECLARE v_player_tokens DECIMAL(10,2);
    
    -- Get copier details
    SELECT copier_user_id, strategy_id INTO v_copier_user_id, v_strategy_id
    FROM trading_copiers WHERE id = p_copier_id;
    
    -- Get original trade amount
    SELECT total_amount INTO v_trade_amount
    FROM strategy_trades WHERE id = p_original_trade_id;
    
    -- Calculate scaled amount
    SET v_scaled_amount = v_trade_amount * p_scale_factor;
    
    -- Get player details
    SELECT id, tokens INTO v_player_id, v_player_tokens
    FROM game_players WHERE user_id = v_copier_user_id;
    
    -- Check if player has enough tokens
    IF v_player_tokens >= v_scaled_amount THEN
        -- Execute the copied trade
        INSERT INTO copied_trades 
        (copier_id, original_trade_id, executed_amount, execution_price, executed_at)
        SELECT p_copier_id, p_original_trade_id, v_scaled_amount, 
               st.price_per_unit, NOW()
        FROM strategy_trades st WHERE st.id = p_original_trade_id;
        
        -- Update copier's investment tracking
        UPDATE trading_copiers 
        SET total_invested = total_invested + v_scaled_amount,
            current_value = current_value + v_scaled_amount
        WHERE id = p_copier_id;
        
        SELECT 'success' as status, v_scaled_amount as executed_amount;
    ELSE
        SELECT 'insufficient_funds' as status, v_player_tokens as available_tokens;
    END IF;
END //
DELIMITER ;

-- Create stored procedure for strategy performance calculation
DELIMITER //
CREATE PROCEDURE CalculateStrategyPerformance(
    IN p_strategy_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    DECLARE v_total_trades INT DEFAULT 0;
    DECLARE v_winning_trades INT DEFAULT 0;
    DECLARE v_total_invested DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_total_profit DECIMAL(12,2) DEFAULT 0.00;
    DECLARE v_max_drawdown DECIMAL(5,2) DEFAULT 0.00;
    DECLARE v_win_rate DECIMAL(5,2);
    DECLARE v_roi DECIMAL(5,2);
    
    -- Calculate metrics
    SELECT 
        COUNT(*) as trades,
        COUNT(CASE WHEN profit_loss > 0 THEN 1 END) as wins,
        SUM(total_amount) as invested,
        SUM(profit_loss) as profit
    INTO v_total_trades, v_winning_trades, v_total_invested, v_total_profit
    FROM strategy_trades
    WHERE strategy_id = p_strategy_id
    AND DATE(executed_at) BETWEEN p_start_date AND p_end_date;
    
    -- Calculate win rate and ROI
    SET v_win_rate = CASE WHEN v_total_trades > 0 THEN (v_winning_trades / v_total_trades) * 100 ELSE 0 END;
    SET v_roi = CASE WHEN v_total_invested > 0 THEN (v_total_profit / v_total_invested) * 100 ELSE 0 END;
    
    -- Insert or update performance record
    INSERT INTO strategy_performance 
    (strategy_id, period_start, period_end, total_trades, winning_trades, 
     total_invested, total_profit, max_drawdown, sharpe_ratio)
    VALUES (p_strategy_id, p_start_date, p_end_date, v_total_trades, v_winning_trades,
            v_total_invested, v_total_profit, v_max_drawdown, 0.000)
    ON DUPLICATE KEY UPDATE
        total_trades = v_total_trades,
        winning_trades = v_winning_trades,
        total_invested = v_total_invested,
        total_profit = v_total_profit,
        calculated_at = NOW();
    
    -- Update main strategy record
    UPDATE trading_strategies 
    SET total_trades = v_total_trades,
        successful_trades = v_winning_trades,
        win_rate = v_win_rate,
        total_invested = v_total_invested,
        total_profit = v_total_profit,
        updated_at = NOW()
    WHERE id = p_strategy_id;
    
    SELECT v_total_trades as total_trades, v_winning_trades as winning_trades,
           v_win_rate as win_rate, v_roi as roi_percentage;
END //
DELIMITER ;

-- Create view for strategy leaderboard
CREATE VIEW strategy_leaderboard AS
SELECT 
    ts.id,
    ts.strategy_name,
    ts.strategy_category,
    u.username as creator_name,
    ts.total_profit / NULLIF(ts.total_invested, 0) * 100 as roi_percentage,
    ts.win_rate,
    ts.total_trades,
    COUNT(DISTINCT tc.id) as total_copiers,
    SUM(tc.investment_amount) as total_copied_amount,
    AVG(tc.performance_rating) as avg_rating,
    ts.created_at,
    RANK() OVER (ORDER BY ts.total_profit / NULLIF(ts.total_invested, 0) DESC) as rank_position
FROM trading_strategies ts
JOIN users u ON ts.creator_user_id = u.id
LEFT JOIN trading_copiers tc ON ts.id = tc.strategy_id AND tc.status = 'active'
WHERE ts.is_public = TRUE AND ts.total_trades >= 5
GROUP BY ts.id
ORDER BY roi_percentage DESC;

-- Create view for user trading portfolio
CREATE VIEW user_trading_portfolio AS
SELECT 
    u.id as user_id,
    u.username,
    -- Created strategies stats
    COUNT(DISTINCT ts.id) as strategies_created,
    COALESCE(SUM(ts.total_profit), 0) as total_strategy_profit,
    COALESCE(AVG(ts.win_rate), 0) as avg_win_rate,
    -- Copy trading stats
    COUNT(DISTINCT tc.id) as strategies_copied,
    COALESCE(SUM(tc.total_invested), 0) as total_copy_invested,
    COALESCE(SUM(tc.current_value), 0) as total_copy_value,
    COALESCE(SUM(tc.current_value - tc.total_invested), 0) as total_copy_profit,
    -- Earnings from copy fees
    COALESCE(copy_fees.total_fees, 0) as copy_fees_earned
FROM users u
LEFT JOIN trading_strategies ts ON u.id = ts.creator_user_id
LEFT JOIN trading_copiers tc ON u.id = tc.copier_user_id AND tc.status = 'active'
LEFT JOIN (
    SELECT gp.user_id, SUM(gt.amount) as total_fees
    FROM game_transactions gt
    JOIN game_players gp ON gt.player_id = gp.id
    WHERE gt.transaction_type = 'copy_fee_earned'
    GROUP BY gp.user_id
) copy_fees ON u.id = copy_fees.user_id
GROUP BY u.id, u.username;
