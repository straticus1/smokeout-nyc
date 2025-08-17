import { prisma } from '../index';

export async function auditLog(
  action: string,
  entity: string,
  entityId: string,
  adminId: string,
  details?: any,
  ipAddress?: string,
  userAgent?: string
): Promise<void> {
  try {
    await prisma.auditLog.create({
      data: {
        action,
        entity,
        entityId,
        adminId,
        details: details || {},
        ipAddress: ipAddress || 'unknown',
        userAgent: userAgent || 'unknown'
      }
    });
  } catch (error) {
    console.error('Failed to create audit log:', error);
    // Don't throw error to avoid breaking the main operation
  }
}

export async function getAuditLogs(
  page: number = 1,
  limit: number = 50,
  entity?: string,
  adminId?: string
) {
  const skip = (page - 1) * limit;
  const where: any = {};
  
  if (entity) {
    where.entity = entity;
  }
  
  if (adminId) {
    where.adminId = adminId;
  }

  const [logs, total] = await Promise.all([
    prisma.auditLog.findMany({
      where,
      orderBy: {
        createdAt: 'desc'
      },
      skip,
      take: limit
    }),
    prisma.auditLog.count({ where })
  ]);

  return {
    logs,
    pagination: {
      page,
      limit,
      total,
      pages: Math.ceil(total / limit)
    }
  };
}
