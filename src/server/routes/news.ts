import express from 'express';
import { prisma } from '../index';
import { authenticate, requireRole, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { auditLog } from '../utils/audit';
import Joi from 'joi';
import { UserRole } from '@prisma/client';
import DOMPurify from 'dompurify';
import { JSDOM } from 'jsdom';

const router = express.Router();

// Initialize DOMPurify for server-side XSS protection
const window = new JSDOM('').window;
const purify = DOMPurify(window);

// Validation schemas
const createNewsSchema = Joi.object({
  title: Joi.string().required().max(255).trim(),
  content: Joi.string().required().max(50000),
  excerpt: Joi.string().optional().max(500).trim(),
  featuredImage: Joi.string().uri().optional(),
  isPublished: Joi.boolean().default(false),
  metaTitle: Joi.string().optional().max(255).trim(),
  metaDescription: Joi.string().optional().max(255).trim()
});

const updateNewsSchema = Joi.object({
  title: Joi.string().optional().max(255).trim(),
  content: Joi.string().optional().max(50000),
  excerpt: Joi.string().optional().max(500).trim(),
  featuredImage: Joi.string().uri().optional().allow(null),
  isPublished: Joi.boolean().optional(),
  metaTitle: Joi.string().optional().max(255).trim().allow(null),
  metaDescription: Joi.string().optional().max(255).trim().allow(null)
});

// Helper function to generate URL slug
function generateSlug(title: string): string {
  return title
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '') // Remove special characters
    .replace(/[\s_-]+/g, '-') // Replace spaces and underscores with hyphens
    .replace(/^-+|-+$/g, ''); // Remove leading/trailing hyphens
}

// Helper function to sanitize HTML content
function sanitizeContent(content: string): string {
  return purify.sanitize(content, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'a', 'img', 'blockquote'],
    ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'class'],
    ALLOW_DATA_ATTR: false
  });
}

