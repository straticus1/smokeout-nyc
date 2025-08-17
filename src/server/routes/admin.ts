import express from 'express';
import { prisma } from '../index';
import { authenticate, requireRole, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import { OperationSmokeoutScraper } from '../scripts/scrapeOperationSmokeout';
import { auditLog, getAuditLogs } from '../utils/audit';
import { upload, processAvatar } from '../utils/fileUpload';
import { sendBulkEmail } from '../utils/email';
import Joi from 'joi';
import { UserRole, StoreStatus } from '@prisma/client';
import DOMPurify from 'dompurify';
import { JSDOM } from 'jsdom';

// Initialize DOMPurify for server-side XSS protection
const window = new JSDOM('').window;
const purify = DOMPurify(window);

const router = express.Router();

const updateUserRoleSchema = Joi.object({
  role: Joi.string().valid(...Object.values(UserRole)).required()
});

const suspendUserSchema = Joi.object({
  reason: Joi.string().required().max(500),
  duration: Joi.number().optional().min(1).max(365) // days
});

const bulkUserActionSchema = Joi.object({
  userIds: Joi.array().items(Joi.string()).min(1).max(1000).required(),
  action: Joi.string().valid('suspend', 'delete', 'activate').required(),
  reason: Joi.string().optional().max(500)
});

const messageAllUsersSchema = Joi.object({
  subject: Joi.string().required().max(255),
  content: Joi.string().required().max(10000),
  sendEmail: Joi.boolean().default(true),
  sendInApp: Joi.boolean().default(true)
});

const createAdvertisementSchema = Joi.object({
  title: Joi.string().required().max(200),
  content: Joi.string().required().max(2000),
  imageUrl: Joi.string().uri().optional(),
  targetUrl: Joi.string().uri().optional(),
  storeId: Joi.string().optional(),
  position: Joi.string().required().valid('header', 'sidebar', 'footer', 'banner'),
  startDate: Joi.date().required(),
  endDate: Joi.date().required()
});

// Dashboard stats
router.get('/dashboard', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    // Get various statistics
    const [
      totalUsers,
      totalStores,
      totalProducts,
      totalDonations,
      recentUsers,
      storesByStatus,
      donationStats
    ] = await Promise.all([
      prisma.user.count(),
      prisma.store.count(),
      prisma.product.count(),
      prisma.donation.count({ where: { status: 'completed' } }),
      prisma.user.count({
        where: {
          createdAt: {
            gte: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000) // Last 30 days
          }
        }
      }),
      prisma.store.groupBy({
        by: ['status'],
        _count: {
          id: true
        }
      }),
      prisma.donation.aggregate({
        where: { status: 'completed' },
        _sum: { amount: true }
      })
    ]);

    res.json({
      stats: {
        totalUsers,
        totalStores,
        totalProducts,
        totalDonations,
        recentUsers,
        totalDonationAmount: donationStats._sum.amount || 0
      },
      storesByStatus: storesByStatus.reduce((acc, item) => {
        acc[item.status] = item._count.id;
        return acc;
      }, {} as Record<string, number>)
    });
  } catch (error) {
    next(error);
  }
});

// Get all users with pagination
router.get('/users', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { page = '1', limit = '20', search, role } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const where: any = {};

    if (search) {
      where.OR = [
        { email: { contains: search as string, mode: 'insensitive' } },
        { username: { contains: search as string, mode: 'insensitive' } },
        { firstName: { contains: search as string, mode: 'insensitive' } },
        { lastName: { contains: search as string, mode: 'insensitive' } }
      ];
    }

    if (role) {
      where.role = role;
    }

    const users = await prisma.user.findMany({
      where,
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
            comments: true,
            donations: true
          }
        }
      },
      orderBy: {
        createdAt: 'desc'
      },
      skip,
      take: limitNum
    });

    const total = await prisma.user.count({ where });

    res.json({
      users,
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

// Update user role
router.patch('/users/:id/role', authenticate, requireRole([UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = updateUserRoleSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const user = await prisma.user.findUnique({
      where: { id }
    });

    if (!user) {
      throw new AppError('User not found', 404);
    }

    // Prevent changing own role
    if (id === req.user!.id) {
      throw new AppError('Cannot change your own role', 400);
    }

    const updatedUser = await prisma.user.update({
      where: { id },
      data: { role: value.role },
      select: {
        id: true,
        email: true,
        username: true,
        firstName: true,
        lastName: true,
        role: true
      }
    });

    res.json({
      message: 'User role updated successfully',
      user: updatedUser
    });
  } catch (error) {
    next(error);
  }
});

// Suspend user
router.post('/users/:id/suspend', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = suspendUserSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    if (id === req.user!.id) {
      throw new AppError('Cannot suspend your own account', 400);
    }

    const user = await prisma.user.findUnique({
      where: { id },
      select: { id: true, email: true, username: true, isSuspended: true }
    });

    if (!user) {
      throw new AppError('User not found', 404);
    }

    if (user.isSuspended) {
      throw new AppError('User is already suspended', 400);
    }

    const updatedUser = await prisma.user.update({
      where: { id },
      data: {
        isSuspended: true,
        suspendedAt: new Date(),
        suspendedBy: req.user!.id,
        suspensionReason: purify.sanitize(value.reason)
      },
      select: {
        id: true,
        email: true,
        username: true,
        isSuspended: true,
        suspendedAt: true,
        suspensionReason: true
      }
    });

    // Audit log
    await auditLog('SUSPEND', 'USER', id, req.user!.id, {
      reason: value.reason,
      duration: value.duration
    }, req.ip, req.get('User-Agent'));

    res.json({
      message: 'User suspended successfully',
      user: updatedUser
    });
  } catch (error) {
    next(error);
  }
});

