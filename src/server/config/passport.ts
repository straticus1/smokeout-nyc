import passport from 'passport';
import { Strategy as JwtStrategy, ExtractJwt } from 'passport-jwt';
import { Strategy as GoogleStrategy } from 'passport-google-oauth20';
import { Strategy as FacebookStrategy } from 'passport-facebook';
import { prisma } from '../index';

export function setupPassport() {
  // JWT Strategy
  passport.use(new JwtStrategy({
    jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
    secretOrKey: process.env.JWT_SECRET!
  }, async (payload, done) => {
    try {
      const user = await prisma.user.findUnique({
        where: { id: payload.userId },
        select: {
          id: true,
          email: true,
          username: true,
          firstName: true,
          lastName: true,
          avatar: true,
          role: true,
          isEmailVerified: true,
          isActive: true,
          isSuspended: true,
          suspensionReason: true
        }
      });

      if (user) {
        // Check if user is suspended or inactive
        if (!user.isActive || user.isSuspended) {
          return done(null, false);
        }
        return done(null, user);
      } else {
        return done(null, false);
      }
    } catch (error) {
      return done(error, false);
    }
  }));

  // Google OAuth Strategy
  if (process.env.GOOGLE_CLIENT_ID && process.env.GOOGLE_CLIENT_SECRET) {
    passport.use(new GoogleStrategy({
      clientID: process.env.GOOGLE_CLIENT_ID,
      clientSecret: process.env.GOOGLE_CLIENT_SECRET,
      callbackURL: `${process.env.SERVER_URL}/api/auth/google/callback`
    }, async (accessToken, refreshToken, profile, done) => {
      try {
        // Check if user already exists
        let user = await prisma.user.findUnique({
          where: { googleId: profile.id }
        });

        if (user) {
          // Update last login
          await prisma.user.update({
            where: { id: user.id },
            data: { lastLoginAt: new Date() }
          });
          return done(null, user);
        }

        // Check if user exists with same email
        user = await prisma.user.findUnique({
          where: { email: profile.emails?.[0].value }
        });

        if (user) {
          // Link Google account to existing user
          user = await prisma.user.update({
            where: { id: user.id },
            data: {
              googleId: profile.id,
              isEmailVerified: true,
              lastLoginAt: new Date()
            }
          });
          return done(null, user);
        }

        // Create new user
        user = await prisma.user.create({
          data: {
            googleId: profile.id,
            email: profile.emails?.[0].value || '',
            firstName: profile.name?.givenName,
            lastName: profile.name?.familyName,
            avatar: profile.photos?.[0].value,
            isEmailVerified: true,
            lastLoginAt: new Date()
          }
        });

        return done(null, user);
      } catch (error) {
        return done(error as Error, undefined);
      }
    }));
  }

  // Facebook OAuth Strategy
  if (process.env.FACEBOOK_APP_ID && process.env.FACEBOOK_APP_SECRET) {
    passport.use(new FacebookStrategy({
      clientID: process.env.FACEBOOK_APP_ID,
      clientSecret: process.env.FACEBOOK_APP_SECRET,
      callbackURL: `${process.env.SERVER_URL}/api/auth/facebook/callback`,
      profileFields: ['id', 'emails', 'name', 'picture']
    }, async (accessToken, refreshToken, profile, done) => {
      try {
        // Check if user already exists
        let user = await prisma.user.findUnique({
          where: { facebookId: profile.id }
        });

        if (user) {
          // Update last login
          await prisma.user.update({
            where: { id: user.id },
            data: { lastLoginAt: new Date() }
          });
          return done(null, user);
        }

        // Check if user exists with same email
        user = await prisma.user.findUnique({
          where: { email: profile.emails?.[0].value }
        });

        if (user) {
          // Link Facebook account to existing user
          user = await prisma.user.update({
            where: { id: user.id },
            data: {
              facebookId: profile.id,
              isEmailVerified: true,
              lastLoginAt: new Date()
            }
          });
          return done(null, user);
        }

        // Create new user
        user = await prisma.user.create({
          data: {
            facebookId: profile.id,
            email: profile.emails?.[0].value || '',
            firstName: profile.name?.givenName,
            lastName: profile.name?.familyName,
            avatar: profile.photos?.[0].value,
            isEmailVerified: true,
            lastLoginAt: new Date()
          }
        });

        return done(null, user);
      } catch (error) {
        return done(error as Error, undefined);
      }
    }));
  }
}
