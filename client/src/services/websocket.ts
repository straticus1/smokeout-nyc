import { EventEmitter } from 'events';

export interface WebSocketMessage {
  type: string;
  [key: string]: any;
}

export interface GameWebSocketClient extends EventEmitter {
  connect(token: string, userId: number): Promise<void>;
  disconnect(): void;
  joinRoom(roomId: string): void;
  leaveRoom(roomId: string): void;
  sendPlantAction(action: string, plantId: number, roomId?: string): void;
  sendTradeRequest(targetUserId: number, items: any[], roomId?: string): void;
  requestMarketUpdate(roomId?: string): void;
  syncWeather(roomId?: string): void;
  broadcastGenetics(parent1Id: number, parent2Id: number, roomId?: string): void;
}

class GameWebSocket extends EventEmitter implements GameWebSocketClient {
  private ws: WebSocket | null = null;
  private wsUrl: string;
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectDelay = 1000;
  private isConnected = false;
  private currentRoom: string | null = null;
  private authToken: string | null = null;
  private userId: number | null = null;
  private heartbeatInterval: NodeJS.Timeout | null = null;

  constructor() {
    super();
    this.wsUrl = process.env.REACT_APP_WS_URL || 'ws://localhost:8080';
  }

  async connect(token: string, userId: number): Promise<void> {
    return new Promise((resolve, reject) => {
      if (this.ws && this.isConnected) {
        resolve();
        return;
      }

      this.authToken = token;
      this.userId = userId;

      try {
        this.ws = new WebSocket(this.wsUrl);

        this.ws.onopen = () => {
          console.log('WebSocket connected');
          this.isConnected = true;
          this.reconnectAttempts = 0;
          
          // Send authentication
          this.send({
            type: 'auth',
            token: token,
            user_id: userId
          });

          // Start heartbeat
          this.startHeartbeat();
          resolve();
        };

        this.ws.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
          } catch (error) {
            console.error('Failed to parse WebSocket message:', error);
          }
        };

        this.ws.onclose = (event) => {
          console.log('WebSocket disconnected:', event.code, event.reason);
          this.isConnected = false;
          this.stopHeartbeat();
          
          if (!event.wasClean && this.shouldReconnect()) {
            this.scheduleReconnect();
          }
          
          this.emit('disconnect', event);
        };

        this.ws.onerror = (error) => {
          console.error('WebSocket error:', error);
          this.emit('error', error);
          if (this.reconnectAttempts === 0) {
            reject(error);
          }
        };

      } catch (error) {
        reject(error);
      }
    });
  }

  disconnect(): void {
    if (this.ws) {
      this.ws.close(1000, 'Client disconnect');
      this.ws = null;
    }
    this.isConnected = false;
    this.stopHeartbeat();
    this.authToken = null;
    this.userId = null;
  }

  joinRoom(roomId: string): void {
    if (!this.isConnected) {
      console.warn('WebSocket not connected');
      return;
    }

    this.currentRoom = roomId;
    this.send({
      type: 'join_room',
      room_id: roomId
    });
  }

  leaveRoom(roomId: string): void {
    if (!this.isConnected) {
      return;
    }

    this.send({
      type: 'leave_room',
      room_id: roomId
    });

    if (this.currentRoom === roomId) {
      this.currentRoom = null;
    }
  }

  sendPlantAction(action: string, plantId: number, roomId?: string): void {
    this.send({
      type: 'plant_action',
      action,
      plant_id: plantId,
      room_id: roomId || this.currentRoom || 'global'
    });
  }

  sendTradeRequest(targetUserId: number, items: any[], roomId?: string): void {
    this.send({
      type: 'trade_request',
      target_user_id: targetUserId,
      items,
      room_id: roomId || this.currentRoom || 'global'
    });
  }

  requestMarketUpdate(roomId?: string): void {
    this.send({
      type: 'market_update',
      room_id: roomId || this.currentRoom || 'global'
    });
  }

  syncWeather(roomId?: string): void {
    this.send({
      type: 'weather_sync',
      room_id: roomId || this.currentRoom || 'global'
    });
  }

  broadcastGenetics(parent1Id: number, parent2Id: number, roomId?: string): void {
    this.send({
      type: 'genetics_bred',
      parent1_id: parent1Id,
      parent2_id: parent2Id,
      room_id: roomId || this.currentRoom || 'global'
    });
  }

  private send(data: WebSocketMessage): void {
    if (this.ws && this.isConnected) {
      this.ws.send(JSON.stringify(data));
    }
  }

  private handleMessage(data: WebSocketMessage): void {
    switch (data.type) {
      case 'auth_success':
        console.log('WebSocket authentication successful');
        this.emit('authenticated', data);
        break;

      case 'room_joined':
        console.log(`Joined room: ${data.room_id}`);
        this.emit('roomJoined', data);
        break;

      case 'player_joined':
        this.emit('playerJoined', data);
        break;

      case 'player_left':
        this.emit('playerLeft', data);
        break;

      case 'plant_action_result':
        this.emit('plantAction', data);
        break;

      case 'trade_request':
        this.emit('tradeRequest', data);
        break;

      case 'trade_request_sent':
        this.emit('tradeRequestSent', data);
        break;

      case 'market_prices_update':
        this.emit('marketUpdate', data);
        break;

      case 'weather_update':
        this.emit('weatherUpdate', data);
        break;

      case 'genetics_bred':
        this.emit('geneticsBred', data);
        break;

      case 'error':
        console.error('WebSocket error:', data.message);
        this.emit('serverError', data);
        break;

      case 'pong':
        // Heartbeat response
        break;

      default:
        console.log('Unknown message type:', data.type);
        this.emit('message', data);
    }
  }

  private shouldReconnect(): boolean {
    return this.reconnectAttempts < this.maxReconnectAttempts;
  }

  private scheduleReconnect(): void {
    if (!this.shouldReconnect()) {
      console.log('Max reconnection attempts reached');
      return;
    }

    this.reconnectAttempts++;
    const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);
    
    console.log(`Attempting to reconnect in ${delay}ms (attempt ${this.reconnectAttempts})`);
    
    setTimeout(() => {
      if (this.authToken && this.userId) {
        this.connect(this.authToken, this.userId).catch(error => {
          console.error('Reconnection failed:', error);
        });
      }
    }, delay);
  }

  private startHeartbeat(): void {
    this.heartbeatInterval = setInterval(() => {
      if (this.isConnected) {
        this.send({ type: 'ping' });
      }
    }, 30000); // 30 seconds
  }

  private stopHeartbeat(): void {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
  }
}

