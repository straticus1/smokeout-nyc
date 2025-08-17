import multer from 'multer';
import sharp from 'sharp';
import path from 'path';
import fs from 'fs';
import { AppError } from '../middleware/errorHandler';

// Ensure upload directories exist
const uploadDirs = ['uploads/avatars', 'uploads/news', 'uploads/stores'];
uploadDirs.forEach(dir => {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
});

// Configure multer for file uploads
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    let uploadPath = 'uploads/';
    
    if (file.fieldname === 'avatar') {
      uploadPath += 'avatars/';
    } else if (file.fieldname === 'newsImage') {
      uploadPath += 'news/';
    } else if (file.fieldname === 'storeImage') {
      uploadPath += 'stores/';
    }
    
    cb(null, uploadPath);
  },
  filename: (req, file, cb) => {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    const extension = path.extname(file.originalname);
    cb(null, file.fieldname + '-' + uniqueSuffix + extension);
  }
});

// File filter for security
const fileFilter = (req: any, file: Express.Multer.File, cb: multer.FileFilterCallback) => {
  // Check file type
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
  
  if (!allowedTypes.includes(file.mimetype)) {
    return cb(new AppError('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.', 400));
  }
  
  // Check file size (5MB max)
  const maxSize = 5 * 1024 * 1024; // 5MB
  if (file.size && file.size > maxSize) {
    return cb(new AppError('File too large. Maximum size is 5MB.', 400));
  }
  
  cb(null, true);
};

export const upload = multer({
  storage,
  fileFilter,
  limits: {
    fileSize: 5 * 1024 * 1024, // 5MB
    files: 1
  }
});

// Image processing utilities
export async function processAvatar(filePath: string): Promise<string> {
  const outputPath = filePath.replace(/\.[^/.]+$/, '_processed.jpg');
  
  await sharp(filePath)
    .resize(200, 200, {
      fit: 'cover',
      position: 'center'
    })
    .jpeg({ quality: 90 })
    .toFile(outputPath);
  
  // Delete original file
  fs.unlinkSync(filePath);
  
  return outputPath;
}

export async function processNewsImage(filePath: string): Promise<string> {
  const outputPath = filePath.replace(/\.[^/.]+$/, '_processed.jpg');
  
  await sharp(filePath)
    .resize(800, 600, {
      fit: 'cover',
      position: 'center'
    })
    .jpeg({ quality: 85 })
    .toFile(outputPath);
  
  // Delete original file
  fs.unlinkSync(filePath);
  
  return outputPath;
}

export async function processStoreImage(filePath: string): Promise<string> {
  const outputPath = filePath.replace(/\.[^/.]+$/, '_processed.jpg');
  
  await sharp(filePath)
    .resize(600, 400, {
      fit: 'cover',
      position: 'center'
    })
    .jpeg({ quality: 85 })
    .toFile(outputPath);
  
  // Delete original file
  fs.unlinkSync(filePath);
  
  return outputPath;
}

// Clean up old files
export function deleteFile(filePath: string): void {
  try {
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
    }
  } catch (error) {
    console.error('Error deleting file:', error);
  }
}
