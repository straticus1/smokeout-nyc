import { useState, useEffect, useCallback, useRef } from 'react';

// Security utility functions and hooks
export class SecurityUtils {
  
  // Input sanitization
  static sanitizeString(input: unknown): string {
    if (typeof input !== 'string') {
      return String(input || '').substring(0, 1000); // Limit length
    }
    
    return input
      .replace(/[<>\"'&]/g, (match) => {
        const entities: Record<string, string> = {
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#x27;',
          '&': '&amp;'
        };
        return entities[match] || match;
      })
      .substring(0, 1000); // Prevent excessively long inputs
  }

  // URL validation
  static isValidUrl(url: unknown): boolean {
    if (typeof url !== 'string') return false;
    
    try {
      const parsed = new URL(url);
      const allowedProtocols = ['http:', 'https:'];
      const allowedDomains = [
        'localhost',
        '127.0.0.1',
        'smokeout.nyc',
        // Add your allowed domains
      ];
      
      if (!allowedProtocols.includes(parsed.protocol)) {
        return false;
      }

      // For production, you might want to validate against allowed domains
      // return allowedDomains.some(domain => parsed.hostname.endsWith(domain));
      
      return true;
    } catch {
      return false;
    }
  }

  // Number validation and sanitization
  static safeNumber(value: unknown, defaultValue = 0, min?: number, max?: number): number {
    const num = Number(value);
    
    if (isNaN(num) || !isFinite(num)) {
      return defaultValue;
    }
    
    let result = num;
    if (typeof min === 'number') result = Math.max(result, min);
    if (typeof max === 'number') result = Math.min(result, max);
    
    return result;
  }

  // Array validation
  static safeArray<T>(value: unknown, maxLength = 100): T[] {
    if (!Array.isArray(value)) {
      return [];
    }
    
    return value.slice(0, maxLength) as T[];
  }

  // Object validation
  static safeObject(value: unknown): Record<string, unknown> {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
      return {};
    }
    
    return value as Record<string, unknown>;
  }

  // Rate limiting check
  static createRateLimiter(maxRequests: number, windowMs: number) {
    const requests = new Map<string, number[]>();
    
    return (identifier: string): boolean => {
      const now = Date.now();
      const windowStart = now - windowMs;
      
      if (!requests.has(identifier)) {
        requests.set(identifier, []);
      }
      
      const userRequests = requests.get(identifier)!;
      
      // Remove old requests
      const validRequests = userRequests.filter(time => time > windowStart);
      
      if (validRequests.length >= maxRequests) {
        return false; // Rate limit exceeded
      }
      
      validRequests.push(now);
      requests.set(identifier, validRequests);
      
      return true;
    };
  }
}

// Content Security Policy utilities
export class CSPUtils {
  static generateNonce(): string {
    const array = new Uint8Array(16);
    crypto.getRandomValues(array);
    return btoa(String.fromCharCode(...array));
  }

  static createCSPHeader(nonce: string): string {
    return [
      "default-src 'self'",
      `script-src 'self' 'nonce-${nonce}'`,
      `style-src 'self' 'unsafe-inline'`, // Tailwind requires unsafe-inline
      "img-src 'self' data: https:",
      "font-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com",
      "connect-src 'self' https://api.smokeout.nyc",
      "frame-src 'none'",
      "object-src 'none'",
      "base-uri 'self'",
      "form-action 'self'"
    ].join('; ');
  }
}

// Authentication utilities
export class AuthUtils {
  static validateToken(token: unknown): boolean {
    if (typeof token !== 'string') return false;
    
    // Basic JWT structure validation
    const parts = token.split('.');
    if (parts.length !== 3) return false;
    
    try {
      // Validate base64 encoding of header and payload
      atob(parts[0]);
      atob(parts[1]);
      return true;
    } catch {
      return false;
    }
  }

  static isTokenExpired(token: string): boolean {
    try {
      const payload = JSON.parse(atob(token.split('.')[1]));
      const exp = payload.exp;
      
      if (!exp) return true;
      
      return Date.now() >= exp * 1000;
    } catch {
      return true;
    }
  }
}

// Custom hooks for security

// Hook for secure API requests
interface UseSecureApiOptions {
  rateLimitKey?: string;
  maxRequests?: number;
  windowMs?: number;
}

export const useSecureApi = (options: UseSecureApiOptions = {}) => {
  const {
    rateLimitKey = 'default',
    maxRequests = 60,
    windowMs = 60000 // 1 minute
  } = options;

  const rateLimiter = useRef(SecurityUtils.createRateLimiter(maxRequests, windowMs));
  const [isBlocked, setIsBlocked] = useState(false);

  const makeRequest = useCallback(async (
    url: string,
    options: RequestInit = {}
  ): Promise<Response> => {
    // Check rate limiting
    if (!rateLimiter.current(rateLimitKey)) {
      setIsBlocked(true);
      throw new Error('Rate limit exceeded. Please try again later.');
    }

    setIsBlocked(false);

    // Validate URL
    if (!SecurityUtils.isValidUrl(url)) {
      throw new Error('Invalid URL provided');
    }

    // Add security headers
    const secureOptions: RequestInit = {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers
      },
      credentials: 'same-origin' // Prevent CSRF
    };

    // Add auth token if available
    const token = localStorage.getItem('authToken');
    if (token && AuthUtils.validateToken(token) && !AuthUtils.isTokenExpired(token)) {
      secureOptions.headers = {
        ...secureOptions.headers,
        'Authorization': `Bearer ${token}`
      };
    }

    return fetch(url, secureOptions);
  }, [rateLimitKey]);

  return { makeRequest, isBlocked };
};

