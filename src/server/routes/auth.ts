import express from 'express';
import bcrypt from 'bcryptjs';
import passport from 'passport';
import crypto from 'crypto';
import { prisma } from '../index';
import { generateToken, authenticate, AuthenticatedRequest } from '../middleware/auth';
import { authRateLimiterMiddleware } from '../middleware/rateLimiter';
import { AppError } from '../middleware/errorHandler';
import { sendEmail } from '../utils/email';
import Joi from 'joi';

const router = express.Router();

// Validation schemas
const registerSchema = Joi.object({
  email: Joi.string().email().required(),
  username: Joi.string().alphanum().min(3).max(30).optional(),
  password: Joi.string().min(6).required(),
  firstName: Joi.string().max(50).optional(),
  lastName: Joi.string().max(50).optional()
});

const loginSchema = Joi.object({
  email: Joi.string().email().required(),
  password: Joi.string().required()
});

const resetPasswordSchema = Joi.object({
  email: Joi.string().email().required()
});

const confirmResetSchema = Joi.object({
  token: Joi.string().required(),
  password: Joi.string().min(6).required()
});

// Register
router.post('/register', authRateLimiterMiddleware, async (req, res, next) => {
  try {
    const { error, value } = registerSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { email, username, password, firstName, lastName } = value;

    // Check if user already exists
    const existingUser = await prisma.user.findFirst({
      where: {
        OR: [
          { email },
          ...(username ? [{ username }] : [])
        ]
      }
    });

    if (existingUser) {
      throw new AppError('User already exists with this email or username', 409);
    }

    // Hash password
    const hashedPassword = await bcrypt.hash(password, 12);

    // Generate email verification token
    const emailVerifyToken = crypto.randomBytes(32).toString('hex');

    // Create user
    const user = await prisma.user.create({
      data: {
        email,
        username,
        password: hashedPassword,
        firstName,
        lastName,
        emailVerifyToken
      },
      select: {
        id: true,
        email: true,
        username: true,
        firstName: true,
        lastName: true,
        role: true,
        isEmailVerified: true
      }
    });

    // Send verification email
    try {
      await sendEmail({
        to: email,
        subject: 'Verify your SmokeoutNYC account',
        html: `
          <h1>Welcome to SmokeoutNYC!</h1>
          <p>Please click the link below to verify your email address:</p>
          <a href="${process.env.CLIENT_URL}/verify-email?token=${emailVerifyToken}">Verify Email</a>
          <p>If you didn't create this account, please ignore this email.</p>
        `
      });
    } catch (emailError) {
      console.error('Failed to send verification email:', emailError);
      // Don't fail registration if email sending fails
    }

    // Generate JWT
    const token = generateToken(user.id);

    res.status(201).json({
      message: 'User registered successfully. Please check your email for verification.',
      token,
      user
    });
  } catch (error) {
    next(error);
  }
});

// Login
router.post('/login', authRateLimiterMiddleware, async (req, res, next) => {
  try {
    const { error, value } = loginSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { email, password } = value;

    // Find user
    const user = await prisma.user.findUnique({
      where: { email }
    });

    if (!user || !user.password) {
      throw new AppError('Invalid credentials', 401);
    }

    // Check password
    const isValidPassword = await bcrypt.compare(password, user.password);
    if (!isValidPassword) {
      throw new AppError('Invalid credentials', 401);
    }

    // Update last login
    await prisma.user.update({
      where: { id: user.id },
      data: { lastLoginAt: new Date() }
    });

    // Generate JWT
    const token = generateToken(user.id);

    res.json({
      message: 'Login successful',
      token,
      user: {
        id: user.id,
        email: user.email,
        username: user.username,
        firstName: user.firstName,
        lastName: user.lastName,
        avatar: user.avatar,
        role: user.role,
        isEmailVerified: user.isEmailVerified
      }
    });
  } catch (error) {
    next(error);
  }
});

// Google OAuth
router.get('/google', passport.authenticate('google', {
  scope: ['profile', 'email']
}));

