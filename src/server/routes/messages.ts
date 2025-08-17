import express from 'express';
import { prisma } from '../index';
import { authenticate, requireRole, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { sendBulkEmail } from '../utils/email';
import Joi from 'joi';
import { MessageType, UserRole } from '@prisma/client';

const router = express.Router();

const sendMessageSchema = Joi.object({
  content: Joi.string().required().max(5000),
  type: Joi.string().valid(...Object.values(MessageType)).default('USER_MESSAGE'),
  receiverId: Joi.string().optional() // If not provided, it's a broadcast
});

const addCommentSchema = Joi.object({
  content: Joi.string().required().max(2000),
  rating: Joi.number().integer().min(1).max(5).optional()
});

// Send message (admin only for broadcasts)
router.post('/send', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { error, value } = sendMessageSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { content, type, receiverId } = value;

    // Check permissions for broadcast messages
    if (!receiverId && (req.user!.role !== UserRole.ADMIN && req.user!.role !== UserRole.SUPER_ADMIN)) {
      throw new AppError('Only admins can send broadcast messages', 403);
    }

    // Set expiration date (1 year from now for compliance)
    const expiresAt = new Date();
    expiresAt.setFullYear(expiresAt.getFullYear() + 1);

    if (receiverId) {
      // Send to specific user
      const receiver = await prisma.user.findUnique({
        where: { id: receiverId },
        select: { id: true, email: true, firstName: true, lastName: true }
      });

      if (!receiver) {
        throw new AppError('Receiver not found', 404);
      }

      const message = await prisma.message.create({
        data: {
          content,
          type,
          senderId: req.user!.id,
          receiverId,
          expiresAt
        },
        include: {
          sender: {
            select: {
              id: true,
              username: true,
              firstName: true,
              lastName: true
            }
          },
          receiver: {
            select: {
              id: true,
              username: true,
              firstName: true,
              lastName: true
            }
          }
        }
      });

      res.status(201).json({
        message: 'Message sent successfully',
        data: message
      });
    } else {
      // Broadcast message to all users
      const users = await prisma.user.findMany({
        select: { id: true, email: true, firstName: true, lastName: true }
      });

      // Create message records for all users
      const messagePromises = users.map(user =>
        prisma.message.create({
          data: {
            content,
            type: MessageType.ADMIN_BROADCAST,
            senderId: req.user!.id,
            receiverId: user.id,
            expiresAt
          }
        })
      );

      await Promise.all(messagePromises);

      // Send emails to all users
      try {
        const emails = users.map(user => user.email);
        await sendBulkEmail(
          emails,
          'Important Message from SmokeoutNYC',
          `
            <h2>Message from SmokeoutNYC Administration</h2>
            <p>${content}</p>
            <hr>
            <p><small>This message was sent to all users. You can view it in your account dashboard.</small></p>
          `
        );
      } catch (emailError) {
        console.error('Failed to send broadcast emails:', emailError);
        // Don't fail the message creation if email fails
      }

      res.status(201).json({
        message: `Broadcast message sent to ${users.length} users`,
        recipientCount: users.length
      });
    }
  } catch (error) {
    next(error);
  }
});

// Get user's messages
router.get('/', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { page = '1', limit = '20', unreadOnly = 'false' } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const where: any = {
      receiverId: req.user!.id
    };

    if (unreadOnly === 'true') {
      where.isRead = false;
    }

    const messages = await prisma.message.findMany({
      where,
      include: {
        sender: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true,
            role: true
          }
        }
      },
      orderBy: {
        createdAt: 'desc'
      },
      skip,
      take: limitNum
    });

    const total = await prisma.message.count({ where });
    const unreadCount = await prisma.message.count({
      where: {
        receiverId: req.user!.id,
        isRead: false
      }
    });

    res.json({
      messages,
      pagination: {
        page: pageNum,
        limit: limitNum,
        total,
        pages: Math.ceil(total / limitNum)
      },
      unreadCount
    });
  } catch (error) {
    next(error);
  }
});

// Mark message as read
router.patch('/:id/read', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const message = await prisma.message.findFirst({
      where: {
        id,
        receiverId: req.user!.id
      }
    });

    if (!message) {
      throw new AppError('Message not found', 404);
    }

    const updatedMessage = await prisma.message.update({
      where: { id },
      data: { isRead: true }
    });

    res.json({
      message: 'Message marked as read',
      data: updatedMessage
    });
  } catch (error) {
    next(error);
  }
});

