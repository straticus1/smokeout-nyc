import { ethers } from 'ethers';
import AsyncStorage from '@react-native-async-storage/async-storage';
import CryptoJS from 'crypto-js';

// Contract ABIs (simplified for demo)
const SMOKEOUT_NFT_ABI = [
  "function mint(address to, string memory tokenURI) public returns (uint256)",
  "function transfer(address to, uint256 tokenId) public",
  "function ownerOf(uint256 tokenId) public view returns (address)",
  "function tokenURI(uint256 tokenId) public view returns (string)",
  "function balanceOf(address owner) public view returns (uint256)",
  "function setApprovalForAll(address operator, bool approved) public",
  "function isApprovedForAll(address owner, address operator) public view returns (bool)"
];

const SMOKEOUT_TOKEN_ABI = [
  "function transfer(address to, uint256 amount) public returns (bool)",
  "function balanceOf(address account) public view returns (uint256)",
  "function decimals() public view returns (uint8)",
  "function symbol() public view returns (string)",
  "function name() public view returns (string)"
];

const MARKETPLACE_ABI = [
  "function listItem(address nftContract, uint256 tokenId, uint256 price) public",
  "function buyItem(address nftContract, uint256 tokenId) public payable",
  "function cancelListing(address nftContract, uint256 tokenId) public",
  "function getListingPrice(address nftContract, uint256 tokenId) public view returns (uint256)",
  "function isItemListed(address nftContract, uint256 tokenId) public view returns (bool)"
];

// Contract addresses (placeholder - replace with actual deployed addresses)
const CONTRACT_ADDRESSES = {
  SMOKEOUT_NFT: '0x1234567890123456789012345678901234567890',
  SMOKEOUT_TOKEN: '0x0987654321098765432109876543210987654321',
  MARKETPLACE: '0x1122334455667788990011223344556677889900',
  STAKING: '0x9988776655443322110099887766554433221100'
};

export interface NFTMetadata {
  name: string;
  description: string;
  image: string;
  attributes: {
    trait_type: string;
    value: string | number;
  }[];
  strain_genetics?: {
    thc_content: number;
    cbd_content: number;
    terpene_profile: string[];
    lineage: string[];
  };
  game_stats?: {
    rarity: 'Common' | 'Uncommon' | 'Rare' | 'Epic' | 'Legendary';
    power_level: number;
    yield_multiplier: number;
    growth_speed: number;
  };
}

export interface MarketplaceListing {
  tokenId: string;
  seller: string;
  price: string;
  metadata: NFTMetadata;
  listed_at: number;
}

export interface WalletBalance {
  eth: string;
  smokeoutToken: string;
  nftCount: number;
}

export interface TransactionResult {
  success: boolean;
  txHash?: string;
  error?: string;
  gasUsed?: string;
}

class BlockchainService {
  private provider: ethers.providers.JsonRpcProvider;
  private signer: ethers.Wallet | null = null;
  private contracts: { [key: string]: ethers.Contract } = {};
  private network: 'mainnet' | 'testnet' | 'local' = 'testnet';

  constructor() {
    // Initialize provider based on network
    this.initializeProvider();
  }

  private initializeProvider() {
    const rpcUrls = {
      mainnet: 'https://mainnet.infura.io/v3/YOUR_INFURA_PROJECT_ID',
      testnet: 'https://sepolia.infura.io/v3/YOUR_INFURA_PROJECT_ID',
      local: 'http://localhost:8545'
    };

    this.provider = new ethers.providers.JsonRpcProvider(rpcUrls[this.network]);
    this.initializeContracts();
  }

  private initializeContracts() {
    // Initialize contract instances (read-only initially)
    this.contracts.nft = new ethers.Contract(
      CONTRACT_ADDRESSES.SMOKEOUT_NFT,
      SMOKEOUT_NFT_ABI,
      this.provider
    );

    this.contracts.token = new ethers.Contract(
      CONTRACT_ADDRESSES.SMOKEOUT_TOKEN,
      SMOKEOUT_TOKEN_ABI,
      this.provider
    );

    this.contracts.marketplace = new ethers.Contract(
      CONTRACT_ADDRESSES.MARKETPLACE,
      MARKETPLACE_ABI,
      this.provider
    );
  }