router.get('/google/callback', 
  passport.authenticate('google', { session: false }),
  (req: any, res) => {
    const token = generateToken(req.user.id);
    res.redirect(`${process.env.CLIENT_URL}/auth/callback?token=${token}`);
  }
);

// Facebook OAuth
router.get('/facebook', passport.authenticate('facebook', {
  scope: ['email']
}));

router.get('/facebook/callback',
  passport.authenticate('facebook', { session: false }),
  (req: any, res) => {
    const token = generateToken(req.user.id);
    res.redirect(`${process.env.CLIENT_URL}/auth/callback?token=${token}`);
  }
);

// Verify email
router.post('/verify-email', async (req, res, next) => {
  try {
    const { token } = req.body;
    
    if (!token) {
      throw new AppError('Verification token is required', 400);
    }

    const user = await prisma.user.findFirst({
      where: { emailVerifyToken: token }
    });

    if (!user) {
      throw new AppError('Invalid verification token', 400);
    }

    await prisma.user.update({
      where: { id: user.id },
      data: {
        isEmailVerified: true,
        emailVerifyToken: null
      }
    });

    res.json({ message: 'Email verified successfully' });
  } catch (error) {
    next(error);
  }
});

// Request password reset
router.post('/forgot-password', authRateLimiterMiddleware, async (req, res, next) => {
  try {
    const { error, value } = resetPasswordSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { email } = value;

    const user = await prisma.user.findUnique({
      where: { email }
    });

    if (!user) {
      // Don't reveal if email exists
      return res.json({ message: 'If an account with that email exists, we sent a password reset link.' });
    }

    // Generate reset token
    const resetToken = crypto.randomBytes(32).toString('hex');
    const resetExpires = new Date(Date.now() + 10 * 60 * 1000); // 10 minutes

    await prisma.user.update({
      where: { id: user.id },
      data: {
        resetPasswordToken: resetToken,
        resetPasswordExpires: resetExpires
      }
    });

    // Send reset email
    try {
      await sendEmail({
        to: email,
        subject: 'Password Reset - SmokeoutNYC',
        html: `
          <h1>Password Reset Request</h1>
          <p>You requested a password reset for your SmokeoutNYC account.</p>
          <p>Click the link below to reset your password (expires in 10 minutes):</p>
          <a href="${process.env.CLIENT_URL}/reset-password?token=${resetToken}">Reset Password</a>
          <p>If you didn't request this, please ignore this email.</p>
        `
      });
    } catch (emailError) {
      console.error('Failed to send reset email:', emailError);
      throw new AppError('Failed to send reset email', 500);
    }

    res.json({ message: 'If an account with that email exists, we sent a password reset link.' });
  } catch (error) {
    next(error);
  }
});

// Reset password
router.post('/reset-password', authRateLimiterMiddleware, async (req, res, next) => {
  try {
    const { error, value } = confirmResetSchema.validate(req.body);
    if (error) {
      throw new AppError(error.details[0].message, 400);
    }

    const { token, password } = value;

    const user = await prisma.user.findFirst({
      where: {
        resetPasswordToken: token,
        resetPasswordExpires: {
          gt: new Date()
        }
      }
    });

    if (!user) {
      throw new AppError('Invalid or expired reset token', 400);
    }

    // Hash new password
    const hashedPassword = await bcrypt.hash(password, 12);

    // Update user
    await prisma.user.update({
      where: { id: user.id },
      data: {
        password: hashedPassword,
        resetPasswordToken: null,
        resetPasswordExpires: null
      }
    });

    res.json({ message: 'Password reset successfully' });
  } catch (error) {
    next(error);
  }
});

// Get current user
router.get('/me', authenticate, (req: AuthenticatedRequest, res) => {
  res.json({ user: req.user });
});

// Logout (client-side token removal, but we can track it)
router.post('/logout', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    // Could implement token blacklisting here if needed
    res.json({ message: 'Logged out successfully' });
  } catch (error) {
    next(error);
  }
});

export default router;
