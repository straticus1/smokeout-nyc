import React, { useState } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { XMarkIcon, CreditCardIcon } from '@heroicons/react/24/outline';
import { useAuth } from '../contexts/AuthContext';
import toast from 'react-hot-toast';

interface BuyCreditsModalProps {
  isOpen: boolean;
  onClose: () => void;
}

interface CreditPackage {
  credits: number;
  price: number;
  popular?: boolean;
  bestValue?: boolean;
}

const creditPackages: CreditPackage[] = [
  { credits: 50, price: 5.00 },
  { credits: 100, price: 10.00, popular: true },
  { credits: 250, price: 25.00 },
  { credits: 500, price: 50.00, bestValue: true },
];

const BuyCreditsModal: React.FC<BuyCreditsModalProps> = ({ isOpen, onClose }) => {
  const { updateCredits } = useAuth();
  const [selectedPackage, setSelectedPackage] = useState<CreditPackage | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<'stripe' | 'paypal'>('stripe');
  const [processing, setProcessing] = useState(false);

  const handlePurchase = async () => {
    if (!selectedPackage) {
      toast.error('Please select a credit package');
      return;
    }

    setProcessing(true);

    try {
      // Simulate payment processing
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      // Update user credits
      updateCredits(selectedPackage.credits);
      
      toast.success(`Successfully purchased ${selectedPackage.credits} credits!`);
      onClose();
      setSelectedPackage(null);
    } catch (error) {
      toast.error('Payment failed. Please try again.');
    } finally {
      setProcessing(false);
    }
  };

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
                    <div className="text-3xl">🪙</div>
                    <span>Buy Credits</span>
                  </Dialog.Title>
                  <button
                    onClick={onClose}
                    className="text-gray-400 hover:text-gray-600 transition-colors"
                  >
                    <XMarkIcon className="w-6 h-6" />
                  </button>
                </div>

                {/* Credit Packages */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                  {creditPackages.map((pkg) => (
                    <div
                      key={pkg.credits}
                      onClick={() => setSelectedPackage(pkg)}
                      className={`relative cursor-pointer border-2 rounded-xl p-4 text-center transition-all duration-200 hover:shadow-md ${
                        selectedPackage?.credits === pkg.credits
                          ? 'border-indigo-500 bg-indigo-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      {pkg.popular && (
                        <div className="absolute -top-2 left-1/2 transform -translate-x-1/2">
                          <span className="bg-amber-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            Most Popular
                          </span>
                        </div>
                      )}
                      
                      {pkg.bestValue && (
                        <div className="absolute -top-2 left-1/2 transform -translate-x-1/2">
                          <span className="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            Best Value
                          </span>
                        </div>
                      )}

                      <div className="text-2xl font-bold text-gray-900 mb-1">
                        {pkg.credits}
                      </div>
                      <div className="text-sm text-gray-500 mb-2">Credits</div>
                      <div className="text-xl font-bold text-indigo-600">
                        ${pkg.price.toFixed(2)}
                      </div>
                      <div className="text-xs text-gray-400 mt-1">
                        ${(pkg.price / pkg.credits).toFixed(3)} per credit
                      </div>
                    </div>
                  ))}
                </div>

                {/* Payment Method */}
                <div className="mb-6">
                  <h3 className="text-lg font-semibold text-gray-900 mb-4">
                    Payment Method
                  </h3>
                  <div className="grid grid-cols-2 gap-4">
                    <button
                      onClick={() => setPaymentMethod('stripe')}
                      className={`flex items-center justify-center space-x-2 p-4 border-2 rounded-xl transition-all duration-200 ${
                        paymentMethod === 'stripe'
                          ? 'border-indigo-500 bg-indigo-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <CreditCardIcon className="w-5 h-5" />
                      <span className="font-medium">Credit Card</span>
                    </button>
                    
                    <button
                      onClick={() => setPaymentMethod('paypal')}
                      className={`flex items-center justify-center space-x-2 p-4 border-2 rounded-xl transition-all duration-200 ${
                        paymentMethod === 'paypal'
                          ? 'border-indigo-500 bg-indigo-50'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <div className="text-blue-600 font-bold text-lg">PayPal</div>
                    </button>
                  </div>
                </div>

                {/* Purchase Summary */}
                {selectedPackage && (
                  <div className="bg-gray-50 rounded-xl p-4 mb-6">
                    <h4 className="font-semibold text-gray-900 mb-2">
                      Purchase Summary
                    </h4>
                    <div className="flex justify-between items-center text-sm text-gray-600 mb-1">
                      <span>{selectedPackage.credits} Credits</span>
                      <span>${selectedPackage.price.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between items-center text-sm text-gray-600 mb-1">
                      <span>Processing Fee</span>
                      <span>$0.00</span>
                    </div>
                    <div className="border-t border-gray-200 pt-2 mt-2">
                      <div className="flex justify-between items-center font-semibold text-gray-900">
                        <span>Total</span>
                        <span>${selectedPackage.price.toFixed(2)}</span>
                      </div>
                    </div>
                  </div>
                )}

                {/* Action Buttons */}
                <div className="flex justify-end space-x-4">
                  <button
                    onClick={onClose}
                    className="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handlePurchase}
                    disabled={!selectedPackage || processing}
                    className="px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                  >
                    {processing ? (
                      <div className="flex items-center space-x-2">
                        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                        <span>Processing...</span>
                      </div>
                    ) : (
                      'Purchase Credits'
                    )}
                  </button>
                </div>

                {/* Security Notice */}
                <div className="mt-4 text-xs text-gray-500 text-center">
                  🔒 Your payment information is secure and encrypted.
                  Credits are non-refundable.
                </div>
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
};

export default BuyCreditsModal;