  // Wallet Management
  async createWallet(): Promise<{ address: string; mnemonic: string }> {
    try {
      const wallet = ethers.Wallet.createRandom();
      const encryptedWallet = await this.encryptAndStoreWallet(wallet);
      
      return {
        address: wallet.address,
        mnemonic: wallet.mnemonic.phrase
      };
    } catch (error) {
      console.error('Failed to create wallet:', error);
      throw new Error('Wallet creation failed');
    }
  }

  async importWallet(mnemonic: string): Promise<string> {
    try {
      const wallet = ethers.Wallet.fromMnemonic(mnemonic);
      await this.encryptAndStoreWallet(wallet);
      return wallet.address;
    } catch (error) {
      console.error('Failed to import wallet:', error);
      throw new Error('Invalid mnemonic phrase');
    }
  }

  async connectWallet(password: string): Promise<string> {
    try {
      const encryptedWallet = await AsyncStorage.getItem('encrypted_wallet');
      if (!encryptedWallet) {
        throw new Error('No wallet found');
      }

      const decryptedData = CryptoJS.AES.decrypt(encryptedWallet, password).toString(CryptoJS.enc.Utf8);
      if (!decryptedData) {
        throw new Error('Invalid password');
      }

      const walletData = JSON.parse(decryptedData);
      this.signer = new ethers.Wallet(walletData.privateKey, this.provider);
      
      // Update contracts with signer
      this.updateContractsWithSigner();
      
      return this.signer.address;
    } catch (error) {
      console.error('Failed to connect wallet:', error);
      throw error;
    }
  }

  private async encryptAndStoreWallet(wallet: ethers.Wallet): Promise<void> {
    const walletData = {
      address: wallet.address,
      privateKey: wallet.privateKey,
      mnemonic: wallet.mnemonic?.phrase
    };

    // In a real app, get password from user input
    const password = 'user_provided_password';
    const encrypted = CryptoJS.AES.encrypt(JSON.stringify(walletData), password).toString();
    
    await AsyncStorage.setItem('encrypted_wallet', encrypted);
    await AsyncStorage.setItem('wallet_address', wallet.address);
  }

  private updateContractsWithSigner() {
    if (!this.signer) return;

    this.contracts.nft = this.contracts.nft.connect(this.signer);
    this.contracts.token = this.contracts.token.connect(this.signer);
    this.contracts.marketplace = this.contracts.marketplace.connect(this.signer);
  }

  // Balance Queries
  async getWalletBalance(address?: string): Promise<WalletBalance> {
    try {
      const walletAddress = address || this.signer?.address;
      if (!walletAddress) throw new Error('No wallet connected');

      const [ethBalance, tokenBalance, nftCount] = await Promise.all([
        this.provider.getBalance(walletAddress),
        this.contracts.token.balanceOf(walletAddress),
        this.contracts.nft.balanceOf(walletAddress)
      ]);

      return {
        eth: ethers.utils.formatEther(ethBalance),
        smokeoutToken: ethers.utils.formatUnits(tokenBalance, 18),
        nftCount: nftCount.toNumber()
      };
    } catch (error) {
      console.error('Failed to get wallet balance:', error);
      throw error;
    }
  }

