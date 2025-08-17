import express from 'express';
import { prisma } from '../index';
import { authenticate, requireRole, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import Joi from 'joi';
import { StoreStatus, UserRole } from '@prisma/client';

const router = express.Router();

// Validation schemas
const createStoreSchema = Joi.object({
  name: Joi.string().required().max(200),
  address: Joi.string().required().max(500),
  latitude: Joi.number().required().min(-90).max(90),
  longitude: Joi.number().required().min(-180).max(180),
  phone: Joi.string().optional().max(20),
  email: Joi.string().email().optional(),
  website: Joi.string().uri().optional(),
  description: Joi.string().optional().max(2000),
  status: Joi.string().valid(...Object.values(StoreStatus)).default('OPEN'),
  operationSmokeoutDate: Joi.date().optional(),
  closureReason: Joi.string().optional().max(500),
  hours: Joi.object().optional()
});

const updateStoreSchema = Joi.object({
  name: Joi.string().optional().max(200),
  address: Joi.string().optional().max(500),
  latitude: Joi.number().optional().min(-90).max(90),
  longitude: Joi.number().optional().min(-180).max(180),
  phone: Joi.string().optional().max(20),
  email: Joi.string().email().optional(),
  website: Joi.string().uri().optional(),
  description: Joi.string().optional().max(2000),
  status: Joi.string().valid(...Object.values(StoreStatus)).optional(),
  operationSmokeoutDate: Joi.date().optional().allow(null),
  closureReason: Joi.string().optional().max(500).allow(null),
  hours: Joi.object().optional()
});

const claimStoreSchema = Joi.object({
  businessLicense: Joi.string().optional(),
  proofOfOwnership: Joi.string().optional(),
  notes: Joi.string().optional().max(1000)
});

// Get all stores with filtering and pagination
router.get('/', async (req, res, next) => {
  try {
    const {
      page = '1',
      limit = '20',
      status,
      search,
      lat,
      lng,
      radius = '10' // km
    } = req.query;

    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    // Build where clause
    const where: any = {};

    if (status) {
      where.status = status;
    }

    if (search) {
      where.OR = [
        { name: { contains: search as string, mode: 'insensitive' } },
        { address: { contains: search as string, mode: 'insensitive' } },
        { description: { contains: search as string, mode: 'insensitive' } }
      ];
    }

    // If location provided, we'll filter after fetching (Prisma doesn't have native geo queries)
    const stores = await prisma.store.findMany({
      where,
      include: {
        owner: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true
          }
        },
        comments: {
          select: {
            id: true,
            rating: true
          }
        },
        _count: {
          select: {
            comments: true
          }
        }
      },
      skip,
      take: limitNum,
      orderBy: {
        createdAt: 'desc'
      }
    });

    // Filter by location if provided
    let filteredStores = stores;
    if (lat && lng) {
      const userLat = parseFloat(lat as string);
      const userLng = parseFloat(lng as string);
      const radiusKm = parseFloat(radius as string);

      filteredStores = stores.filter(store => {
        const distance = calculateDistance(userLat, userLng, store.latitude, store.longitude);
        return distance <= radiusKm;
      });
    }

    // Calculate average ratings
    const storesWithRatings = filteredStores.map(store => {
      const ratings = store.comments.map(c => c.rating).filter(r => r !== null) as number[];
      const averageRating = ratings.length > 0 
        ? ratings.reduce((sum, rating) => sum + rating, 0) / ratings.length 
        : null;

      return {
        ...store,
        averageRating: averageRating ? Math.round(averageRating * 10) / 10 : null,
        totalComments: store._count.comments
      };
    });

    const total = await prisma.store.count({ where });

    res.json({
      stores: storesWithRatings,
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

// Get single store
router.get('/:id', async (req, res, next) => {
  try {
    const { id } = req.params;

    const store = await prisma.store.findUnique({
      where: { id },
      include: {
        owner: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true,
            avatar: true
          }
        },
        comments: {
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
          }
        },
        products: {
          where: {
            isApproved: true
          },
          take: 10,
          orderBy: {
            createdAt: 'desc'
          }
        }
      }
    });

    if (!store) {
      throw new AppError('Store not found', 404);
    }

    // Calculate average rating
    const ratings = store.comments.map(c => c.rating).filter(r => r !== null) as number[];
    const averageRating = ratings.length > 0 
      ? ratings.reduce((sum, rating) => sum + rating, 0) / ratings.length 
      : null;

    res.json({
      ...store,
      averageRating: averageRating ? Math.round(averageRating * 10) / 10 : null
    });
  } catch (error) {
    next(error);
  }
});

// Create store
router.post('/', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { error, value } = createStoreSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const store = await prisma.store.create({
      data: {
        ...value,
        // Only admins can create stores as verified initially
        isVerified: req.user!.role === UserRole.ADMIN || req.user!.role === UserRole.SUPER_ADMIN
      },
      include: {
        owner: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true
          }
        }
      }
    });

    res.status(201).json(store);
  } catch (error) {
    next(error);
  }
});

