import { Server, Socket } from 'socket.io';
import { verifyToken } from '../middleware/auth';
import { prisma, redis } from '../index';

interface AuthenticatedSocket extends Socket {
  userId?: string;
  username?: string;
}

export function setupSocketHandlers(io: Server) {
  // Authentication middleware for socket connections
  io.use(async (socket: AuthenticatedSocket, next) => {
    try {
      const token = socket.handshake.auth.token;
      
      if (!token) {
        return next(new Error('Authentication error: No token provided'));
      }

      const decoded = verifyToken(token);
      if (!decoded) {
        return next(new Error('Authentication error: Invalid token'));
      }

      // Get user details
      const user = await prisma.user.findUnique({
        where: { id: decoded.userId },
        select: {
          id: true,
          username: true,
          firstName: true,
          lastName: true,
          role: true
        }
      });

      if (!user) {
        return next(new Error('Authentication error: User not found'));
      }

      socket.userId = user.id;
      socket.username = user.username || `${user.firstName} ${user.lastName}` || 'Anonymous';
      
      next();
    } catch (error) {
      next(new Error('Authentication error'));
    }
  });

  io.on('connection', async (socket: AuthenticatedSocket) => {
    console.log(`User ${socket.username} connected with socket ${socket.id}`);

    // Add user to online users
    if (socket.userId) {
      await addOnlineUser(socket.userId, socket.id);
      
      // Broadcast updated online count
      const onlineCount = await getOnlineUserCount();
      io.emit('online_count', onlineCount);
    }

    // Handle joining chat
    socket.on('join_chat', async () => {
      socket.join('global_chat');
      
      // Send recent messages to the newly joined user
      const recentMessages = await prisma.chatMessage.findMany({
        take: 50,
        orderBy: {
          createdAt: 'desc'
        }
      });
      
      socket.emit('chat_history', recentMessages.reverse());
    });

    // Handle chat messages
    socket.on('chat_message', async (data: { message: string }) => {
      if (!socket.userId || !socket.username) {
        return;
      }

      try {
        // Validate message
        if (!data.message || data.message.trim().length === 0) {
          return;
        }

        if (data.message.length > 500) {
          socket.emit('error', { message: 'Message too long (max 500 characters)' });
          return;
        }

        // Save message to database
        const chatMessage = await prisma.chatMessage.create({
          data: {
            content: data.message.trim(),
            userId: socket.userId,
            username: socket.username
          }
        });

        // Broadcast to all users in the chat
        io.to('global_chat').emit('new_message', {
          id: chatMessage.id,
          content: chatMessage.content,
          username: chatMessage.username,
          userId: chatMessage.userId,
          createdAt: chatMessage.createdAt
        });

        // Clean up old messages (keep only last 1000)
        await cleanupOldChatMessages();
        
      } catch (error) {
        console.error('Error handling chat message:', error);
        socket.emit('error', { message: 'Failed to send message' });
      }
    });

    // Handle store updates (for real-time map updates)
    socket.on('subscribe_store_updates', (storeId: string) => {
      socket.join(`store_${storeId}`);
    });

    socket.on('unsubscribe_store_updates', (storeId: string) => {
      socket.leave(`store_${storeId}`);
    });

    // Handle admin broadcasts
    socket.on('admin_broadcast', async (data: { message: string, type: 'info' | 'warning' | 'error' }) => {
      if (!socket.userId) {
        return;
      }

      try {
        // Check if user is admin
        const user = await prisma.user.findUnique({
          where: { id: socket.userId },
          select: { role: true }
        });

        if (!user || (user.role !== 'ADMIN' && user.role !== 'SUPER_ADMIN')) {
          socket.emit('error', { message: 'Unauthorized' });
          return;
        }

        // Broadcast to all connected users
        io.emit('admin_broadcast', {
          message: data.message,
          type: data.type,
          timestamp: new Date()
        });

        console.log(`Admin broadcast sent by ${socket.username}: ${data.message}`);
        
      } catch (error) {
        console.error('Error handling admin broadcast:', error);
        socket.emit('error', { message: 'Failed to send broadcast' });
      }
    });

    // Handle typing indicators
    socket.on('typing_start', () => {
      if (socket.username) {
        socket.to('global_chat').emit('user_typing', {
          username: socket.username,
          userId: socket.userId
        });
      }
    });

    socket.on('typing_stop', () => {
      if (socket.username) {
        socket.to('global_chat').emit('user_stopped_typing', {
          username: socket.username,
          userId: socket.userId
        });
      }
    });

    // Handle disconnection
    socket.on('disconnect', async () => {
      console.log(`User ${socket.username} disconnected`);
      
      if (socket.userId) {
        await removeOnlineUser(socket.userId, socket.id);
        
        // Broadcast updated online count
        const onlineCount = await getOnlineUserCount();
        io.emit('online_count', onlineCount);
      }
    });
  });

  // Broadcast store updates when stores are modified
  setupStoreUpdateBroadcasts(io);
}

// Helper functions for online user management
async function addOnlineUser(userId: string, socketId: string): Promise<void> {
  try {
    await prisma.onlineUser.upsert({
      where: { userId },
      update: {
        socketId,
        lastSeen: new Date()
      },
      create: {
        userId,
        socketId,
        lastSeen: new Date()
      }
    });

    // Also store in Redis for faster access
    await redis.hSet('online_users', userId, socketId);
  } catch (error) {
    console.error('Error adding online user:', error);
  }
}

async function removeOnlineUser(userId: string, socketId: string): Promise<void> {
  try {
    // Remove from database
    await prisma.onlineUser.deleteMany({
      where: {
        userId,
        socketId
      }
    });

    // Remove from Redis
    await redis.hDel('online_users', userId);
  } catch (error) {
    console.error('Error removing online user:', error);
  }
}

async function getOnlineUserCount(): Promise<number> {
  try {
    // Try Redis first for better performance
    const redisCount = await redis.hLen('online_users');
    if (redisCount > 0) {
      return redisCount;
    }

    // Fallback to database
    const count = await prisma.onlineUser.count();
    return count;
  } catch (error) {
    console.error('Error getting online user count:', error);
    return 0;
  }
}

async function cleanupOldChatMessages(): Promise<void> {
  try {
    // Keep only the last 1000 messages
    const messagesToDelete = await prisma.chatMessage.findMany({
      select: { id: true },
      orderBy: { createdAt: 'desc' },
      skip: 1000
    });

    if (messagesToDelete.length > 0) {
      await prisma.chatMessage.deleteMany({
        where: {
          id: {
            in: messagesToDelete.map(m => m.id)
          }
        }
      });
    }
  } catch (error) {
    console.error('Error cleaning up old chat messages:', error);
  }
}

function setupStoreUpdateBroadcasts(io: Server): void {
  // This would be called when stores are updated via API
  // You can emit events like:
  // io.to(`store_${storeId}`).emit('store_updated', updatedStore);
  // io.emit('stores_updated', { action: 'create', store: newStore });
}

// Export function to broadcast store updates from other parts of the application
export function broadcastStoreUpdate(io: Server, action: 'create' | 'update' | 'delete', store: any): void {
  io.emit('stores_updated', { action, store });
  
  if (action !== 'delete') {
    io.to(`store_${store.id}`).emit('store_updated', store);
  }
}
