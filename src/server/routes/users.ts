import express from 'express';
import { prisma } from '../index';
import { authenticate, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { upload, processAvatar, deleteFile } from '../utils/fileUpload';
import Joi from 'joi';

const router = express.Router();

const updateProfileSchema = Joi.object({
  username: Joi.string().alphanum().min(3).max(30).optional(),
  firstName: Joi.string().max(50).optional(),
  lastName: Joi.string().max(50).optional(),
  avatar: Joi.string().uri().optional()
});

// Get user profile
router.get('/profile', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const user = await prisma.user.findUnique({
      where: { id: req.user!.id },
      select: {
        id: true,
        email: true,
        username: true,
        firstName: true,
        lastName: true,
        avatar: true,
        role: true,
        isEmailVerified: true,
        createdAt: true,
        lastLoginAt: true,
        _count: {
          select: {
            ownedStores: true,
            comments: true
          }
        }
      }
    });

    if (!user) {
      throw new AppError('User not found', 404);
    }

    res.json(user);
  } catch (error) {
    next(error);
  }
});

// Update user profile
router.put('/profile', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { error, value } = updateProfileSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    // Check if username is already taken
    if (value.username) {
      const existingUser = await prisma.user.findFirst({
        where: {
          username: value.username,
          id: { not: req.user!.id }
        }
      });

      if (existingUser) {
        throw new AppError('Username already taken', 409);
      }
    }

    const updatedUser = await prisma.user.update({
      where: { id: req.user!.id },
      data: value,
      select: {
        id: true,
        email: true,
        username: true,
        firstName: true,
        lastName: true,
        avatar: true,
        role: true,
        isEmailVerified: true
      }
    });

    res.json(updatedUser);
  } catch (error) {
    next(error);
  }
});

// Get user's stores
router.get('/stores', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const stores = await prisma.store.findMany({
      where: { ownerId: req.user!.id },
      include: {
        _count: {
          select: {
            comments: true,
            products: true
          }
        }
      },
      orderBy: {
        createdAt: 'desc'
      }
    });

    res.json(stores);
  } catch (error) {
    next(error);
  }
});

// Get user's comments
router.get('/comments', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { page = '1', limit = '10' } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const comments = await prisma.comment.findMany({
      where: { userId: req.user!.id },
      include: {
        store: {
          select: {
            id: true,
            name: true,
            address: true
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
      where: { userId: req.user!.id }
    });

    res.json({
      comments,
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

// Upload avatar
router.post('/avatar', authenticate, upload.single('avatar'), async (req: AuthenticatedRequest, res, next) => {
  try {
    if (!req.file) {
      throw new AppError('No file uploaded', 400);
    }

    // Process the avatar image
    const processedPath = await processAvatar(req.file.path);
    const avatarUrl = `${process.env.SERVER_URL}/${processedPath}`;

    // Get current avatar to delete old one
    const currentUser = await prisma.user.findUnique({
      where: { id: req.user!.id },
      select: { avatar: true }
    });

    // Update user avatar
    const updatedUser = await prisma.user.update({
      where: { id: req.user!.id },
      data: { avatar: avatarUrl },
      select: {
        id: true,
        email: true,
        username: true,
        firstName: true,
        lastName: true,
        avatar: true,
        role: true,
        isEmailVerified: true
      }
    });

    // Delete old avatar file if it exists
    if (currentUser?.avatar && currentUser.avatar.includes(process.env.SERVER_URL || '')) {
      const oldPath = currentUser.avatar.replace(`${process.env.SERVER_URL}/`, '');
      deleteFile(oldPath);
    }

    res.json({
      message: 'Avatar uploaded successfully',
      user: updatedUser
    });
  } catch (error) {
    // Clean up uploaded file on error
    if (req.file) {
      deleteFile(req.file.path);
    }
    next(error);
  }
});

// Delete avatar
router.delete('/avatar', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const currentUser = await prisma.user.findUnique({
      where: { id: req.user!.id },
      select: { avatar: true }
    });

    if (!currentUser?.avatar) {
      throw new AppError('No avatar to delete', 400);
    }

    // Update user to remove avatar
    const updatedUser = await prisma.user.update({
      where: { id: req.user!.id },
      data: { avatar: null },
      select: {
        id: true,
        email: true,
        username: true,
        firstName: true,
        lastName: true,
        avatar: true,
        role: true,
        isEmailVerified: true
      }
    });

    // Delete avatar file if it's hosted locally
    if (currentUser.avatar.includes(process.env.SERVER_URL || '')) {
      const filePath = currentUser.avatar.replace(`${process.env.SERVER_URL}/`, '');
      deleteFile(filePath);
    }

    res.json({
      message: 'Avatar deleted successfully',
      user: updatedUser
    });
  } catch (error) {
    next(error);
  }
});

export default router;