// Get all published news articles (public)
router.get('/', async (req, res, next) => {
  try {
    const { page = '1', limit = '10', search } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = Math.min(parseInt(limit as string), 50); // Cap at 50
    const skip = (pageNum - 1) * limitNum;

    const where: any = { isPublished: true };

    if (search) {
      where.OR = [
        { title: { contains: search as string, mode: 'insensitive' } },
        { excerpt: { contains: search as string, mode: 'insensitive' } },
        { content: { contains: search as string, mode: 'insensitive' } }
      ];
    }

    const articles = await prisma.newsArticle.findMany({
      where,
      select: {
        id: true,
        title: true,
        excerpt: true,
        slug: true,
        featuredImage: true,
        publishedAt: true,
        createdAt: true,
        author: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true
          }
        }
      },
      orderBy: {
        publishedAt: 'desc'
      },
      skip,
      take: limitNum
    });

    const total = await prisma.newsArticle.count({ where });

    res.json({
      articles,
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

// Get single news article by slug (public)
router.get('/:slug', async (req, res, next) => {
  try {
    const { slug } = req.params;

    const article = await prisma.newsArticle.findFirst({
      where: {
        slug,
        isPublished: true
      },
      include: {
        author: {
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

    if (!article) {
      throw new AppError('Article not found', 404);
    }

    res.json(article);
  } catch (error) {
    next(error);
  }
});

// Admin routes
// Get all articles (published and unpublished)
router.get('/admin/all', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { page = '1', limit = '20', published, search } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = Math.min(parseInt(limit as string), 100);
    const skip = (pageNum - 1) * limitNum;

    const where: any = {};

    if (published !== undefined) {
      where.isPublished = published === 'true';
    }

    if (search) {
      where.OR = [
        { title: { contains: search as string, mode: 'insensitive' } },
        { content: { contains: search as string, mode: 'insensitive' } }
      ];
    }

    const articles = await prisma.newsArticle.findMany({
      where,
      include: {
        author: {
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

    const total = await prisma.newsArticle.count({ where });

    res.json({
      articles,
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

// Create news article
router.post('/admin', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { error, value } = createNewsSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    // Sanitize content to prevent XSS
    const sanitizedContent = sanitizeContent(value.content);
    const sanitizedExcerpt = value.excerpt ? purify.sanitize(value.excerpt) : null;

    // Generate unique slug
    let baseSlug = generateSlug(value.title);
    let slug = baseSlug;
    let counter = 1;

    while (await prisma.newsArticle.findUnique({ where: { slug } })) {
      slug = `${baseSlug}-${counter}`;
      counter++;
    }

    const article = await prisma.newsArticle.create({
      data: {
        title: purify.sanitize(value.title),
        content: sanitizedContent,
        excerpt: sanitizedExcerpt,
        slug,
        featuredImage: value.featuredImage,
        isPublished: value.isPublished,
        publishedAt: value.isPublished ? new Date() : null,
        metaTitle: value.metaTitle ? purify.sanitize(value.metaTitle) : null,
        metaDescription: value.metaDescription ? purify.sanitize(value.metaDescription) : null,
        authorId: req.user!.id
      },
      include: {
        author: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true
          }
        }
      }
    });

    // Audit log
    await auditLog('CREATE', 'NEWS', article.id, req.user!.id, {
      title: article.title,
      isPublished: article.isPublished
    }, req.ip, req.get('User-Agent'));

    res.status(201).json(article);
  } catch (error) {
    next(error);
  }
});

// Update news article
router.put('/admin/:id', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = updateNewsSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const existingArticle = await prisma.newsArticle.findUnique({
      where: { id }
    });

    if (!existingArticle) {
      throw new AppError('Article not found', 404);
    }

    // Prepare update data
    const updateData: any = {};

    if (value.title) {
      updateData.title = purify.sanitize(value.title);
      
      // Update slug if title changed
      if (value.title !== existingArticle.title) {
        let baseSlug = generateSlug(value.title);
        let slug = baseSlug;
        let counter = 1;

        while (await prisma.newsArticle.findFirst({ 
          where: { 
            slug, 
            id: { not: id } 
          } 
        })) {
          slug = `${baseSlug}-${counter}`;
          counter++;
        }
        updateData.slug = slug;
      }
    }

    if (value.content) {
      updateData.content = sanitizeContent(value.content);
    }

    if (value.excerpt !== undefined) {
      updateData.excerpt = value.excerpt ? purify.sanitize(value.excerpt) : null;
    }

    if (value.featuredImage !== undefined) {
      updateData.featuredImage = value.featuredImage;
    }

    if (value.isPublished !== undefined) {
      updateData.isPublished = value.isPublished;
      
      // Set publishedAt if publishing for the first time
      if (value.isPublished && !existingArticle.isPublished) {
        updateData.publishedAt = new Date();
      }
    }

    if (value.metaTitle !== undefined) {
      updateData.metaTitle = value.metaTitle ? purify.sanitize(value.metaTitle) : null;
    }

    if (value.metaDescription !== undefined) {
      updateData.metaDescription = value.metaDescription ? purify.sanitize(value.metaDescription) : null;
    }

    const updatedArticle = await prisma.newsArticle.update({
      where: { id },
      data: updateData,
      include: {
        author: {
          select: {
            id: true,
            username: true,
            firstName: true,
            lastName: true
          }
        }
      }
    });

    // Audit log
    await auditLog('UPDATE', 'NEWS', id, req.user!.id, {
      changes: updateData
    }, req.ip, req.get('User-Agent'));

    res.json(updatedArticle);
  } catch (error) {
    next(error);
  }
});

// Delete news article
router.delete('/admin/:id', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const article = await prisma.newsArticle.findUnique({
      where: { id },
      select: { id: true, title: true }
    });

    if (!article) {
      throw new AppError('Article not found', 404);
    }

    await prisma.newsArticle.delete({
      where: { id }
    });

    // Audit log
    await auditLog('DELETE', 'NEWS', id, req.user!.id, {
      title: article.title
    }, req.ip, req.get('User-Agent'));

    res.json({
      message: 'Article deleted successfully'
    });
  } catch (error) {
    next(error);
  }
});

// Toggle publish status
router.patch('/admin/:id/publish', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const article = await prisma.newsArticle.findUnique({
      where: { id }
    });

    if (!article) {
      throw new AppError('Article not found', 404);
    }

    const updatedArticle = await prisma.newsArticle.update({
      where: { id },
      data: {
        isPublished: !article.isPublished,
        publishedAt: !article.isPublished && !article.publishedAt ? new Date() : article.publishedAt
      }
    });

    // Audit log
    await auditLog('UPDATE', 'NEWS', id, req.user!.id, {
      action: updatedArticle.isPublished ? 'published' : 'unpublished'
    }, req.ip, req.get('User-Agent'));

    res.json({
      message: `Article ${updatedArticle.isPublished ? 'published' : 'unpublished'} successfully`,
      article: updatedArticle
    });
  } catch (error) {
    next(error);
  }
});

export default router;
