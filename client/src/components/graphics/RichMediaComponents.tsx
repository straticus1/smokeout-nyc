import React, { useState, useRef, useEffect } from 'react';
import { motion, AnimatePresence, useAnimation } from 'framer-motion';
import { 
  PlayIcon, 
  PauseIcon, 
  SpeakerWaveIcon, 
  SpeakerXMarkIcon,
  ArrowsPointingOutIcon,
  XMarkIcon,
  ShareIcon,
  HeartIcon,
  ChatBubbleLeftIcon,
  EyeIcon,
  PhotoIcon,
  VideoCameraIcon,
  DocumentTextIcon,
  MicrophoneIcon
} from '@heroicons/react/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/react/24/solid';

// Interactive Image Gallery
interface GalleryImage {
  id: string;
  url: string;
  title: string;
  description: string;
  tags: string[];
  author: string;
  date: string;
}

interface ImageGalleryProps {
  images: GalleryImage[];
  columns?: number;
}

export const InteractiveImageGallery: React.FC<ImageGalleryProps> = ({ 
  images, 
  columns = 3 
}) => {
  const [selectedImage, setSelectedImage] = useState<GalleryImage | null>(null);
  const [filter, setFilter] = useState<string>('all');
  const [liked, setLiked] = useState<Set<string>>(new Set());

  const allTags = Array.from(new Set(images.flatMap(img => img.tags)));
  
  const filteredImages = images.filter(img => 
    filter === 'all' || img.tags.includes(filter)
  );

  const toggleLike = (imageId: string) => {
    const newLiked = new Set(liked);
    if (newLiked.has(imageId)) {
      newLiked.delete(imageId);
    } else {
      newLiked.add(imageId);
    }
    setLiked(newLiked);
  };

  return (
    <div className="bg-white rounded-2xl shadow-lg p-6">
      <div className="flex justify-between items-center mb-6">
        <h3 className="text-xl font-semibold text-gray-900">Cannabis Media Gallery</h3>
        
        {/* Filter Tags */}
        <div className="flex flex-wrap gap-2">
          <button
            onClick={() => setFilter('all')}
            className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
              filter === 'all'
                ? 'bg-green-600 text-white'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            All
          </button>
          {allTags.slice(0, 5).map((tag) => (
            <button
              key={tag}
              onClick={() => setFilter(tag)}
              className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
                filter === tag
                  ? 'bg-green-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {tag}
            </button>
          ))}
        </div>
      </div>

      {/* Image Grid */}
      <div className={`grid grid-cols-1 md:grid-cols-${Math.min(columns, 3)} lg:grid-cols-${columns} gap-4`}>
        <AnimatePresence>
          {filteredImages.map((image, index) => (
            <motion.div
              key={image.id}
              layout
              initial={{ opacity: 0, scale: 0.8 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.8 }}
              transition={{ delay: index * 0.05 }}
              className="relative group cursor-pointer overflow-hidden rounded-lg bg-gray-100"
              onClick={() => setSelectedImage(image)}
            >
              <img
                src={image.url}
                alt={image.title}
                className="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300"
              />
              
              {/* Overlay */}
              <div className="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all duration-300 flex items-end">
                <div className="p-4 text-white transform translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                  <h4 className="font-semibold mb-1">{image.title}</h4>
                  <p className="text-sm opacity-90">{image.description}</p>
                </div>
              </div>

              {/* Like Button */}
              <motion.button
                className="absolute top-3 right-3 p-2 rounded-full bg-white/80 backdrop-blur-sm"
                whileHover={{ scale: 1.1 }}
                whileTap={{ scale: 0.9 }}
                onClick={(e) => {
                  e.stopPropagation();
                  toggleLike(image.id);
                }}
              >
                {liked.has(image.id) ? (
                  <HeartSolidIcon className="w-5 h-5 text-red-500" />
                ) : (
                  <HeartIcon className="w-5 h-5 text-gray-600" />
                )}
              </motion.button>
            </motion.div>
          ))}
        </AnimatePresence>
      </div>

      {/* Fullscreen Modal */}
      <AnimatePresence>
        {selectedImage && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/90 flex items-center justify-center z-50 p-4"
            onClick={() => setSelectedImage(null)}
          >
            <motion.div
              initial={{ scale: 0.8, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.8, opacity: 0 }}
              className="relative max-w-4xl w-full bg-white rounded-lg overflow-hidden"
              onClick={(e) => e.stopPropagation()}
            >
              <img
                src={selectedImage.url}
                alt={selectedImage.title}
                className="w-full h-96 object-cover"
              />
              
              <div className="p-6">
                <h3 className="text-2xl font-bold mb-2">{selectedImage.title}</h3>
                <p className="text-gray-600 mb-4">{selectedImage.description}</p>
                
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-4 text-sm text-gray-500">
                    <span>By {selectedImage.author}</span>
                    <span>{selectedImage.date}</span>
                  </div>
                  
                  <div className="flex items-center space-x-2">
                    <button className="flex items-center space-x-1 text-gray-600 hover:text-gray-800">
                      <ShareIcon className="w-5 h-5" />
                      <span>Share</span>
                    </button>
                  </div>
                </div>
              </div>
              
              <button
                onClick={() => setSelectedImage(null)}
                className="absolute top-4 right-4 p-2 bg-white/80 backdrop-blur-sm rounded-full hover:bg-white transition-colors"
              >
                <XMarkIcon className="w-6 h-6" />
              </button>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};

// Rich Video Player
interface VideoPlayerProps {
  src: string;
  poster?: string;
  title: string;
  description: string;
  views: number;
  likes: number;
  author: string;
  publishedAt: string;
}

export const RichVideoPlayer: React.FC<VideoPlayerProps> = ({
  src,
  poster,
  title,
  description,
  views,
  likes,
  author,
  publishedAt
}) => {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isMuted, setIsMuted] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [isLiked, setIsLiked] = useState(false);
  const [showControls, setShowControls] = useState(true);

  useEffect(() => {
    const video = videoRef.current;
    if (!video) return;

    const handleTimeUpdate = () => setCurrentTime(video.currentTime);
    const handleDurationChange = () => setDuration(video.duration);
    const handlePlay = () => setIsPlaying(true);
    const handlePause = () => setIsPlaying(false);

    video.addEventListener('timeupdate', handleTimeUpdate);
    video.addEventListener('durationchange', handleDurationChange);
    video.addEventListener('play', handlePlay);
    video.addEventListener('pause', handlePause);

    return () => {
      video.removeEventListener('timeupdate', handleTimeUpdate);
      video.removeEventListener('durationchange', handleDurationChange);
      video.removeEventListener('play', handlePlay);
      video.removeEventListener('pause', handlePause);
    };
  }, []);

  const togglePlay = () => {
    const video = videoRef.current;
    if (!video) return;

    if (isPlaying) {
      video.pause();
    } else {
      video.play();
    }
  };

  const toggleMute = () => {
    const video = videoRef.current;
    if (!video) return;

    video.muted = !video.muted;
    setIsMuted(video.muted);
  };

  const handleSeek = (e: React.MouseEvent<HTMLDivElement>) => {
    const video = videoRef.current;
    if (!video) return;

    const rect = e.currentTarget.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const percentage = x / rect.width;
    video.currentTime = percentage * duration;
  };

  const toggleFullscreen = () => {
    const container = videoRef.current?.parentElement;
    if (!container) return;

    if (!isFullscreen) {
      container.requestFullscreen?.();
    } else {
      document.exitFullscreen?.();
    }
    setIsFullscreen(!isFullscreen);
  };

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  return (
    <div className="bg-white rounded-2xl shadow-lg overflow-hidden">
      <div 
        className="relative group"
        onMouseEnter={() => setShowControls(true)}
        onMouseLeave={() => setShowControls(false)}
      >
        <video
          ref={videoRef}
          src={src}
          poster={poster}
          className="w-full h-64 md:h-96 object-cover"
          onClick={togglePlay}
        />

        {/* Play/Pause Overlay */}
        <AnimatePresence>
          {(!isPlaying || showControls) && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="absolute inset-0 flex items-center justify-center bg-black/30"
            >
              <motion.button
                onClick={togglePlay}
                className="p-4 bg-white/90 backdrop-blur-sm rounded-full hover:bg-white transition-colors"
                whileHover={{ scale: 1.1 }}
                whileTap={{ scale: 0.9 }}
              >
                {isPlaying ? (
                  <PauseIcon className="w-8 h-8 text-gray-800" />
                ) : (
                  <PlayIcon className="w-8 h-8 text-gray-800 ml-1" />
                )}
              </motion.button>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Video Controls */}
        <AnimatePresence>
          {showControls && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: 20 }}
              className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4"
            >
              {/* Progress Bar */}
              <div 
                className="w-full h-2 bg-white/30 rounded-full mb-3 cursor-pointer"
                onClick={handleSeek}
              >
                <motion.div
                  className="h-full bg-green-500 rounded-full"
                  animate={{ width: `${(currentTime / duration) * 100}%` }}
                />
              </div>

              {/* Control Buttons */}
              <div className="flex items-center justify-between text-white">
                <div className="flex items-center space-x-3">
                  <button onClick={togglePlay} className="hover:text-green-400 transition-colors">
                    {isPlaying ? <PauseIcon className="w-5 h-5" /> : <PlayIcon className="w-5 h-5" />}
                  </button>
                  
                  <button onClick={toggleMute} className="hover:text-green-400 transition-colors">
                    {isMuted ? <SpeakerXMarkIcon className="w-5 h-5" /> : <SpeakerWaveIcon className="w-5 h-5" />}
                  </button>
                  
                  <span className="text-sm">
                    {formatTime(currentTime)} / {formatTime(duration)}
                  </span>
                </div>
                
                <button onClick={toggleFullscreen} className="hover:text-green-400 transition-colors">
                  <ArrowsPointingOutIcon className="w-5 h-5" />
                </button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* Video Info */}
      <div className="p-6">
        <h3 className="text-xl font-bold text-gray-900 mb-2">{title}</h3>
        <p className="text-gray-600 mb-4">{description}</p>
        
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4 text-sm text-gray-500">
            <div className="flex items-center space-x-1">
              <EyeIcon className="w-4 h-4" />
              <span>{views.toLocaleString()} views</span>
            </div>
            <span>By {author}</span>
            <span>{new Date(publishedAt).toLocaleDateString()}</span>
          </div>
          
          <div className="flex items-center space-x-3">
            <button
              onClick={() => setIsLiked(!isLiked)}
              className={`flex items-center space-x-1 px-3 py-2 rounded-lg transition-colors ${
                isLiked ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {isLiked ? (
                <HeartSolidIcon className="w-4 h-4" />
              ) : (
                <HeartIcon className="w-4 h-4" />
              )}
              <span>{(likes + (isLiked ? 1 : 0)).toLocaleString()}</span>
            </button>
            
            <button className="flex items-center space-x-1 px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors">
              <ShareIcon className="w-4 h-4" />
              <span>Share</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// News Article Card with Rich Media
interface NewsArticle {
  id: string;
  title: string;
  excerpt: string;
  content: string;
  author: string;
  publishedAt: string;
  featuredImage: string;
  category: string;
  tags: string[];
  readTime: number;
  views: number;
  likes: number;
}

export const NewsArticleCard: React.FC<{ article: NewsArticle }> = ({ article }) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const [isLiked, setIsLiked] = useState(false);

  return (
    <motion.article
      layout
      className="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow"
    >
      {/* Featured Image */}
      <div className="relative h-48 overflow-hidden">
        <img
          src={article.featuredImage}
          alt={article.title}
          className="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
        />
        
        {/* Category Badge */}
        <div className="absolute top-4 left-4">
          <span className="px-3 py-1 bg-green-600 text-white text-sm font-medium rounded-full">
            {article.category}
          </span>
        </div>
        
        {/* Reading Time */}
        <div className="absolute bottom-4 right-4">
          <span className="px-2 py-1 bg-black/50 text-white text-xs rounded backdrop-blur-sm">
            {article.readTime} min read
          </span>
        </div>
      </div>

      {/* Content */}
      <div className="p-6">
        <h2 className="text-xl font-bold text-gray-900 mb-2 line-clamp-2">
          {article.title}
        </h2>
        
        <p className="text-gray-600 mb-4 line-clamp-3">
          {article.excerpt}
        </p>

        {/* Article Meta */}
        <div className="flex items-center justify-between text-sm text-gray-500 mb-4">
          <div className="flex items-center space-x-3">
            <span>By {article.author}</span>
            <span>•</span>
            <span>{new Date(article.publishedAt).toLocaleDateString()}</span>
            <span>•</span>
            <div className="flex items-center space-x-1">
              <EyeIcon className="w-4 h-4" />
              <span>{article.views.toLocaleString()}</span>
            </div>
          </div>
        </div>

        {/* Tags */}
        <div className="flex flex-wrap gap-2 mb-4">
          {article.tags.slice(0, 3).map((tag) => (
            <span
              key={tag}
              className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full"
            >
              #{tag}
            </span>
          ))}
        </div>

        {/* Expanded Content */}
        <AnimatePresence>
          {isExpanded && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={{ opacity: 0, height: 0 }}
              className="prose prose-sm max-w-none mb-4"
            >
              <div dangerouslySetInnerHTML={{ __html: article.content }} />
            </motion.div>
          )}
        </AnimatePresence>

        {/* Actions */}
        <div className="flex items-center justify-between pt-4 border-t border-gray-100">
          <button
            onClick={() => setIsExpanded(!isExpanded)}
            className="flex items-center space-x-1 text-green-600 hover:text-green-700 font-medium"
          >
            <DocumentTextIcon className="w-4 h-4" />
            <span>{isExpanded ? 'Read Less' : 'Read More'}</span>
          </button>
          
          <div className="flex items-center space-x-3">
            <button
              onClick={() => setIsLiked(!isLiked)}
              className={`flex items-center space-x-1 px-3 py-2 rounded-lg transition-colors ${
                isLiked ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {isLiked ? (
                <HeartSolidIcon className="w-4 h-4" />
              ) : (
                <HeartIcon className="w-4 h-4" />
              )}
              <span>{(article.likes + (isLiked ? 1 : 0)).toLocaleString()}</span>
            </button>
            
            <button className="flex items-center space-x-1 px-3 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition-colors">
              <ChatBubbleLeftIcon className="w-4 h-4" />
              <span>Comment</span>
            </button>
          </div>
        </div>
      </div>
    </motion.article>
  );
};

// Rich Media Dashboard
export const RichMediaDashboard: React.FC = () => {
  const sampleImages: GalleryImage[] = [
    {
      id: '1',
      url: '/api/placeholder/400/300',
      title: 'Cannabis Cultivation Lab',
      description: 'Modern hydroponic growing facility',
      tags: ['cultivation', 'technology', 'indoor'],
      author: 'John Smith',
      date: '2024-01-15'
    },
    {
      id: '2',
      url: '/api/placeholder/400/300',
      title: 'NYC Dispensary Opening',
      description: 'Grand opening ceremony in Manhattan',
      tags: ['dispensary', 'nyc', 'opening'],
      author: 'Sarah Johnson',
      date: '2024-01-20'
    },
    {
      id: '3',
      url: '/api/placeholder/400/300',
      title: 'Cannabis Research Laboratory',
      description: 'Scientists analyzing cannabis compounds',
      tags: ['research', 'science', 'lab'],
      author: 'Dr. Mike Brown',
      date: '2024-01-22'
    }
  ];

  const sampleArticle: NewsArticle = {
    id: '1',
    title: 'NYC Cannabis Industry Shows Strong Growth Despite Regulatory Challenges',
    excerpt: 'Despite ongoing regulatory hurdles, New York City\'s cannabis industry continues to expand with new dispensaries opening and increased consumer adoption.',
    content: '<p>The cannabis industry in New York City has demonstrated remarkable resilience and growth throughout 2024, despite facing numerous regulatory challenges and enforcement actions...</p>',
    author: 'Cannabis Reporter',
    publishedAt: '2024-01-25',
    featuredImage: '/api/placeholder/600/300',
    category: 'Industry News',
    tags: ['nyc', 'cannabis', 'regulation', 'growth'],
    readTime: 5,
    views: 1250,
    likes: 89
  };

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-8">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="mb-8"
      >
        <h1 className="text-3xl font-bold text-gray-900 mb-4">
          Rich Media Center
        </h1>
        <p className="text-gray-600">
          Interactive media gallery, videos, and news content for the cannabis community
        </p>
      </motion.div>

      {/* Image Gallery */}
      <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ delay: 0.1 }}
      >
        <InteractiveImageGallery images={sampleImages} columns={3} />
      </motion.div>

      {/* Video Player */}
      <motion.div
        initial={{ opacity: 0, x: -20 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ delay: 0.2 }}
      >
        <RichVideoPlayer
          src="/api/placeholder/video"
          poster="/api/placeholder/800/450"
          title="Cannabis Cultivation Masterclass"
          description="Learn advanced techniques for growing premium cannabis from industry experts"
          views={5420}
          likes={234}
          author="Green Thumb Academy"
          publishedAt="2024-01-20"
        />
      </motion.div>

      {/* News Article */}
      <motion.div
        initial={{ opacity: 0, x: 20 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ delay: 0.3 }}
      >
        <NewsArticleCard article={sampleArticle} />
      </motion.div>
    </div>
  );
};