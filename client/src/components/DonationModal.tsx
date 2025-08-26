import React, { useState, useEffect } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { XMarkIcon, HeartIcon, CreditCardIcon } from '@heroicons/react/24/outline';
import { useAuth } from '../contexts/AuthContext';
import toast from 'react-hot-toast';
import axios from 'axios';

interface DonationModalProps {
  isOpen: boolean;
  onClose: () => void;
  politician: {
    id: number;
    name: string;
    position: string;
    slug: string;
  };
}

interface DonationSettings {
  donations_enabled: boolean;
  min_donation_amount: number;
  max_donation_amount: number;
  processing_fee_percent: number;
  campaign_contact_email?: string;
  donation_instructions?: string;
}

const DonationModal: React.FC<DonationModalProps> = ({ isOpen, onClose, politician }) => {
  const { user, isAuthenticated } = useAuth();
  const [amount, setAmount] = useState<string>('');
  const [paymentMethod, setPaymentMethod] = useState<'credit_card' | 'paypal'>('credit_card');
  const [donorName, setDonorName] = useState(user?.username || '');
  const [donorEmail, setDonorEmail] = useState(user?.email || '');
  const [donorAddress, setDonorAddress] = useState('');
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [settings, setSettings] = useState<DonationSettings | null>(null);
  const [loading, setLoading] = useState(true);

  const quickAmounts = [25, 50, 100, 250, 500, 1000];

  useEffect(() => {
    if (isOpen && politician.id) {
      fetchDonationSettings();
    }
  }, [isOpen, politician.id]);

  const fetchDonationSettings = async () => {
    try {
      const response = await axios.get(`/api/donations/${politician.id}/settings`);
      setSettings(response.data.settings);
    } catch (error) {
      console.error('Failed to fetch donation settings:', error);
      toast.error('Failed to load donation information');
    } finally {
      setLoading(false);
    }
  };

  const calculateFees = (donationAmount: number) => {
    if (!settings) return { processingFee: 0, netAmount: donationAmount };
    
    const processingFee = donationAmount * (settings.processing_fee_percent / 100);
    const netAmount = donationAmount - processingFee;
    
    return { processingFee, netAmount };
  };

  const handleDonate = async () => {
    if (!isAuthenticated) {
      toast.error('Please log in to make a donation');
      return;
    }

    if (!amount || parseFloat(amount) <= 0) {
      toast.error('Please enter a valid donation amount');
      return;
    }

    const donationAmount = parseFloat(amount);
    
    if (settings) {
      if (donationAmount < settings.min_donation_amount) {
        toast.error(`Minimum donation amount is $${settings.min_donation_amount}`);
        return;
      }
      
      if (donationAmount > settings.max_donation_amount) {
        toast.error(`Maximum donation amount is $${settings.max_donation_amount}`);
        return;
      }
    }

    setProcessing(true);

    try {
      const donationData = {
        politician_id: politician.id,
        amount: donationAmount,
        payment_method: paymentMethod,
        donor_name: isAnonymous ? null : donorName,
        donor_email: donorEmail,
        donor_address: donorAddress,
        is_anonymous: isAnonymous,
      };

      const response = await axios.post('/api/donations/donate', donationData);

      if (response.data.success) {
        toast.success(`Successfully donated $${donationAmount} to ${politician.name}!`);
        onClose();
        resetForm();
      } else {
        toast.error(response.data.error || 'Donation failed');
      }
    } catch (error: any) {
      const message = error.response?.data?.error || 'Donation failed. Please try again.';
      toast.error(message);
    } finally {
      setProcessing(false);
    }
  };

  const resetForm = () => {
    setAmount('');
    setDonorName(user?.username || '');
    setDonorEmail(user?.email || '');
    setDonorAddress('');
    setIsAnonymous(false);
    setPaymentMethod('credit_card');
  };

  const fees = amount ? calculateFees(parseFloat(amount) || 0) : { processingFee: 0, netAmount: 0 };

  if (loading) {
    return (
      <Transition appear show={isOpen} as={React.Fragment}>
        <Dialog as="div" className="relative z-50" onClose={onClose}>
          <div className="fixed inset-0 bg-black bg-opacity-25 backdrop-blur-sm" />
          <div className="fixed inset-0 overflow-y-auto">
            <div className="flex min-h-full items-center justify-center p-4">
              <div className="bg-white rounded-2xl p-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
                <p className="text-center mt-4 text-gray-600">Loading donation information...</p>
              </div>
            </div>
          </div>
        </Dialog>
      </Transition>
    );
  }

  if (!settings?.donations_enabled) {
    return (
      <Transition appear show={isOpen} as={React.Fragment}>
        <Dialog as="div" className="relative z-50" onClose={onClose}>
          <div className="fixed inset-0 bg-black bg-opacity-25 backdrop-blur-sm" />
          <div className="fixed inset-0 overflow-y-auto">
            <div className="flex min-h-full items-center justify-center p-4">
              <div className="bg-white rounded-2xl p-8 text-center">
                <HeartIcon className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-900 mb-2">
                  Donations Not Available
                </h3>
                <p className="text-gray-600 mb-6">
                  {politician.name} is not currently accepting donations through this platform.
                </p>
                <button
                  onClick={onClose}
                  className="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </Dialog>
      </Transition>
    );
  }

  return (
    <Transition appear show={isOpen} as={React.Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={React.Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black bg-opacity-25 backdrop-blur-sm" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={React.Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white p-6 text-left align-middle shadow-xl transition-all">
                <div className="flex justify-between items-center mb-6">
                  <Dialog.Title className="text-2xl font-bold text-gray-900 flex items-center space-x-2">
                    <HeartIcon className="w-8 h-8 text-red-500" />
                    <span>Donate to {politician.name}</span>
                  </Dialog.Title>
                  <button
                    onClick={onClose}
                    className="text-gray-400 hover:text-gray-600 transition-colors"
                  >
                    <XMarkIcon className="w-6 h-6" />
                  </button>
                </div>

                <div className="mb-6">
                  <p className="text-gray-600">
                    Support <strong>{politician.name}</strong> ({politician.position}) with a secure donation.
                    All donations are processed securely and forwarded to their official campaign.
                  </p>
                  {settings.donation_instructions && (
                    <div className="mt-4 p-4 bg-blue-50 rounded-lg">
                      <p className="text-blue-800 text-sm">{settings.donation_instructions}</p>
                    </div>
                  )}
                </div>

                {/* Quick Amount Buttons */}
                <div className="mb-6">
                  <label className="block text-sm font-medium text-gray-700 mb-3">
                    Choose Amount
                  </label>
                  <div className="grid grid-cols-3 gap-3 mb-4">
                    {quickAmounts.map((quickAmount) => (
                      <button
                        key={quickAmount}
                        onClick={() => setAmount(quickAmount.toString())}
                        className={`p-3 border-2 rounded-lg font-semibold transition-all ${
                          amount === quickAmount.toString()
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                            : 'border-gray-200 hover:border-gray-300'
                        }`}
                      >
                        ${quickAmount}
                      </button>
                    ))}
                  </div>
                  
                  <div className="relative">
                    <span className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-semibold">
                      $
                    </span>
                    <input
                      type="number"
                      value={amount}
                      onChange={(e) => setAmount(e.target.value)}
                      placeholder="Enter custom amount"
                      min={settings.min_donation_amount}
                      max={settings.max_donation_amount}
                      step="0.01"
                      className="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    />
                  </div>
                  
                  <p className="text-xs text-gray-500 mt-2">
                    Amount must be between ${settings.min_donation_amount} and ${settings.max_donation_amount}
                  </p>
                </div>

                {/* Fee Breakdown */}
                {amount && parseFloat(amount) > 0 && (
                  <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h4 className="font-semibold text-gray-900 mb-2">Donation Breakdown</h4>
                    <div className="space-y-1 text-sm">
                      <div className="flex justify-between">
                        <span>Your donation:</span>
                        <span>${parseFloat(amount).toFixed(2)}</span>
                      </div>
                      <div className="flex justify-between text-gray-600">
                        <span>Processing fee ({settings.processing_fee_percent}%):</span>
                        <span>-${fees.processingFee.toFixed(2)}</span>
                      </div>
                      <div className="flex justify-between font-semibold border-t pt-1">
                        <span>Amount to campaign:</span>
                        <span>${fees.netAmount.toFixed(2)}</span>
                      </div>
                    </div>
                  </div>
                )}

                {/* Donor Information */}
                <div className="mb-6 space-y-4">
                  <h4 className="font-semibold text-gray-900">Donor Information</h4>
                  
                  <div className="flex items-center space-x-3">
                    <input
                      type="checkbox"
                      id="anonymous"
                      checked={isAnonymous}
                      onChange={(e) => setIsAnonymous(e.target.checked)}
                      className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label htmlFor="anonymous" className="text-sm text-gray-700">
                      Make this donation anonymous
                    </label>
                  </div>

                  {!isAnonymous && (
                    <>
                      <input
                        type="text"
                        value={donorName}
                        onChange={(e) => setDonorName(e.target.value)}
                        placeholder="Full Name"
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        required
                      />
                    </>
                  )}

                  <input
                    type="email"
                    value={donorEmail}
                    onChange={(e) => setDonorEmail(e.target.value)}
                    placeholder="Email Address"
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    required
                  />

                  <textarea
                    value={donorAddress}
                    onChange={(e) => setDonorAddress(e.target.value)}
                    placeholder="Address (required for campaign finance reporting)"
                    rows={3}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  />
                </div>

                {/* Payment Method */}
                <div className="mb-6">
                  <h4 className="font-semibold text-gray-900 mb-3">Payment Method</h4>
                  <div className="grid grid-cols-2 gap-4">
                    <button
                      onClick={() => setPaymentMethod('credit_card')}
                      className={`flex items-center justify-center space-x-2 p-4 border-2 rounded-lg transition-all ${
                        paymentMethod === 'credit_card'
                          ? 'border-indigo-500 bg-indigo-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <CreditCardIcon className="w-5 h-5" />
                      <span>Credit Card</span>
                    </button>
                    
                    <button
                      onClick={() => setPaymentMethod('paypal')}
                      className={`flex items-center justify-center space-x-2 p-4 border-2 rounded-lg transition-all ${
                        paymentMethod === 'paypal'
                          ? 'border-indigo-500 bg-indigo-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <div className="text-blue-600 font-bold">PayPal</div>
                    </button>
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="flex justify-end space-x-4">
                  <button
                    onClick={onClose}
                    className="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleDonate}
                    disabled={!amount || parseFloat(amount) <= 0 || processing || !isAuthenticated}
                    className="px-6 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                  >
                    {processing ? (
                      <div className="flex items-center space-x-2">
                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                        <span>Processing...</span>
                      </div>
                    ) : (
                      `Donate $${amount || '0'}`
                    )}
                  </button>
                </div>

                {/* Legal Notice */}
                <div className="mt-6 text-xs text-gray-500 text-center">
                  <p>
                    🔒 Your donation is secure and will be forwarded to the official campaign.
                    By donating, you agree to our terms and campaign finance regulations.
                  </p>
                  <p className="mt-1">
                    Political donations are not tax-deductible for federal income tax purposes.
                  </p>
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
};

export default DonationModal;
