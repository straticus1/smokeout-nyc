import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Menu, Transition } from '@headlessui/react';
import { 
  ChevronDownIcon, 
  UserIcon, 
  CogIcon, 
  ArrowRightOnRectangleIcon,
  ShoppingCartIcon,
  MagnifyingGlassIcon,
  StarIcon,
  NewspaperIcon,
  CheckBadgeIcon,
  SparklesIcon,
  UserGroupIcon,
  BellIcon
} from '@heroicons/react/24/outline';
import BuyCreditsModal from './BuyCreditsModal';
import SmartNotificationsCenter from './SmartNotificationsCenter';

const Header: React.FC = () => {
  const { user, logout, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const [showBuyCredits, setShowBuyCredits] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  return (
    <>
      <header className="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            {/* Logo */}
<Link to="/" className="flex items-center space-x-2">
              <div className="text-2xl">🏪</div>
              <h1 className="text-xl font-bold text-indigo-600">
                SmokeoutNYC
              </h1>
            </Link>

            {/* Navigation */}
            <nav className="hidden md:flex items-center space-x-8">
              <Link 
                to="/map" 
                className="flex items-center space-x-1 text-gray-600 hover:text-gray-900 transition-colors"
              >
                <MagnifyingGlassIcon className="w-4 h-4" />
                <span>Map</span>
              </Link>
              
              <Link 
                to="/add-store" 
                className="flex items-center space-x-1 text-gray-600 hover:text-gray-900 transition-colors"
              >
                <StarIcon className="w-4 h-4" />
                <span>Add Store</span>
              </Link>
              
              <Link 
                to="/news" 
                className="flex items-center space-x-1 text-gray-600 hover:text-gray-900 transition-colors"
              >
                <NewspaperIcon className="w-4 h-4" />
                <span>News</span>
              </Link>
            </nav>

            {/* User Menu */}
            <div className="flex items-center space-x-4">
              {isAuthenticated ? (
                <>
                  {/* Notifications Button */}
                  <button
                    onClick={() => setShowNotifications(true)}
                    className="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors relative"
                    title="Smart Notifications"
                  >
                    <BellIcon className="w-6 h-6" />
                    {/* Notification Badge */}
                    <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                      3
                    </span>
                  </button>

                  {/* Credits Display */}
                  <button
                    onClick={() => setShowBuyCredits(true)}
                    className="flex items-center space-x-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-2 rounded-lg font-semibold text-sm hover:from-amber-600 hover:to-orange-600 transition-all duration-200 transform hover:scale-105"
                  >
                    <div className="text-lg">🪙</div>
                    <span>{user?.credits || 0} Credits</span>
                  </button>

                  {/* User Dropdown */}
                  <Menu as="div" className="relative">
                    <Menu.Button className="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                      <div className="w-8 h-8 bg-indigo-600 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                        {user?.username?.charAt(0).toUpperCase()}
                      </div>
                      <ChevronDownIcon className="w-4 h-4 text-gray-500" />
                    </Menu.Button>

                    <Transition
                      enter="transition duration-100 ease-out"
                      enterFrom="transform scale-95 opacity-0"
                      enterTo="transform scale-100 opacity-100"
                      leave="transition duration-75 ease-out"
                      leaveFrom="transform scale-100 opacity-100"
                      leaveTo="transform scale-95 opacity-0"
                    >
                      <Menu.Items className="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 focus:outline-none">
                        <div className="px-4 py-2 border-b border-gray-100">
                          <p className="text-sm font-medium text-gray-900">
                            {user?.username}
                          </p>
                          <p className="text-xs text-gray-500">
                            {user?.email}
                          </p>
                        </div>

                        <Menu.Item>
                          {({ active }) => (
                            <Link
                              to="/profile"
                              className={`${
                                active ? 'bg-gray-50' : ''
                              } flex items-center space-x-2 px-4 py-2 text-sm text-gray-700`}
                            >
                              <UserIcon className="w-4 h-4" />
                              <span>My Profile</span>
                            </Link>
                          )}
                        </Menu.Item>

                        <Menu.Item>
                          {({ active }) => (
                            <button
                              onClick={() => setShowBuyCredits(true)}
                              className={`${
                                active ? 'bg-gray-50' : ''
                              } flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 w-full text-left`}
                            >
                              <ShoppingCartIcon className="w-4 h-4" />
                              <span>Buy Credits</span>
                            </button>
                          )}
                        </Menu.Item>

                        <div className="border-t border-gray-100 mt-1">
                          <div className="px-4 py-2">
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Phase 1 Features</p>
                          </div>
                          <Menu.Item>
                            {({ active }) => (
                              <Link
                                to="/ai-risk-assistant"
                                className={`${
                                  active ? 'bg-gray-50' : ''
                                } flex items-center space-x-2 px-4 py-2 text-sm text-gray-700`}
                              >
                                <SparklesIcon className="w-4 h-4 text-purple-600" />
                                <span>AI Risk Assistant</span>
                              </Link>
                            )}
                          </Menu.Item>

                          <Menu.Item>
                            {({ active }) => (
                              <Link
                                to="/multiplayer-hub"
                                className={`${
                                  active ? 'bg-gray-50' : ''
                                } flex items-center space-x-2 px-4 py-2 text-sm text-gray-700`}
                              >
                                <UserGroupIcon className="w-4 h-4 text-indigo-600" />
                                <span>Multiplayer Hub</span>
                              </Link>
                            )}
                          </Menu.Item>

                          <Menu.Item>
                            {({ active }) => (
                              <Link
                                to="/notifications"
                                className={`${
                                  active ? 'bg-gray-50' : ''
                                } flex items-center space-x-2 px-4 py-2 text-sm text-gray-700`}
                              >
                                <BellIcon className="w-4 h-4 text-blue-600" />
                                <span>Smart Notifications</span>
                              </Link>
                            )}
                          </Menu.Item>
                        </div>

                        <Menu.Item>
                          {({ active }) => (
                            <Link
                              to="/settings"
                              className={`${
                                active ? 'bg-gray-50' : ''
                              } flex items-center space-x-2 px-4 py-2 text-sm text-gray-700`}
                            >
                              <CogIcon className="w-4 h-4" />
                              <span>Settings</span>
                            </Link>
                          )}
                        </Menu.Item>

                        <div className="border-t border-gray-100 mt-1">
                          <Menu.Item>
                            {({ active }) => (
                              <button
                                onClick={handleLogout}
                                className={`${
                                  active ? 'bg-gray-50' : ''
                                } flex items-center space-x-2 px-4 py-2 text-sm text-gray-700 w-full text-left`}
                              >
                                <ArrowRightOnRectangleIcon className="w-4 h-4" />
                                <span>Logout</span>
                              </button>
                            )}
                          </Menu.Item>
                        </div>
                      </Menu.Items>
                    </Transition>
                  </Menu>
                </>
              ) : (
                <div className="flex items-center space-x-3">
                  <Link
                    to="/login"
                    className="text-gray-600 hover:text-gray-900 font-medium transition-colors"
                  >
                    Login
                  </Link>
                  <Link
                    to="/register"
                    className="bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-indigo-700 transition-colors"
                  >
                    Sign Up
                  </Link>
                </div>
              )}
            </div>
          </div>
        </div>
      </header>

      {/* Buy Credits Modal */}
      <BuyCreditsModal 
        isOpen={showBuyCredits}
        onClose={() => setShowBuyCredits(false)}
      />
      
      {/* Smart Notifications Center */}
      <SmartNotificationsCenter 
        isOpen={showNotifications}
        onClose={() => setShowNotifications(false)}
      />
    </>
  );
};

export default Header;
