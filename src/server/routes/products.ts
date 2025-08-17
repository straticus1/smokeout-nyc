import express from 'express';
import { prisma } from '../index';
import { authenticate, requireRole, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import Joi from 'joi';
import { ProductType, UserRole } from '@prisma/client';

const router = express.Router();

const createProductSchema = Joi.object({
  name: Joi.string().required().max(200),
  description: Joi.string().optional().max(2000),
  type: Joi.string().valid(...Object.values(ProductType)).required(),
  strain: Joi.string().optional().max(100),
  thcContent: Joi.number().optional().min(0).max(100),
  cbdContent: Joi.number().optional().min(0).max(100),
  price: Joi.number().optional().min(0),
  image: Joi.string().uri().optional(),
  storeId: Joi.string().required()
});

const updateProductSchema = Joi.object({
  name: Joi.string().optional().max(200),
  description: Joi.string().optional().max(2000),
  type: Joi.string().valid(...Object.values(ProductType)).optional(),
  strain: Joi.string().optional().max(100),
  thcContent: Joi.number().optional().min(0).max(100),
  cbdContent: Joi.number().optional().min(0).max(100),
  price: Joi.number().optional().min(0),
  image: Joi.string().uri().optional()
});

// Get all products with filtering and search
router.get('/', async (req, res, next) => {
  try {
    const {
      page = '1',
      limit = '20',
      type,
      search,
      storeId,
      approved = 'true'
    } = req.query;

    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const where: any = {};

    if (approved === 'true') {
      where.isApproved = true;
    }

    if (type) {
      where.type = type;
    }

    if (storeId) {
      where.storeId = storeId;
    }

    if (search) {
      where.OR = [
        { name: { contains: search as string, mode: 'insensitive' } },
        { description: { contains: search as string, mode: 'insensitive' } },
        { strain: { contains: search as string, mode: 'insensitive' } }
      ];
    }

    const products = await prisma.product.findMany({
      where,
      include: {
        store: {
          select: {
            id: true,
            name: true,
            address: true,
            status: true
          }
        }
      },
      orderBy: {
        createdAt: 'desc'
      },
      skip,
      take: limitNum
    });

    const total = await prisma.product.count({ where });

    res.json({
      products,
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

// Get single product
router.get('/:id', async (req, res, next) => {
  try {
    const { id } = req.params;

    const product = await prisma.product.findUnique({
      where: { id },
      include: {
        store: {
          select: {
            id: true,
            name: true,
            address: true,
            status: true,
            owner: {
              select: {
                id: true,
                username: true,
                firstName: true,
                lastName: true
              }
            }
          }
        }
      }
    });

    if (!product) {
      throw new AppError('Product not found', 404);
    }

    res.json(product);
  } catch (error) {
    next(error);
  }
});

// Create product
router.post('/', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { error, value } = createProductSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    // Check if store exists and user has permission
    const store = await prisma.store.findUnique({
      where: { id: value.storeId },
      include: { owner: true }
    });

    if (!store) {
      throw new AppError('Store not found', 404);
    }

    const isStoreOwner = store.ownerId === req.user!.id;
    const isAdmin = req.user!.role === UserRole.ADMIN || req.user!.role === UserRole.SUPER_ADMIN;

    if (!isStoreOwner && !isAdmin) {
      throw new AppError('You can only add products to stores you own', 403);
    }

    const product = await prisma.product.create({
      data: {
        ...value,
        createdBy: req.user!.id,
        isApproved: isAdmin // Auto-approve if admin creates it
      },
      include: {
        store: {
          select: {
            id: true,
            name: true,
            address: true
          }
        }
      }
    });

    res.status(201).json(product);
  } catch (error) {
    next(error);
  }
});

// Update product
router.put('/:id', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = updateProductSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const product = await prisma.product.findUnique({
      where: { id },
      include: {
        store: {
          include: { owner: true }
        }
      }
    });

    if (!product) {
      throw new AppError('Product not found', 404);
    }

    // Check permissions
    const isStoreOwner = product.store.ownerId === req.user!.id;
    const isProductCreator = product.createdBy === req.user!.id;
    const isAdmin = req.user!.role === UserRole.ADMIN || req.user!.role === UserRole.SUPER_ADMIN;

    if (!isStoreOwner && !isProductCreator && !isAdmin) {
      throw new AppError('You can only edit products you created or products from stores you own', 403);
    }

    const updatedProduct = await prisma.product.update({
      where: { id },
      data: {
        ...value,
        // Reset approval if non-admin makes changes
        isApproved: isAdmin ? product.isApproved : false
      },
      include: {
        store: {
          select: {
            id: true,
            name: true,
            address: true
          }
        }
      }
    });

    res.json(updatedProduct);
  } catch (error) {
    next(error);
  }
});

// Delete product
router.delete('/:id', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const product = await prisma.product.findUnique({
      where: { id },
      include: {
        store: {
          include: { owner: true }
        }
      }
    });

    if (!product) {
      throw new AppError('Product not found', 404);
    }

    // Check permissions
    const isStoreOwner = product.store.ownerId === req.user!.id;
    const isProductCreator = product.createdBy === req.user!.id;
    const isAdmin = req.user!.role === UserRole.ADMIN || req.user!.role === UserRole.SUPER_ADMIN;

    if (!isStoreOwner && !isProductCreator && !isAdmin) {
      throw new AppError('You can only delete products you created or products from stores you own', 403);
    }

    await prisma.product.delete({
      where: { id }
    });

    res.json({ message: 'Product deleted successfully' });
  } catch (error) {
    next(error);
  }
});

// Admin routes for product approval
router.get('/admin/pending', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { page = '1', limit = '20' } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const products = await prisma.product.findMany({
      where: { isApproved: false },
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

    const total = await prisma.product.count({
      where: { isApproved: false }
    });

    res.json({
      products,
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

// Approve/reject product
router.patch('/:id/approval', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { id } = req.params;
    const { approved } = req.body;

    if (typeof approved !== 'boolean') {
      throw new AppError('Approved field must be a boolean', 400);
    }

    const product = await prisma.product.findUnique({
      where: { id }
    });

    if (!product) {
      throw new AppError('Product not found', 404);
    }

    const updatedProduct = await prisma.product.update({
      where: { id },
      data: { isApproved: approved },
      include: {
        store: {
          select: {
            id: true,
            name: true,
            address: true
          }
        }
      }
    });

    res.json({
      message: `Product ${approved ? 'approved' : 'rejected'} successfully`,
      product: updatedProduct
    });
  } catch (error) {
    next(error);
  }
});

export default router;