// Unsuspend user
router.post('/users/:id/unsuspend', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const user = await prisma.user.findUnique({
      where: { id },
      select: { id: true, email: true, username: true, isSuspended: true }
    });

    if (!user) {
      throw new AppError('User not found', 404);
    }

    if (!user.isSuspended) {
      throw new AppError('User is not suspended', 400);
    }

    const updatedUser = await prisma.user.update({
      where: { id },
      data: {
        isSuspended: false,
        suspendedAt: null,
        suspendedBy: null,
        suspensionReason: null
      },
      select: {
        id: true,
        email: true,
        username: true,
        isSuspended: true
      }
    });

    // Audit log
    await auditLog('UNSUSPEND', 'USER', id, req.user!.id, {}, req.ip, req.get('User-Agent'));

    res.json({
      message: 'User unsuspended successfully',
      user: updatedUser
    });
  } catch (error) {
    next(error);
  }
});

// Delete user
router.delete('/users/:id', authenticate, requireRole([UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    if (id === req.user!.id) {
      throw new AppError('Cannot delete your own account', 400);
    }

    const user = await prisma.user.findUnique({
      where: { id },
      select: { id: true, email: true, username: true }
    });

    if (!user) {
      throw new AppError('User not found', 404);
    }

    await prisma.user.delete({
      where: { id }
    });

    // Audit log
    await auditLog('DELETE', 'USER', id, req.user!.id, {
      email: user.email,
      username: user.username
    }, req.ip, req.get('User-Agent'));

    res.json({
      message: 'User deleted successfully'
    });
  } catch (error) {
    next(error);
  }
});

// Bulk user actions
router.post('/users/bulk-action', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { error, value } = bulkUserActionSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { userIds, action, reason } = value;

    // Prevent actions on own account
    if (userIds.includes(req.user!.id)) {
      throw new AppError('Cannot perform bulk actions on your own account', 400);
    }

    // Verify users exist
    const users = await prisma.user.findMany({
      where: { id: { in: userIds } },
      select: { id: true, email: true, username: true }
    });

    if (users.length !== userIds.length) {
      throw new AppError('Some users not found', 404);
    }

    let result: any;
    let actionText = '';

    switch (action) {
      case 'suspend':
        result = await prisma.user.updateMany({
          where: { id: { in: userIds } },
          data: {
            isSuspended: true,
            suspendedAt: new Date(),
            suspendedBy: req.user!.id,
            suspensionReason: reason ? purify.sanitize(reason) : 'Bulk suspension'
          }
        });
        actionText = 'suspended';
        break;

      case 'activate':
        result = await prisma.user.updateMany({
          where: { id: { in: userIds } },
          data: {
            isActive: true,
            isSuspended: false,
            suspendedAt: null,
            suspendedBy: null,
            suspensionReason: null
          }
        });
        actionText = 'activated';
        break;

      case 'delete':
        if (req.user!.role !== UserRole.SUPER_ADMIN) {
          throw new AppError('Only super admins can bulk delete users', 403);
        }
        result = await prisma.user.deleteMany({
          where: { id: { in: userIds } }
        });
        actionText = 'deleted';
        break;

      default:
        throw new AppError('Invalid action', 400);
    }

    // Audit log for each user
    for (const user of users) {
      await auditLog(action.toUpperCase(), 'USER', user.id, req.user!.id, {
        bulkAction: true,
        reason: reason || 'Bulk action'
      }, req.ip, req.get('User-Agent'));
    }

    res.json({
      message: `Successfully ${actionText} ${result.count} users`,
      affectedCount: result.count
    });
  } catch (error) {
    next(error);
  }
});