// Mark all messages as read
router.patch('/read-all', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const result = await prisma.message.updateMany({
      where: {
        receiverId: req.user!.id,
        isRead: false
      },
      data: { isRead: true }
    });

    res.json({
      message: `Marked ${result.count} messages as read`
    });
  } catch (error) {
    next(error);
  }
});

// Delete message
router.delete('/:id', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const message = await prisma.message.findFirst({
      where: {
        id,
        receiverId: req.user!.id
      }
    });

    if (!message) {
      throw new AppError('Message not found', 404);
    }

    await prisma.message.delete({
      where: { id }
    });

    res.json({
      message: 'Message deleted successfully'
    });
  } catch (error) {
    next(error);
  }
});

// Add comment to store
router.post('/stores/:storeId/comments', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { storeId } = req.params;
    const { error, value } = addCommentSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { content, rating } = value;

    // Check if store exists
    const store = await prisma.store.findUnique({
      where: { id: storeId }
    });

    if (!store) {
      throw new AppError('Store not found', 404);
    }

    // Check if user already commented on this store
    const existingComment = await prisma.comment.findFirst({
      where: {
        userId: req.user!.id,
        storeId
      }
    });

    if (existingComment) {
      throw new AppError('You have already commented on this store', 409);
    }

    const comment = await prisma.comment.create({
      data: {
        content,
        rating,
        userId: req.user!.id,
        storeId
      },
      include: {
        user: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true,
            avatar: true
          }
        }
      }
    });

    res.status(201).json(comment);
  } catch (error) {
    next(error);
  }
});

// Get store comments
router.get('/stores/:storeId/comments', async (req, res, next) => {
  try {
    const { storeId } = req.params;
    const { page = '1', limit = '20' } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const comments = await prisma.comment.findMany({
      where: { storeId },
      include: {
        user: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true,
            avatar: true
          }
        }
      },
      orderBy: {
        createdAt: 'desc'
      },
      skip,
      take: limitNum
    });

    const total = await prisma.comment.count({
      where: { storeId }
    });

    // Calculate average rating
    const ratingStats = await prisma.comment.aggregate({
      where: {
        storeId,
        rating: { not: null }
      },
      _avg: { rating: true },
      _count: { rating: true }
    });

    res.json({
      comments,
      pagination: {
        page: pageNum,
        limit: limitNum,
        total,
        pages: Math.ceil(total / limitNum)
      },
      averageRating: ratingStats._avg.rating ? Math.round(ratingStats._avg.rating * 10) / 10 : null,
      totalRatings: ratingStats._count.rating
    });
  } catch (error) {
    next(error);
  }
});

// Update comment
router.put('/comments/:id', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = addCommentSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const comment = await prisma.comment.findFirst({
      where: {
        id,
        userId: req.user!.id
      }
    });

    if (!comment) {
      throw new AppError('Comment not found or you do not have permission to edit it', 404);
    }

    const updatedComment = await prisma.comment.update({
      where: { id },
      data: value,
      include: {
        user: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true,
            avatar: true
          }
        }
      }
    });

    res.json(updatedComment);
  } catch (error) {
    next(error);
  }
});

// Delete comment
router.delete('/comments/:id', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const comment = await prisma.comment.findFirst({
      where: {
        id,
        userId: req.user!.id
      }
    });

    if (!comment) {
      throw new AppError('Comment not found or you do not have permission to delete it', 404);
    }

    await prisma.comment.delete({
      where: { id }
    });

    res.json({
      message: 'Comment deleted successfully'
    });
  } catch (error) {
    next(error);
  }
});

// Admin: Get all messages for management
router.get('/admin/all', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { page = '1', limit = '20', type } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const where: any = {};
    if (type) {
      where.type = type;
    }

    const messages = await prisma.message.findMany({
      where,
      include: {
        sender: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true,
            role: true
          }
        },
        receiver: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true
          }
        }
      },
      orderBy: {
        createdAt: 'desc'
      },
      skip,
      take: limitNum
    });

    const total = await prisma.message.count({ where });

    res.json({
      messages,
      pagination: {
        page: pageNum,
        limit: limitNum,
        total,
        pages: Math.ceil(total / limitNum)
      }
    });
  } catch (error) {
    next(error);
  }
});

export default router;