// Update store
router.put('/:id', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = updateStoreSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    // Check if store exists
    const existingStore = await prisma.store.findUnique({
      where: { id },
      include: { owner: true }
    });

    if (!existingStore) {
      throw new AppError('Store not found', 404);
    }

    // Check permissions
    const isOwner = existingStore.ownerId === req.user!.id;
    const isAdmin = req.user!.role === UserRole.ADMIN || req.user!.role === UserRole.SUPER_ADMIN;

    if (!isOwner && !isAdmin) {
      throw new AppError('You can only edit stores you own', 403);
    }

    const updatedStore = await prisma.store.update({
      where: { id },
      data: value,
      include: {
        owner: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true
          }
        }
      }
    });

    res.json(updatedStore);
  } catch (error) {
    next(error);
  }
});

// Delete store
router.delete('/:id', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const store = await prisma.store.findUnique({
      where: { id }
    });

    if (!store) {
      throw new AppError('Store not found', 404);
    }

    await prisma.store.delete({
      where: { id }
    });

    res.json({ message: 'Store deleted successfully' });
  } catch (error) {
    next(error);
  }
});

// Claim store
router.post('/:id/claim', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = claimStoreSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const store = await prisma.store.findUnique({
      where: { id }
    });

    if (!store) {
      throw new AppError('Store not found', 404);
    }

    if (store.isClaimed) {
      throw new AppError('Store is already claimed', 409);
    }

    // Check if user already has a pending claim
    const existingClaim = await prisma.claimRequest.findFirst({
      where: {
        userId: req.user!.id,
        storeId: id,
        status: 'pending'
      }
    });

    if (existingClaim) {
      throw new AppError('You already have a pending claim for this store', 409);
    }

    const claimRequest = await prisma.claimRequest.create({
      data: {
        userId: req.user!.id,
        storeId: id,
        ...value
      }
    });

    res.status(201).json({
      message: 'Claim request submitted successfully',
      claimRequest
    });
  } catch (error) {
    next(error);
  }
});

// Get claim requests (admin only)
router.get('/admin/claims', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { status = 'pending' } = req.query;

    const claims = await prisma.claimRequest.findMany({
      where: {
        status: status as string
      },
      include: {
        user: {
          select: {
            id: true,
            email: true,
            username: true,
            firstName: true,
            lastName: true
          }
        },
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
      }
    });

    res.json(claims);
  } catch (error) {
    next(error);
  }
});

// Approve/reject claim
router.patch('/admin/claims/:claimId', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { claimId } = req.params;
    const { status } = req.body; // 'approved' or 'rejected'

    if (!['approved', 'rejected'].includes(status)) {
      throw new AppError('Status must be approved or rejected', 400);
    }

    const claim = await prisma.claimRequest.findUnique({
      where: { id: claimId },
      include: { store: true, user: true }
    });

    if (!claim) {
      throw new AppError('Claim request not found', 404);
    }

    if (claim.status !== 'pending') {
      throw new AppError('Claim request has already been processed', 400);
    }

    // Update claim status
    const updatedClaim = await prisma.claimRequest.update({
      where: { id: claimId },
      data: { status }
    });

    // If approved, update store ownership
    if (status === 'approved') {
      await prisma.store.update({
        where: { id: claim.storeId },
        data: {
          ownerId: claim.userId,
          isClaimed: true,
          isVerified: true
        }
      });

      // Update user role to store owner if not already
      if (claim.user.role === UserRole.USER) {
        await prisma.user.update({
          where: { id: claim.userId },
          data: { role: UserRole.STORE_OWNER }
        });
      }
    }

    res.json({
      message: `Claim request ${status} successfully`,
      claim: updatedClaim
    });
  } catch (error) {
    next(error);
  }
});

// Helper function to calculate distance between two points
function calculateDistance(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371; // Radius of the Earth in km
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

export default router;