// Parse CSV/TSV for bulk user operations
router.post('/users/parse-bulk-data', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { data, format } = req.body; // format: 'csv', 'tsv', or 'comma'
    
    if (!data || typeof data !== 'string') {
      throw new AppError('Data is required', 400);
    }

    let userIdentifiers: string[] = [];
    
    if (format === 'csv' || format === 'tsv') {
      const delimiter = format === 'csv' ? ',' : '\t';
      const lines = data.split('\n').filter(line => line.trim());
      
      // Assume first column contains emails or usernames
      userIdentifiers = lines.map(line => {
        const columns = line.split(delimiter);
        return columns[0]?.trim().replace(/['"]/g, ''); // Remove quotes
      }).filter(id => id && id.length > 0);
    } else {
      // Comma separated
      userIdentifiers = data.split(',').map((id: string) => id.trim()).filter((id: string) => id.length > 0);
    }

    // Sanitize and validate identifiers
    userIdentifiers = userIdentifiers.map(id => purify.sanitize(id));

    // Find matching users
    const users = await prisma.user.findMany({
      where: {
        OR: [
          { email: { in: userIdentifiers } },
          { username: { in: userIdentifiers } },
          { id: { in: userIdentifiers } }
        ]
      },
      select: {
        id: true,
        email: true,
        username: true,
        firstName: true,
        lastName: true,
        role: true,
        isActive: true,
        isSuspended: true
      }
    });

    const foundIdentifiers = users.map(u => [u.email, u.username, u.id]).flat().filter(Boolean);
    const notFound = userIdentifiers.filter(id => !foundIdentifiers.includes(id));

    res.json({
      users,
      totalFound: users.length,
      totalRequested: userIdentifiers.length,
      notFound
    });
  } catch (error) {
    next(error);
  }
});

// Message all users
router.post('/users/message-all', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { error, value } = messageAllUsersSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { subject, content, sendEmail, sendInApp } = value;

    // Sanitize content
    const sanitizedSubject = purify.sanitize(subject);
    const sanitizedContent = purify.sanitize(content);

    // Get all active users
    const users = await prisma.user.findMany({
      where: {
        isActive: true,
        isSuspended: false
      },
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true
      }
    });

    let emailCount = 0;
    let messageCount = 0;

    // Send in-app messages
    if (sendInApp) {
      const messagePromises = users.map(user =>
        prisma.message.create({
          data: {
            content: `${sanitizedSubject}\n\n${sanitizedContent}`,
            type: 'ADMIN_BROADCAST',
            senderId: req.user!.id,
            receiverId: user.id,
            expiresAt: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000) // 1 year
          }
        })
      );

      await Promise.all(messagePromises);
      messageCount = users.length;
    }

    // Send emails
    if (sendEmail) {
      try {
        const emails = users.map(u => u.email);
        await sendBulkEmail(
          emails,
          sanitizedSubject,
          `
            <h2>${sanitizedSubject}</h2>
            <div>${sanitizedContent}</div>
            <hr>
            <p><small>This message was sent to all users by the SmokeoutNYC administration.</small></p>
          `
        );
        emailCount = users.length;
      } catch (emailError) {
        console.error('Failed to send bulk emails:', emailError);
      }
    }

    // Audit log
    await auditLog('MESSAGE_ALL', 'USER', 'bulk', req.user!.id, {
      subject: sanitizedSubject,
      recipientCount: users.length,
      sendEmail,
      sendInApp
    }, req.ip, req.get('User-Agent'));

    res.json({
      message: 'Messages sent successfully',
      recipientCount: users.length,
      emailsSent: emailCount,
      inAppMessagesSent: messageCount
    });
  } catch (error) {
    next(error);
  }
});

// Run Operation Smokeout scraper
router.post('/scrape/operation-smokeout', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const scraper = new OperationSmokeoutScraper();
    
    // Run scraper in background
    scraper.run().catch(error => {
      console.error('Scraper error:', error);
    });

    res.json({
      message: 'Operation Smokeout scraper started. Check logs for progress.'
    });
  } catch (error) {
    next(error);
  }
});

// Get audit logs
router.get('/audit-logs', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { page = '1', limit = '50', entity, adminId } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);

    const result = await getAuditLogs(pageNum, limitNum, entity as string, adminId as string);
    res.json(result);
  } catch (error) {
    next(error);
  }
});

// Get system logs (simplified version)
router.get('/logs', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    // This would typically read from log files
    // For now, return recent activities from database
    const recentActivities = await Promise.all([
      prisma.user.findMany({
        take: 5,
        orderBy: { createdAt: 'desc' },
        select: {
          id: true,
          email: true,
          createdAt: true
        }
      }),
      prisma.store.findMany({
        take: 5,
        orderBy: { createdAt: 'desc' },
        select: {
          id: true,
          name: true,
          status: true,
          createdAt: true
        }
      }),
      prisma.donation.findMany({
        take: 5,
        orderBy: { createdAt: 'desc' },
        select: {
          id: true,
          amount: true,
          method: true,
          status: true,
          createdAt: true
        }
      })
    ]);

    res.json({
      recentUsers: recentActivities[0],
      recentStores: recentActivities[1],
      recentDonations: recentActivities[2]
    });
  } catch (error) {
    next(error);
  }
});