  // NFT Operations
  async mintStrainNFT(
    recipientAddress: string,
    metadata: NFTMetadata,
    geneticsData: any
  ): Promise<TransactionResult> {
    try {
      if (!this.signer) throw new Error('Wallet not connected');

      // Upload metadata to IPFS (simulated)
      const metadataURI = await this.uploadToIPFS(metadata);
      
      const tx = await this.contracts.nft.mint(recipientAddress, metadataURI);
      const receipt = await tx.wait();

      // Store genetics data association
      await this.associateGeneticsData(receipt.events[0].args.tokenId, geneticsData);

      return {
        success: true,
        txHash: receipt.transactionHash,
        gasUsed: receipt.gasUsed.toString()
      };
    } catch (error) {
      console.error('Failed to mint NFT:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  async transferNFT(
    tokenId: string,
    toAddress: string
  ): Promise<TransactionResult> {
    try {
      if (!this.signer) throw new Error('Wallet not connected');

      const tx = await this.contracts.nft.transfer(toAddress, tokenId);
      const receipt = await tx.wait();

      return {
        success: true,
        txHash: receipt.transactionHash,
        gasUsed: receipt.gasUsed.toString()
      };
    } catch (error) {
      console.error('Failed to transfer NFT:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  async getUserNFTs(address?: string): Promise<NFTMetadata[]> {
    try {
      const walletAddress = address || this.signer?.address;
      if (!walletAddress) throw new Error('No wallet connected');

      const balance = await this.contracts.nft.balanceOf(walletAddress);
      const nfts: NFTMetadata[] = [];

      // Note: In a real implementation, you'd need an indexing service
      // This is a simplified approach
      for (let i = 0; i < balance.toNumber(); i++) {
        try {
          // This would require additional contract methods to get tokens by owner
          const tokenId = i; // Simplified
          const tokenURI = await this.contracts.nft.tokenURI(tokenId);
          const metadata = await this.fetchMetadataFromIPFS(tokenURI);
          nfts.push(metadata);
        } catch (err) {
          console.warn(`Failed to fetch NFT ${i}:`, err);
        }
      }

      return nfts;
    } catch (error) {
      console.error('Failed to get user NFTs:', error);
      return [];
    }
  }

  // Marketplace Operations
  async listNFTForSale(
    tokenId: string,
    priceInEth: string
  ): Promise<TransactionResult> {
    try {
      if (!this.signer) throw new Error('Wallet not connected');

      const priceInWei = ethers.utils.parseEther(priceInEth);
      
      // First approve marketplace to transfer the NFT
      const approveTx = await this.contracts.nft.setApprovalForAll(
        CONTRACT_ADDRESSES.MARKETPLACE,
        true
      );
      await approveTx.wait();

      // List the item
      const tx = await this.contracts.marketplace.listItem(
        CONTRACT_ADDRESSES.SMOKEOUT_NFT,
        tokenId,
        priceInWei
      );
      const receipt = await tx.wait();

      return {
        success: true,
        txHash: receipt.transactionHash,
        gasUsed: receipt.gasUsed.toString()
      };
    } catch (error) {
      console.error('Failed to list NFT:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  async buyNFTFromMarketplace(
    tokenId: string
  ): Promise<TransactionResult> {
    try {
      if (!this.signer) throw new Error('Wallet not connected');

      const price = await this.contracts.marketplace.getListingPrice(
        CONTRACT_ADDRESSES.SMOKEOUT_NFT,
        tokenId
      );

      const tx = await this.contracts.marketplace.buyItem(
        CONTRACT_ADDRESSES.SMOKEOUT_NFT,
        tokenId,
        { value: price }
      );
      const receipt = await tx.wait();

      return {
        success: true,
        txHash: receipt.transactionHash,
        gasUsed: receipt.gasUsed.toString()
      };
    } catch (error) {
      console.error('Failed to buy NFT:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  async getMarketplaceListings(): Promise<MarketplaceListing[]> {
    try {
      // In a real implementation, this would query events or use an indexing service
      // This is a simplified mock
      const mockListings: MarketplaceListing[] = [
        {
          tokenId: '1',
          seller: '0x1234567890123456789012345678901234567890',
          price: '0.1',
          metadata: {
            name: 'OG Kush Genesis',
            description: 'Rare Genesis strain with exceptional genetics',
            image: 'https://ipfs.io/ipfs/QmHash1',
            attributes: [
              { trait_type: 'Strain Type', value: 'Hybrid' },
              { trait_type: 'THC Content', value: 28.5 },
              { trait_type: 'Rarity', value: 'Legendary' }
            ],
            game_stats: {
              rarity: 'Legendary',
              power_level: 95,
              yield_multiplier: 1.8,
              growth_speed: 1.2
            }
          },
          listed_at: Date.now() - 86400000 // 1 day ago
        }
      ];

      return mockListings;
    } catch (error) {
      console.error('Failed to get marketplace listings:', error);
      return [];
    }
  }

  // Token Operations
  async sendTokens(
    toAddress: string,
    amount: string
  ): Promise<TransactionResult> {
    try {
      if (!this.signer) throw new Error('Wallet not connected');

      const amountInWei = ethers.utils.parseUnits(amount, 18);
      const tx = await this.contracts.token.transfer(toAddress, amountInWei);
      const receipt = await tx.wait();

      return {
        success: true,
        txHash: receipt.transactionHash,
        gasUsed: receipt.gasUsed.toString()
      };
    } catch (error) {
      console.error('Failed to send tokens:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Staking Operations (simplified)
  async stakeTokens(amount: string): Promise<TransactionResult> {
    try {
      if (!this.signer) throw new Error('Wallet not connected');
      
      // This would interact with a staking contract
      // Simplified implementation
      const stakingResult = await this.simulateStaking(amount);
      
      return {
        success: stakingResult.success,
        txHash: stakingResult.txHash,
        error: stakingResult.error
      };
    } catch (error) {
      console.error('Failed to stake tokens:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Utility Methods
  private async uploadToIPFS(metadata: NFTMetadata): Promise<string> {
    // Simulated IPFS upload - in production, use actual IPFS service
    const mockHash = 'Qm' + Math.random().toString(36).substring(2);
    
    // Store metadata locally for demo
    await AsyncStorage.setItem(`metadata_${mockHash}`, JSON.stringify(metadata));
    
    return `https://ipfs.io/ipfs/${mockHash}`;
  }

  private async fetchMetadataFromIPFS(uri: string): Promise<NFTMetadata> {
    // Simulated IPFS fetch
    const hash = uri.split('/').pop();
    const storedMetadata = await AsyncStorage.getItem(`metadata_${hash}`);
    
    if (storedMetadata) {
      return JSON.parse(storedMetadata);
    }
    
    // Fallback mock metadata
    return {
      name: 'Unknown Strain',
      description: 'Metadata not available',
      image: '',
      attributes: []
    };
  }

  private async associateGeneticsData(tokenId: string, geneticsData: any): Promise<void> {
    await AsyncStorage.setItem(`genetics_${tokenId}`, JSON.stringify(geneticsData));
  }

  private async simulateStaking(amount: string): Promise<TransactionResult> {
    // Simulate staking transaction
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    return {
      success: true,
      txHash: '0x' + Math.random().toString(16).substring(2, 66),
      gasUsed: '21000'
    };
  }

  // Network Management
  async switchNetwork(network: 'mainnet' | 'testnet' | 'local'): Promise<void> {
    this.network = network;
    this.initializeProvider();
    
    if (this.signer) {
      this.signer = this.signer.connect(this.provider);
      this.updateContractsWithSigner();
    }
  }

  async estimateGas(contractMethod: string, params: any[]): Promise<string> {
    try {
      if (!this.signer) throw new Error('Wallet not connected');
      
      // This would estimate gas for the specific contract method
      // Simplified implementation
      const gasEstimate = ethers.BigNumber.from('100000'); // Mock estimate
      
      return gasEstimate.toString();
    } catch (error) {
      console.error('Failed to estimate gas:', error);
      return '100000'; // Fallback
    }
  }

  // Event Listeners
  subscribeToNFTTransfers(callback: (event: any) => void): () => void {
    const filter = this.contracts.nft.filters.Transfer(null, this.signer?.address);
    
    this.contracts.nft.on(filter, callback);
    
    return () => {
      this.contracts.nft.removeListener(filter, callback);
    };
  }

  subscribeToMarketplaceEvents(callback: (event: any) => void): () => void {
    // Subscribe to marketplace events
    const filter = this.contracts.marketplace.filters.ItemListed();
    
    this.contracts.marketplace.on(filter, callback);
    
    return () => {
      this.contracts.marketplace.removeListener(filter, callback);
    };
  }

  // Disconnect wallet
  async disconnectWallet(): Promise<void> {
    this.signer = null;
    this.initializeContracts(); // Reset to read-only contracts
    await AsyncStorage.removeItem('encrypted_wallet');
    await AsyncStorage.removeItem('wallet_address');
  }
}

export default new BlockchainService();