// Singleton instance
export const gameWebSocket = new GameWebSocket();

// React hook for WebSocket
import { useState, useEffect, useCallback } from 'react';
import { useAuth } from '../contexts/AuthContext';

export interface UseWebSocketOptions {
  autoConnect?: boolean;
  room?: string;
}

export function useGameWebSocket(options: UseWebSocketOptions = {}) {
  const { user, token } = useAuth();
  const [isConnected, setIsConnected] = useState(false);
  const [playersCount, setPlayersCount] = useState(0);
  const [error, setError] = useState<string | null>(null);

  const connect = useCallback(async () => {
    if (!user || !token) return;
    
    try {
      await gameWebSocket.connect(token, user.id);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Connection failed');
    }
  }, [user, token]);

  const joinRoom = useCallback((roomId: string) => {
    gameWebSocket.joinRoom(roomId);
  }, []);

  const leaveRoom = useCallback((roomId: string) => {
    gameWebSocket.leaveRoom(roomId);
  }, []);

  useEffect(() => {
    const handleAuthenticated = () => {
      setIsConnected(true);
      setError(null);
    };

    const handleDisconnect = () => {
      setIsConnected(false);
    };

    const handleRoomJoined = (data: any) => {
      setPlayersCount(data.players_count);
    };

    const handlePlayerJoined = (data: any) => {
      setPlayersCount(data.players_count);
    };

    const handlePlayerLeft = (data: any) => {
      setPlayersCount(data.players_count);
    };

    const handleError = (err: any) => {
      setError(err.message || 'WebSocket error');
    };

    gameWebSocket.on('authenticated', handleAuthenticated);
    gameWebSocket.on('disconnect', handleDisconnect);
    gameWebSocket.on('roomJoined', handleRoomJoined);
    gameWebSocket.on('playerJoined', handlePlayerJoined);
    gameWebSocket.on('playerLeft', handlePlayerLeft);
    gameWebSocket.on('error', handleError);
    gameWebSocket.on('serverError', handleError);

    // Auto-connect if enabled
    if (options.autoConnect !== false && user && token) {
      connect();
    }

    // Auto-join room if specified
    if (options.room && isConnected) {
      joinRoom(options.room);
    }

    return () => {
      gameWebSocket.off('authenticated', handleAuthenticated);
      gameWebSocket.off('disconnect', handleDisconnect);
      gameWebSocket.off('roomJoined', handleRoomJoined);
      gameWebSocket.off('playerJoined', handlePlayerJoined);
      gameWebSocket.off('playerLeft', handlePlayerLeft);
      gameWebSocket.off('error', handleError);
      gameWebSocket.off('serverError', handleError);
    };
  }, [user, token, options.autoConnect, options.room, connect, joinRoom, isConnected]);

  return {
    webSocket: gameWebSocket,
    isConnected,
    playersCount,
    error,
    connect,
    joinRoom,
    leaveRoom
  };
}