// Advertisement management
router.get('/advertisements', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { page = '1', limit = '20', active } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const where: any = {};
    if (active !== undefined) {
      where.isActive = active === 'true';
    }

    const ads = await prisma.advertisement.findMany({
      where,
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

    const total = await prisma.advertisement.count({ where });

    res.json({
      advertisements: ads,
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

// Create advertisement
router.post('/advertisements', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { error, value } = createAdvertisementSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const advertisement = await prisma.advertisement.create({
      data: value,
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

    res.status(201).json(advertisement);
  } catch (error) {
    next(error);
  }
});

// Update advertisement
router.put('/advertisements/:id', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { id } = req.params;
    const { error, value } = createAdvertisementSchema.validate(req.body);
    
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const advertisement = await prisma.advertisement.update({
      where: { id },
      data: value,
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

    res.json(advertisement);
  } catch (error) {
    next(error);
  }
});

// Delete advertisement
router.delete('/advertisements/:id', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { id } = req.params;

    await prisma.advertisement.delete({
      where: { id }
    });

    res.json({
      message: 'Advertisement deleted successfully'
    });
  } catch (error) {
    next(error);
  }
});

// Toggle advertisement status
router.patch('/advertisements/:id/toggle', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { id } = req.params;

    const ad = await prisma.advertisement.findUnique({
      where: { id }
    });

    if (!ad) {
      throw new AppError('Advertisement not found', 404);
    }

    const updatedAd = await prisma.advertisement.update({
      where: { id },
      data: { isActive: !ad.isActive }
    });

    res.json({
      message: `Advertisement ${updatedAd.isActive ? 'activated' : 'deactivated'} successfully`,
      advertisement: updatedAd
    });
  } catch (error) {
    next(error);
  }
});

// Bulk update store statuses
router.post('/stores/bulk-update', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { storeIds, status, reason } = req.body;

    if (!storeIds || !Array.isArray(storeIds) || storeIds.length === 0) {
      throw new AppError('Store IDs array is required', 400);
    }

    if (!Object.values(StoreStatus).includes(status)) {
      throw new AppError('Invalid status', 400);
    }

    const updateData: any = { status };
    if (reason) {
      updateData.closureReason = reason;
    }
    if (status === StoreStatus.CLOSED_OPERATION_SMOKEOUT) {
      updateData.operationSmokeoutDate = new Date();
    }

    const result = await prisma.store.updateMany({
      where: {
        id: { in: storeIds }
      },
      data: updateData
    });

    res.json({
      message: `Updated ${result.count} stores successfully`,
      updatedCount: result.count
    });
  } catch (error) {
    next(error);
  }
});

// Export data (CSV format)
router.get('/export/:type', authenticate, requireRole([UserRole.ADMIN, UserRole.SUPER_ADMIN]), async (req, res, next) => {
  try {
    const { type } = req.params;

    let data: any[] = [];
    let filename = '';

    switch (type) {
      case 'stores':
        data = await prisma.store.findMany({
          include: {
            owner: {
              select: {
                email: true,
                username: true
              }
            }
          }
        });
        filename = 'stores.csv';
        break;

      case 'users':
        data = await prisma.user.findMany({
          select: {
            id: true,
            email: true,
            username: true,
            firstName: true,
            lastName: true,
            role: true,
            isEmailVerified: true,
            createdAt: true,
            lastLoginAt: true
          }
        });
        filename = 'users.csv';
        break;

      case 'donations':
        data = await prisma.donation.findMany({
          where: { status: 'completed' },
          include: {
            user: {
              select: {
                email: true,
                username: true
              }
            }
          }
        });
        filename = 'donations.csv';
        break;

      default:
        throw new AppError('Invalid export type', 400);
    }

    // Convert to CSV (simplified - in production use a proper CSV library)
    const csv = convertToCSV(data);

    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', `attachment; filename=${filename}`);
    res.send(csv);
  } catch (error) {
    next(error);
  }
});

// Helper function to convert data to CSV
function convertToCSV(data: any[]): string {
  if (data.length === 0) return '';

  const headers = Object.keys(data[0]);
  const csvRows = [headers.join(',')];

  for (const row of data) {
    const values = headers.map(header => {
      const value = row[header];
      return typeof value === 'string' ? `"${value.replace(/"/g, '""')}"` : value;
    });
    csvRows.push(values.join(','));
  }

  return csvRows.join('\n');
}

export default router;