// Hook for input validation
export const useSecureInput = (initialValue = '') => {
  const [value, setValue] = useState(initialValue);
  const [sanitizedValue, setSanitizedValue] = useState(initialValue);
  const [isValid, setIsValid] = useState(true);
  const [errors, setErrors] = useState<string[]>([]);

  const updateValue = useCallback((newValue: string, validators: Array<(val: string) => string | null> = []) => {
    const sanitized = SecurityUtils.sanitizeString(newValue);
    const validationErrors: string[] = [];

    // Run custom validators
    validators.forEach(validator => {
      const error = validator(sanitized);
      if (error) validationErrors.push(error);
    });

    setValue(newValue);
    setSanitizedValue(sanitized);
    setErrors(validationErrors);
    setIsValid(validationErrors.length === 0);
  }, []);

  return {
    value,
    sanitizedValue,
    isValid,
    errors,
    updateValue
  };
};

// Hook for XSS protection
export const useXSSProtection = () => {
  const sanitizeHtml = useCallback((html: string): string => {
    // This would typically use DOMPurify in a real implementation
    return html
      .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
      .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
      .replace(/javascript:/gi, '')
      .replace(/on\w+\s*=/gi, '');
  }, []);

  const createSafeHTML = useCallback((html: string) => {
    return { __html: sanitizeHtml(html) };
  }, [sanitizeHtml]);

  return { sanitizeHtml, createSafeHTML };
};

// Hook for CSRF protection
export const useCSRFProtection = () => {
  const [csrfToken, setCsrfToken] = useState<string | null>(null);

  useEffect(() => {
    // Get CSRF token from meta tag or API
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) {
      setCsrfToken(metaToken);
    } else {
      // Generate a token if not provided by server
      const token = CSPUtils.generateNonce();
      setCsrfToken(token);
    }
  }, []);

  const addCSRFHeader = useCallback((headers: Record<string, string> = {}) => {
    if (csrfToken) {
      return {
        ...headers,
        'X-CSRF-Token': csrfToken
      };
    }
    return headers;
  }, [csrfToken]);

  return { csrfToken, addCSRFHeader };
};

// Hook for session security
export const useSessionSecurity = () => {
  const [isSessionValid, setIsSessionValid] = useState(false);
  const [sessionWarning, setSessionWarning] = useState<string | null>(null);
  
  const checkSession = useCallback(() => {
    const token = localStorage.getItem('authToken');
    const lastActivity = localStorage.getItem('lastActivity');
    
    if (!token || !AuthUtils.validateToken(token)) {
      setIsSessionValid(false);
      setSessionWarning('Invalid session. Please log in again.');
      return;
    }
    
    if (AuthUtils.isTokenExpired(token)) {
      setIsSessionValid(false);
      setSessionWarning('Session expired. Please log in again.');
      localStorage.removeItem('authToken');
      localStorage.removeItem('lastActivity');
      return;
    }
    
    // Check for session timeout (30 minutes of inactivity)
    if (lastActivity) {
      const lastActivityTime = parseInt(lastActivity, 10);
      const thirtyMinutesAgo = Date.now() - (30 * 60 * 1000);
      
      if (lastActivityTime < thirtyMinutesAgo) {
        setIsSessionValid(false);
        setSessionWarning('Session timed out due to inactivity. Please log in again.');
        localStorage.removeItem('authToken');
        localStorage.removeItem('lastActivity');
        return;
      }
    }
    
    setIsSessionValid(true);
    setSessionWarning(null);
    
    // Update last activity
    localStorage.setItem('lastActivity', Date.now().toString());
  }, []);

  useEffect(() => {
    checkSession();
    
    // Check session every minute
    const interval = setInterval(checkSession, 60000);
    
    // Check session on user activity
    const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    const updateActivity = () => {
      localStorage.setItem('lastActivity', Date.now().toString());
    };
    
    activityEvents.forEach(event => {
      document.addEventListener(event, updateActivity, true);
    });
    
    return () => {
      clearInterval(interval);
      activityEvents.forEach(event => {
        document.removeEventListener(event, updateActivity, true);
      });
    };
  }, [checkSession]);

  return { isSessionValid, sessionWarning, checkSession };
};

// Common validators
export const validators = {
  required: (message = 'This field is required') => (value: string) => 
    value.trim().length === 0 ? message : null,
    
  minLength: (min: number, message?: string) => (value: string) =>
    value.length < min ? message || `Minimum ${min} characters required` : null,
    
  maxLength: (max: number, message?: string) => (value: string) =>
    value.length > max ? message || `Maximum ${max} characters allowed` : null,
    
  email: (message = 'Invalid email format') => (value: string) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(value) ? null : message;
  },
  
  noScript: (message = 'Script tags not allowed') => (value: string) =>
    /<script/i.test(value) ? message : null,
    
  alphanumeric: (message = 'Only letters and numbers allowed') => (value: string) => {
    const alphanumericRegex = /^[a-zA-Z0-9\s]*$/;
    return alphanumericRegex.test(value) ? null : message;
  }
};