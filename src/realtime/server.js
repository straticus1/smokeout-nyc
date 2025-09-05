const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');
const mysql = require('mysql2/promise');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: process.env.CLIENT_ORIGIN || "*",
    methods: ["GET", "POST"]
  }
});

// Middleware
app.use(cors());
app.use(express.json());

// Database connection
const dbConfig = {
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'smokeoutnyc',
  port: process.env.DB_PORT || 3306,
  reconnect: true,
  acquireTimeout: 60000,
  timeout: 60000
};

let db;

// Initialize database connection
async function initDatabase() {
  try {
    db = await mysql.createConnection(dbConfig);
    console.log('✅ Database connected for realtime service');
  } catch (error) {
    console.error('❌ Database connection failed:', error);
    process.exit(1);
  }
}

// Socket.io connection handling
const connectedUsers = new Map();
const activeGames = new Map();
const chatRooms = new Map();

io.on('connection', (socket) => {
  console.log(`🔗 User connected: ${socket.id}`);

  // User authentication and registration
  socket.on('authenticate', async (data) => {
    try {
      const { userId, username, token } = data;
      
      // Verify JWT token (simplified - add proper JWT verification)
      if (token) {
        socket.userId = userId;
        socket.username = username;
        
        connectedUsers.set(socket.id, {
          userId,
          username,
          connectedAt: new Date(),
          lastActivity: new Date()
        });
        
        socket.emit('authenticated', { success: true });
        
        // Broadcast user online status
        socket.broadcast.emit('user_online', {
          userId,
          username
        });
        
        // Send current online users count
        io.emit('online_count', connectedUsers.size);
        
      } else {
        socket.emit('authentication_error', { message: 'Invalid token' });
      }
    } catch (error) {
      console.error('Authentication error:', error);
      socket.emit('authentication_error', { message: 'Authentication failed' });
    }
  });

  // Real-time gaming features
  socket.on('join_game_room', (data) => {
    const { gameId, gameType } = data;
    const roomId = `game_${gameId}`;
    
    socket.join(roomId);
    socket.gameRoom = roomId;
    
    if (!activeGames.has(roomId)) {
      activeGames.set(roomId, {
        gameId,
        gameType,
        players: [],
        startedAt: new Date()
      });
    }
    
    const gameRoom = activeGames.get(roomId);
    gameRoom.players.push({
      socketId: socket.id,
      userId: socket.userId,
      username: socket.username
    });
    
    // Notify room about new player
    socket.to(roomId).emit('player_joined', {
      userId: socket.userId,
      username: socket.username,
      playerCount: gameRoom.players.length
    });
  });

  // Game actions (for multiplayer gaming)
  socket.on('game_action', async (data) => {
    try {
      const { action, gameId, payload } = data;
      const roomId = `game_${gameId}`;
      
      // Log game action to database
      await db.execute(
        'INSERT INTO game_actions (user_id, game_id, action_type, payload, created_at) VALUES (?, ?, ?, ?, NOW())',
        [socket.userId, gameId, action, JSON.stringify(payload)]
      );
      
      // Broadcast action to other players in the room
      socket.to(roomId).emit('game_action', {
        userId: socket.userId,
        username: socket.username,
        action,
        payload,
        timestamp: new Date()
      });
      
    } catch (error) {
      console.error('Game action error:', error);
      socket.emit('error', { message: 'Failed to process game action' });
    }
  });

  // Chat system
  socket.on('join_chat', (data) => {
    const { chatType, chatId } = data; // guild, global, private
    const roomId = `chat_${chatType}_${chatId}`;
    
    socket.join(roomId);
    socket.chatRooms = socket.chatRooms || [];
    socket.chatRooms.push(roomId);
    
    socket.emit('chat_joined', { roomId, chatType, chatId });
  });

  socket.on('send_message', async (data) => {
    try {
      const { chatType, chatId, message, replyTo } = data;
      const roomId = `chat_${chatType}_${chatId}`;
      
      // Save message to database
      const [result] = await db.execute(
        'INSERT INTO chat_messages (sender_id, chat_type, chat_id, message, reply_to, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
        [socket.userId, chatType, chatId, message, replyTo || null]
      );
      
      const messageData = {
        id: result.insertId,
        senderId: socket.userId,
        senderName: socket.username,
        message,
        chatType,
        chatId,
        replyTo,
        timestamp: new Date()
      };
      
      // Broadcast message to room
      io.to(roomId).emit('new_message', messageData);
      
    } catch (error) {
      console.error('Chat error:', error);
      socket.emit('error', { message: 'Failed to send message' });
    }
  });

  // Notification system
  socket.on('mark_notification_read', async (data) => {
    try {
      const { notificationId } = data;
      
      await db.execute(
        'UPDATE notifications SET status = "read", read_at = NOW() WHERE id = ? AND user_id = ?',
        [notificationId, socket.userId]
      );
      
      socket.emit('notification_updated', { notificationId, status: 'read' });
      
    } catch (error) {
      console.error('Notification update error:', error);
    }
  });

  // Real-time analytics updates
  socket.on('subscribe_analytics', (data) => {
    const { dashboardType, filters } = data;
    socket.join(`analytics_${dashboardType}`);
    socket.analyticsSubscription = { dashboardType, filters };
  });

  // Trading/marketplace updates
  socket.on('watch_marketplace', (data) => {
    const { itemTypes, priceRange } = data;
    socket.join('marketplace');
    socket.marketplaceWatch = { itemTypes, priceRange };
  });

  // Risk updates
  socket.on('subscribe_risk_updates', (data) => {
    const { businessId, riskTypes } = data;
    socket.join(`risk_${businessId}`);
  });

  // Player status updates
  socket.on('update_status', async (data) => {
    try {
      const { status, location, activity } = data;
      
      await db.execute(
        'UPDATE user_status SET status = ?, location = ?, current_activity = ?, last_updated = NOW() WHERE user_id = ?',
        [status, location, activity, socket.userId]
      );
      
      // Broadcast to friends/guild members
      socket.broadcast.emit('player_status_update', {
        userId: socket.userId,
        username: socket.username,
        status,
        location,
        activity,
        timestamp: new Date()
      });
      
    } catch (error) {
      console.error('Status update error:', error);
    }
  });

  // Handle disconnection
  socket.on('disconnect', async () => {
    console.log(`🔌 User disconnected: ${socket.id}`);
    
    const user = connectedUsers.get(socket.id);
    if (user) {
      // Update user offline status
      try {
        await db.execute(
          'UPDATE user_status SET status = "offline", last_seen = NOW() WHERE user_id = ?',
          [user.userId]
        );
      } catch (error) {
        console.error('Error updating offline status:', error);
      }
      
      // Remove from active games
      for (const [roomId, game] of activeGames.entries()) {
        game.players = game.players.filter(p => p.socketId !== socket.id);
        if (game.players.length === 0) {
          activeGames.delete(roomId);
        } else {
          socket.to(roomId).emit('player_left', {
            userId: user.userId,
            username: user.username,
            playerCount: game.players.length
          });
        }
      }
      
      connectedUsers.delete(socket.id);
      
      // Broadcast user offline status
      socket.broadcast.emit('user_offline', {
        userId: user.userId,
        username: user.username
      });
      
      // Update online count
      io.emit('online_count', connectedUsers.size);
    }
  });
});

