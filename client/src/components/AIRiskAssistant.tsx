import React, { useState, useEffect, useRef } from 'react';
import { 
  ChatBubbleLeftIcon, 
  XMarkIcon, 
  PaperAirplaneIcon,
  LightBulbIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon
} from '@heroicons/react/24/outline';
import { motion, AnimatePresence } from 'framer-motion';
import toast from 'react-hot-toast';
import axios from 'axios';

interface AIRiskAssistantProps {
  isOpen: boolean;
  onClose: () => void;
  initialContext?: {
    location?: string;
    riskData?: any;
  };
}

interface Message {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  timestamp: Date;
  suggestions?: SuggestedAction[];
}

interface SuggestedAction {
  action: string;
  label: string;
  description: string;
}

interface RiskExplanation {
  summary: string;
  key_message: string;
  action_needed: boolean;
  overall_explanation?: string;
  risk_breakdown?: Record<string, any>;
  what_this_means?: string;
  next_steps?: string[];
  confidence_explanation?: string;
}

const AIRiskAssistant: React.FC<AIRiskAssistantProps> = ({ 
  isOpen, 
  onClose, 
  initialContext 
}) => {
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputMessage, setInputMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<'chat' | 'analysis' | 'insights'>('chat');
  const [riskAnalysis, setRiskAnalysis] = useState<RiskExplanation | null>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (isOpen && messages.length === 0) {
      // Initialize conversation with welcome message
      const welcomeMessage: Message = {
        id: Date.now().toString(),
        role: 'assistant',
        content: `👋 Hi! I'm your AI Risk Assistant. I can help you understand cannabis business risks, analyze locations, and provide personalized recommendations. 

${initialContext?.location ? `I see you're interested in analyzing ${initialContext.location}. ` : ''}What would you like to know about cannabis business risks?`,
        timestamp: new Date(),
        suggestions: [
          {
            action: 'analyze_location',
            label: 'Analyze a location',
            description: 'Get detailed risk assessment for a specific address'
          },
          {
            action: 'explain_risks',
            label: 'Explain risk factors',
            description: 'Learn about different types of business risks'
          },
          {
            action: 'get_recommendations',
            label: 'Get recommendations',
            description: 'Receive personalized business advice'
          }
        ]
      };
      setMessages([welcomeMessage]);

      // If we have initial risk data, process it
      if (initialContext?.riskData) {
        processRiskData(initialContext.riskData);
      }
    }
  }, [isOpen, initialContext]);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const processRiskData = async (riskData: any) => {
    try {
      const response = await axios.post('/api/ai-risk-assistant/explain', {
        risk_assessment: riskData,
        type: 'detailed'
      });
      
      setRiskAnalysis(response.data.explanation);
    } catch (error) {
      console.error('Failed to process risk data:', error);
    }
  };

  const sendMessage = async () => {
    if (!inputMessage.trim() || isLoading) return;

    const userMessage: Message = {
      id: Date.now().toString(),
      role: 'user',
      content: inputMessage,
      timestamp: new Date()
    };

    setMessages(prev => [...prev, userMessage]);
    setInputMessage('');
    setIsLoading(true);

    try {
      const response = await axios.post('/api/ai-risk-assistant/conversation', {
        message: inputMessage,
        conversation_id: conversationId,
        context: initialContext
      });

      if (!conversationId) {
        setConversationId(response.data.conversation_id);
      }

      const assistantMessage: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: response.data.response.content,
        timestamp: new Date(),
        suggestions: response.data.suggested_actions
      };

      setMessages(prev => [...prev, assistantMessage]);
    } catch (error) {
      console.error('Failed to send message:', error);
      toast.error('Failed to get AI response. Please try again.');
      
      const errorMessage: Message = {
        id: (Date.now() + 1).toString(),
        role: 'assistant',
        content: 'I apologize, but I encountered an error processing your request. Please try again or contact support if the problem persists.',
        timestamp: new Date()
      };
      
      setMessages(prev => [...prev, errorMessage]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleSuggestedAction = (action: SuggestedAction) => {
    setInputMessage(action.description);
    // Auto-send the suggested action
    setTimeout(() => {
      sendMessage();
    }, 100);
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const getRiskLevelColor = (level: string) => {
    switch (level?.toLowerCase()) {
      case 'low':
        return 'text-green-600 bg-green-100';
      case 'moderate':
      case 'medium':
        return 'text-yellow-600 bg-yellow-100';
      case 'high':
        return 'text-orange-600 bg-orange-100';
      case 'critical':
        return 'text-red-600 bg-red-100';
      default:
        return 'text-gray-600 bg-gray-100';
    }
  };

  const getRiskIcon = (level: string) => {
    switch (level?.toLowerCase()) {
      case 'critical':
      case 'high':
        return <ExclamationTriangleIcon className="w-5 h-5" />;
      default:
        return <InformationCircleIcon className="w-5 h-5" />;
    }
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black bg-opacity-25 backdrop-blur-sm z-40"
            onClick={onClose}
          />
          
          {/* Assistant Panel */}
          <motion.div
            initial={{ opacity: 0, x: '100%' }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: '100%' }}
            transition={{ type: 'spring', damping: 20, stiffness: 300 }}
            className="fixed right-0 top-0 h-full w-full max-w-2xl bg-white shadow-2xl z-50 flex flex-col"
          >
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-gray-200 bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
              <div className="flex items-center space-x-3">
                <div className="p-2 bg-white bg-opacity-20 rounded-full">
                  <ChatBubbleLeftIcon className="w-6 h-6" />
                </div>
                <div>
                  <h2 className="text-lg font-semibold">AI Risk Assistant</h2>
                  <p className="text-sm opacity-90">
                    Intelligent cannabis business guidance
                  </p>
                </div>
              </div>
              <button
                onClick={onClose}
                className="p-2 hover:bg-white hover:bg-opacity-20 rounded-full transition-colors"
              >
                <XMarkIcon className="w-6 h-6" />
              </button>
            </div>

            {/* Tabs */}
            <div className="flex border-b border-gray-200">
              {[
                { key: 'chat', label: 'Chat', icon: ChatBubbleLeftIcon },
                { key: 'analysis', label: 'Analysis', icon: InformationCircleIcon },
                { key: 'insights', label: 'Insights', icon: LightBulbIcon }
              ].map(tab => (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key as any)}
                  className={`flex-1 flex items-center justify-center space-x-2 py-3 px-4 text-sm font-medium transition-colors ${
                    activeTab === tab.key
                      ? 'border-b-2 border-indigo-600 text-indigo-600 bg-indigo-50'
                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                  }`}
                >
                  <tab.icon className="w-4 h-4" />
                  <span>{tab.label}</span>
                </button>
              ))}
            </div>

            {/* Content */}
            <div className="flex-1 overflow-hidden">
              {activeTab === 'chat' && (
                <div className="h-full flex flex-col">
                  {/* Messages */}
                  <div className="flex-1 overflow-y-auto p-4 space-y-4">
                    {messages.map((message) => (
                      <div
                        key={message.id}
                        className={`flex ${
                          message.role === 'user' ? 'justify-end' : 'justify-start'
                        }`}
                      >
                        <div
                          className={`max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${
                            message.role === 'user'
                              ? 'bg-indigo-600 text-white'
                              : 'bg-gray-100 text-gray-900'
                          }`}
                        >
                          <div className="whitespace-pre-wrap">
                            {message.content}
                          </div>
                          
                          {message.suggestions && message.suggestions.length > 0 && (
                            <div className="mt-3 space-y-2">
                              <div className="text-xs opacity-75 mb-2">
                                Suggested actions:
                              </div>
                              {message.suggestions.map((suggestion, index) => (
                                <button
                                  key={index}
                                  onClick={() => handleSuggestedAction(suggestion)}
                                  className="w-full text-left px-3 py-2 text-sm bg-white bg-opacity-90 hover:bg-opacity-100 rounded border border-gray-200 hover:border-indigo-300 transition-colors"
                                >
                                  <div className="font-medium text-gray-900">
                                    {suggestion.label}
                                  </div>
                                  <div className="text-xs text-gray-600">
                                    {suggestion.description}
                                  </div>
                                </button>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>
                    ))}
                    
                    {isLoading && (
                      <div className="flex justify-start">
                        <div className="bg-gray-100 rounded-lg px-4 py-2">
                          <div className="flex items-center space-x-2">
                            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-indigo-600"></div>
                            <span className="text-sm text-gray-600">
                              AI is thinking...
                            </span>
                          </div>
                        </div>
                      </div>
                    )}
                    
                    <div ref={messagesEndRef} />
                  </div>

                  {/* Input */}
                  <div className="border-t border-gray-200 p-4">
                    <div className="flex space-x-2">
                      <input
                        type="text"
                        value={inputMessage}
                        onChange={(e) => setInputMessage(e.target.value)}
                        onKeyPress={handleKeyPress}
                        placeholder="Ask about cannabis business risks..."
                        className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        disabled={isLoading}
                      />
                      <button
                        onClick={sendMessage}
                        disabled={!inputMessage.trim() || isLoading}
                        className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      >
                        <PaperAirplaneIcon className="w-5 h-5" />
                      </button>
                    </div>
                  </div>
                </div>
              )}

              {activeTab === 'analysis' && (
                <div className="p-6 overflow-y-auto h-full">
                  {riskAnalysis ? (
                    <div className="space-y-6">
                      {/* Summary */}
                      <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
                        <div className="flex items-start space-x-3">
                          {getRiskIcon(riskAnalysis.key_message)}
                          <div>
                            <h3 className="font-semibold text-gray-900 mb-2">
                              Risk Summary
                            </h3>
                            <p className="text-gray-700">
                              {riskAnalysis.summary}
                            </p>
                            <div className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-3 ${
                              getRiskLevelColor(riskAnalysis.key_message)
                            }`}>
                              {riskAnalysis.key_message}
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* What This Means */}
                      {riskAnalysis.what_this_means && (
                        <div className="bg-gray-50 rounded-lg p-4">
                          <h4 className="font-semibold text-gray-900 mb-2">
                            What This Means
                          </h4>
                          <p className="text-gray-700">
                            {riskAnalysis.what_this_means}
                          </p>
                        </div>
                      )}

                      {/* Next Steps */}
                      {riskAnalysis.next_steps && riskAnalysis.next_steps.length > 0 && (
                        <div className="bg-green-50 rounded-lg p-4 border border-green-200">
                          <h4 className="font-semibold text-gray-900 mb-3">
                            Recommended Next Steps
                          </h4>
                          <ul className="space-y-2">
                            {riskAnalysis.next_steps.map((step, index) => (
                              <li key={index} className="flex items-start space-x-2">
                                <span className="flex-shrink-0 w-5 h-5 bg-green-500 text-white rounded-full text-xs flex items-center justify-center font-medium">
                                  {index + 1}
                                </span>
                                <span className="text-gray-700">{step}</span>
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}

                      {/* Risk Breakdown */}
                      {riskAnalysis.risk_breakdown && (
                        <div className="space-y-4">
                          <h4 className="font-semibold text-gray-900">
                            Detailed Risk Factors
                          </h4>
                          {Object.entries(riskAnalysis.risk_breakdown).map(([factor, details]) => (
                            <div key={factor} className="border border-gray-200 rounded-lg p-4">
                              <div className="flex items-center justify-between mb-2">
                                <h5 className="font-medium text-gray-900">
                                  {(details as any).name}
                                </h5>
                                <span className={`px-2 py-1 rounded text-xs font-medium ${
                                  getRiskLevelColor((details as any).impact_level)
                                }`}>
                                  {(details as any).impact_level} risk
                                </span>
                              </div>
                              <p className="text-sm text-gray-600">
                                {(details as any).explanation}
                              </p>
                            </div>
                          ))}
                        </div>
                      )}

                      {/* Confidence */}
                      {riskAnalysis.confidence_explanation && (
                        <div className="bg-blue-50 rounded-lg p-4 border border-blue-200">
                          <h4 className="font-semibold text-gray-900 mb-2">
                            Analysis Confidence
                          </h4>
                          <p className="text-sm text-gray-700">
                            {riskAnalysis.confidence_explanation}
                          </p>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="flex items-center justify-center h-full text-center">
                      <div className="max-w-md">
                        <InformationCircleIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">
                          No Analysis Available
                        </h3>
                        <p className="text-gray-600 mb-4">
                          Start a conversation or provide location data to get a detailed risk analysis.
                        </p>
                        <button
                          onClick={() => setActiveTab('chat')}
                          className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                        >
                          Start Chatting
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {activeTab === 'insights' && (
                <div className="p-6 overflow-y-auto h-full">
                  <div className="text-center">
                    <LightBulbIcon className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                      AI Insights Coming Soon
                    </h3>
                    <p className="text-gray-600">
                      Personalized insights and trend analysis will be available here.
                    </p>
                  </div>
                </div>
              )}
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
};

export default AIRiskAssistant;
