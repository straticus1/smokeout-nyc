import express from 'express';
import paypal from 'paypal-rest-sdk';
import axios from 'axios';
import { prisma } from '../index';
import { authenticate, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../middleware/errorHandler';
import Joi from 'joi';

const router = express.Router();

// Configure PayPal
paypal.configure({
  mode: process.env.PAYPAL_MODE || 'sandbox',
  client_id: process.env.PAYPAL_CLIENT_ID!,
  client_secret: process.env.PAYPAL_CLIENT_SECRET!
});

const createDonationSchema = Joi.object({
  amount: Joi.number().required().min(1).max(10000),
  method: Joi.string().valid('paypal', 'bitcoin').required(),
  donorName: Joi.string().optional().max(100),
  donorEmail: Joi.string().email().optional()
});

// Create PayPal donation
router.post('/paypal/create', async (req, res, next) => {
  try {
    const { error, value } = createDonationSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { amount, donorName, donorEmail } = value;

    // Create PayPal payment
    const payment = {
      intent: 'sale',
      payer: {
        payment_method: 'paypal'
      },
      redirect_urls: {
        return_url: `${process.env.CLIENT_URL}/donation/success`,
        cancel_url: `${process.env.CLIENT_URL}/donation/cancel`
      },
      transactions: [{
        amount: {
          currency: 'USD',
          total: amount.toString()
        },
        description: 'Donation to SmokeoutNYC',
        item_list: {
          items: [{
            name: 'Donation to SmokeoutNYC',
            sku: 'donation',
            price: amount.toString(),
            currency: 'USD',
            quantity: 1
          }]
        }
      }]
    };

    paypal.payment.create(payment, async (error, payment) => {
      if (error) {
        console.error('PayPal payment creation error:', error);
        throw new AppError('Failed to create PayPal payment', 500);
      } else {
        // Save donation record with pending status
        const donation = await prisma.donation.create({
          data: {
            amount,
            method: 'paypal',
            transactionId: payment.id!,
            donorName,
            donorEmail,
            status: 'pending'
          }
        });

        // Find approval URL
        const approvalUrl = payment.links?.find(link => link.rel === 'approval_url')?.href;

        res.json({
          paymentId: payment.id,
          approvalUrl,
          donation
        });
      }
    });
  } catch (error) {
    next(error);
  }
});

// Execute PayPal payment
router.post('/paypal/execute', async (req, res, next) => {
  try {
    const { paymentId, payerId } = req.body;

    if (!paymentId || !payerId) {
      throw new AppError('Payment ID and Payer ID are required', 400);
    }

    const executePayment = {
      payer_id: payerId
    };

    paypal.payment.execute(paymentId, executePayment, async (error, payment) => {
      if (error) {
        console.error('PayPal payment execution error:', error);
        
        // Update donation status to failed
        await prisma.donation.update({
          where: { transactionId: paymentId },
          data: { status: 'failed' }
        });

        throw new AppError('Failed to execute PayPal payment', 500);
      } else {
        // Update donation status to completed
        const donation = await prisma.donation.update({
          where: { transactionId: paymentId },
          data: { status: 'completed' }
        });

        res.json({
          message: 'Donation completed successfully',
          donation,
          payment
        });
      }
    });
  } catch (error) {
    next(error);
  }
});

// Create Bitcoin donation
router.post('/bitcoin/create', async (req, res, next) => {
  try {
    const { error, value } = createDonationSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { amount, donorName, donorEmail } = value;

    // Generate a unique Bitcoin address or use a static one
    // For simplicity, using a static address - in production you'd generate unique addresses
    const bitcoinAddress = process.env.BITCOIN_WALLET_ADDRESS;

    if (!bitcoinAddress) {
      throw new AppError('Bitcoin donations not configured', 500);
    }

    // Get current Bitcoin price to calculate BTC amount
    const priceResponse = await axios.get('https://api.coinbase.com/v2/exchange-rates?currency=BTC');
    const usdToBtcRate = parseFloat(priceResponse.data.data.rates.USD);
    const btcAmount = amount / usdToBtcRate;

    // Create donation record
    const donation = await prisma.donation.create({
      data: {
        amount,
        method: 'bitcoin',
        transactionId: `btc_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
        donorName,
        donorEmail,
        status: 'pending'
      }
    });

    res.json({
      donation,
      bitcoinAddress,
      btcAmount: btcAmount.toFixed(8),
      usdAmount: amount,
      instructions: 'Send the exact BTC amount to the provided address. Your donation will be confirmed once the transaction is verified on the blockchain.'
    });
  } catch (error) {
    next(error);
  }
});

// Verify Bitcoin transaction (webhook or manual check)
router.post('/bitcoin/verify', async (req, res, next) => {
  try {
    const { donationId, txHash } = req.body;

    if (!donationId || !txHash) {
      throw new AppError('Donation ID and transaction hash are required', 400);
    }

    const donation = await prisma.donation.findUnique({
      where: { id: donationId }
    });

    if (!donation) {
      throw new AppError('Donation not found', 404);
    }

    if (donation.method !== 'bitcoin') {
      throw new AppError('Invalid donation method', 400);
    }

    // In a real implementation, you would verify the transaction on the blockchain
    // For now, we'll just mark it as completed
    const updatedDonation = await prisma.donation.update({
      where: { id: donationId },
      data: {
        status: 'completed',
        transactionId: txHash
      }
    });

    res.json({
      message: 'Bitcoin donation verified successfully',
      donation: updatedDonation
    });
  } catch (error) {
    next(error);
  }
});

// Get donation statistics
router.get('/stats', async (req, res, next) => {
  try {
    const stats = await prisma.donation.aggregate({
      where: { status: 'completed' },
      _sum: { amount: true },
      _count: { id: true }
    });

    const paypalStats = await prisma.donation.aggregate({
      where: {
        status: 'completed',
        method: 'paypal'
      },
      _sum: { amount: true },
      _count: { id: true }
    });

    const bitcoinStats = await prisma.donation.aggregate({
      where: {
        status: 'completed',
        method: 'bitcoin'
      },
      _sum: { amount: true },
      _count: { id: true }
    });

    res.json({
      total: {
        amount: stats._sum.amount || 0,
        count: stats._count.id || 0
      },
      paypal: {
        amount: paypalStats._sum.amount || 0,
        count: paypalStats._count.id || 0
      },
      bitcoin: {
        amount: bitcoinStats._sum.amount || 0,
        count: bitcoinStats._count.id || 0
      }
    });
  } catch (error) {
    next(error);
  }
});

// Get recent donations (public, anonymized)
router.get('/recent', async (req, res, next) => {
  try {
    const { limit = '10' } = req.query;
    const limitNum = parseInt(limit as string);

    const donations = await prisma.donation.findMany({
      where: { status: 'completed' },
      select: {
        id: true,
        amount: true,
        method: true,
        donorName: true, // Only include if donor chose to share
        createdAt: true
      },
      orderBy: { createdAt: 'desc' },
      take: limitNum
    });

    // Anonymize donor names (show only first name or "Anonymous")
    const anonymizedDonations = donations.map(donation => ({
      ...donation,
      donorName: donation.donorName 
        ? donation.donorName.split(' ')[0] + ' ****'
        : 'Anonymous'
    }));

    res.json(anonymizedDonations);
  } catch (error) {
    next(error);
  }
});

// Admin: Get all donations
router.get('/admin/all', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    // Check admin permissions
    if (req.user!.role !== 'ADMIN' && req.user!.role !== 'SUPER_ADMIN') {
      throw new AppError('Unauthorized', 403);
    }

    const { page = '1', limit = '20', status } = req.query;
    const pageNum = parseInt(page as string);
    const limitNum = parseInt(limit as string);
    const skip = (pageNum - 1) * limitNum;

    const where: any = {};
    if (status) {
      where.status = status;
    }

    const donations = await prisma.donation.findMany({
      where,
      include: {
        user: {
          select: {
            id: true,
            email: true,
            username: true,
            firstName: true,
            lastName: true
          }
        }
      },
      orderBy: { createdAt: 'desc' },
      skip,
      take: limitNum
    });

    const total = await prisma.donation.count({ where });

    res.json({
      donations,
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