// Background tasks for real-time updates
setInterval(async () => {
  try {
    // Check for new marketplace items
    const [marketplaceUpdates] = await db.execute(
      'SELECT * FROM player_trades WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND status = "listed"'
    );
    
    if (marketplaceUpdates.length > 0) {
      io.to('marketplace').emit('new_marketplace_items', marketplaceUpdates);
    }
    
    // Check for risk alerts
    const [riskAlerts] = await db.execute(
      'SELECT * FROM risk_alerts WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND status = "active"'
    );
    
    for (const alert of riskAlerts) {
      io.to(`risk_${alert.business_id}`).emit('risk_alert', {
        businessId: alert.business_id,
        riskLevel: alert.risk_level,
        message: alert.message,
        timestamp: alert.created_at
      });
    }
    
    // Send analytics updates
    const analyticsData = {
      onlineUsers: connectedUsers.size,
      activeGames: activeGames.size,
      messagesPerMinute: 0, // Calculate from recent messages
      timestamp: new Date()
    };
    
    io.to('analytics_dashboard').emit('analytics_update', analyticsData);
    
  } catch (error) {
    console.error('Background task error:', error);
  }
}, 60000); // Every minute

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ 
    status: 'healthy', 
    uptime: process.uptime(),
    connections: connectedUsers.size,
    activeGames: activeGames.size
  });
});

// API endpoint to send notifications
app.post('/api/notify', async (req, res) => {
  try {
    const { userId, type, title, message, data } = req.body;
    
    // Save notification to database
    await db.execute(
      'INSERT INTO notifications (user_id, type, title, message, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
      [userId, type, title, message, JSON.stringify(data || {})]
    );
    
    // Find user's socket and send real-time notification
    for (const [socketId, user] of connectedUsers.entries()) {
      if (user.userId === userId) {
        io.to(socketId).emit('notification', {
          type,
          title,
          message,
          data,
          timestamp: new Date()
        });
        break;
      }
    }
    
    res.json({ success: true });
  } catch (error) {
    console.error('Notification error:', error);
    res.status(500).json({ error: 'Failed to send notification' });
  }
});

// Broadcast endpoint for admin messages
app.post('/api/broadcast', (req, res) => {
  try {
    const { message, type, data } = req.body;
    
    io.emit('broadcast', {
      message,
      type: type || 'info',
      data: data || {},
      timestamp: new Date()
    });
    
    res.json({ success: true, recipients: connectedUsers.size });
  } catch (error) {
    console.error('Broadcast error:', error);
    res.status(500).json({ error: 'Failed to broadcast message' });
  }
});

// Start server
const PORT = process.env.PORT || 80;

async function startServer() {
  await initDatabase();
  
  server.listen(PORT, () => {
    console.log(`🚀 SmokeoutNYC Realtime Server running on port ${PORT}`);
    console.log(`📊 Features: WebSocket, Chat, Gaming, Notifications, Analytics`);
  });
}

startServer().catch(console.error);

// Graceful shutdown
process.on('SIGINT', () => {
  console.log('🛑 Shutting down realtime server...');
  if (db) {
    db.end();
  }
  server.close(() => {
    console.log('✅ Server shutdown complete');
    process.exit(0);
  });
});
