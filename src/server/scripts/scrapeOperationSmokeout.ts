import puppeteer from 'puppeteer';
import axios from 'axios';
import * as cheerio from 'cheerio';
import { prisma } from '../index';
import { StoreStatus } from '@prisma/client';

interface ScrapedStore {
  name: string;
  address: string;
  latitude?: number;
  longitude?: number;
  closureDate?: Date;
  reason?: string;
  source: string;
}

class OperationSmokeoutScraper {
  private delay = parseInt(process.env.SCRAPING_DELAY || '2000');
  private userAgent = process.env.USER_AGENT || 'SmokeoutNYC Bot 1.0';

  async scrapeNYCGovData(): Promise<ScrapedStore[]> {
    console.log('Scraping NYC.gov data for Operation Smokeout closures...');
    
    const stores: ScrapedStore[] = [];
    
    try {
      // This would be the actual NYC.gov endpoint for Operation Smokeout data
      // For now, this is a placeholder - you'd need to find the real endpoint
      const response = await axios.get('https://data.cityofnewyork.us/api/views/example-endpoint', {
        headers: {
          'User-Agent': this.userAgent
        }
      });

      // Parse the response data
      if (response.data && response.data.data) {
        for (const record of response.data.data) {
          // Assuming the data structure - adjust based on actual API
          stores.push({
            name: record[8] || 'Unknown Store', // Adjust index based on actual data
            address: record[9] || 'Unknown Address',
            closureDate: record[10] ? new Date(record[10]) : undefined,
            reason: 'Operation Smokeout',
            source: 'NYC.gov Open Data'
          });
        }
      }
    } catch (error) {
      console.error('Error scraping NYC.gov data:', error);
    }

    return stores;
  }

  async scrapeNewsArticles(): Promise<ScrapedStore[]> {
    console.log('Scraping news articles for Operation Smokeout mentions...');
    
    const stores: ScrapedStore[] = [];
    const browser = await puppeteer.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
      const page = await browser.newPage();
      await page.setUserAgent(this.userAgent);

      // Search for Operation Smokeout news articles
      const searchQueries = [
        'Operation Smokeout NYC smoke shops closed',
        'NYC smoke shop raids closures 2023 2024',
        'illegal smoke shops closed New York City'
      ];

      for (const query of searchQueries) {
        await this.delay_execution();
        
        // Search Google News for relevant articles
        await page.goto(`https://www.google.com/search?q=${encodeURIComponent(query)}&tbm=nws`);
        
        // Extract article links
        const links = await page.evaluate(() => {
          const linkElements = document.querySelectorAll('a[href*="/url?q="]');
          return Array.from(linkElements).map(link => {
            const href = link.getAttribute('href');
            if (href) {
              const url = new URLSearchParams(href.split('?')[1]).get('q');
              return url;
            }
            return null;
          }).filter(url => url && !url.includes('google.com'));
        });

        // Visit each article and extract store information
        for (const link of links.slice(0, 5)) { // Limit to first 5 articles per query
          if (link) {
            try {
              await this.delay_execution();
              await page.goto(link, { waitUntil: 'networkidle2', timeout: 30000 });
              
              const content = await page.content();
              const extractedStores = this.extractStoresFromArticle(content, link);
              stores.push(...extractedStores);
            } catch (error) {
              console.error(`Error scraping article ${link}:`, error);
            }
          }
        }
      }
    } catch (error) {
      console.error('Error in news scraping:', error);
    } finally {
      await browser.close();
    }

    return stores;
  }

  private extractStoresFromArticle(html: string, source: string): ScrapedStore[] {
    const $ = cheerio.load(html);
    const stores: ScrapedStore[] = [];
    const text = $.text().toLowerCase();

    // Look for patterns indicating store closures
    const patterns = [
      /(\d+\s+[a-zA-Z\s]+(?:street|st|avenue|ave|boulevard|blvd|road|rd|drive|dr))/gi,
      /([a-zA-Z\s]+smoke\s*shop)/gi,
      /([a-zA-Z\s]+tobacco)/gi,
      /([a-zA-Z\s]+convenience)/gi
    ];

    const addresses: string[] = [];
    const storeNames: string[] = [];

    patterns.forEach(pattern => {
      const matches = text.match(pattern);
      if (matches) {
        matches.forEach(match => {
          if (match.includes('street') || match.includes('avenue') || match.includes('boulevard')) {
            addresses.push(match.trim());
          } else if (match.includes('smoke') || match.includes('tobacco')) {
            storeNames.push(match.trim());
          }
        });
      }
    });

    // Combine found information
    const maxLength = Math.max(addresses.length, storeNames.length);
    for (let i = 0; i < maxLength; i++) {
      stores.push({
        name: storeNames[i] || 'Unknown Store',
        address: addresses[i] || 'Address not found',
        reason: 'Operation Smokeout',
        source
      });
    }

    return stores;
  }

  async geocodeAddress(address: string): Promise<{ latitude: number; longitude: number } | null> {
    try {
      // Using Google Geocoding API (you'll need to set up API key)
      const response = await axios.get(`https://maps.googleapis.com/maps/api/geocode/json`, {
        params: {
          address: `${address}, New York, NY`,
          key: process.env.GOOGLE_MAPS_API_KEY
        }
      });

      if (response.data.results && response.data.results.length > 0) {
        const location = response.data.results[0].geometry.location;
        return {
          latitude: location.lat,
          longitude: location.lng
        };
      }
    } catch (error) {
      console.error('Geocoding error:', error);
    }
    
    return null;
  }

  async saveScrapedStores(stores: ScrapedStore[]): Promise<void> {
    console.log(`Saving ${stores.length} scraped stores to database...`);
    
    for (const store of stores) {
      try {
        // Try to geocode the address
        const coordinates = await this.geocodeAddress(store.address);
        await this.delay_execution(1000); // Rate limit geocoding requests

        // Check if store already exists
        const existingStore = await prisma.store.findFirst({
          where: {
            OR: [
              { name: { contains: store.name, mode: 'insensitive' } },
              { address: { contains: store.address, mode: 'insensitive' } }
            ]
          }
        });

        if (existingStore) {
          // Update existing store if it's not already marked as closed
          if (existingStore.status === StoreStatus.OPEN) {
            await prisma.store.update({
              where: { id: existingStore.id },
              data: {
                status: StoreStatus.CLOSED_OPERATION_SMOKEOUT,
                operationSmokeoutDate: store.closureDate,
                closureReason: store.reason
              }
            });
            console.log(`Updated existing store: ${store.name}`);
          }
        } else {
          // Create new store entry
          await prisma.store.create({
            data: {
              name: store.name,
              address: store.address,
              latitude: coordinates?.latitude || 0,
              longitude: coordinates?.longitude || 0,
              status: StoreStatus.CLOSED_OPERATION_SMOKEOUT,
              operationSmokeoutDate: store.closureDate,
              closureReason: store.reason,
              description: `Store closed due to ${store.reason}. Source: ${store.source}`,
              isVerified: false
            }
          });
          console.log(`Created new store entry: ${store.name}`);
        }
      } catch (error) {
        console.error(`Error saving store ${store.name}:`, error);
      }
    }
  }

  private async delay_execution(ms?: number): Promise<void> {
    const delayTime = ms || this.delay;
    return new Promise(resolve => setTimeout(resolve, delayTime));
  }

  async run(): Promise<void> {
    console.log('Starting Operation Smokeout scraping...');
    
    try {
      // Scrape from multiple sources
      const [nycGovStores, newsStores] = await Promise.all([
        this.scrapeNYCGovData(),
        this.scrapeNewsArticles()
      ]);

      const allStores = [...nycGovStores, ...newsStores];
      
      // Remove duplicates based on name and address similarity
      const uniqueStores = this.removeDuplicates(allStores);
      
      console.log(`Found ${uniqueStores.length} unique stores to process`);
      
      // Save to database
      await this.saveScrapedStores(uniqueStores);
      
      console.log('Operation Smokeout scraping completed successfully');
    } catch (error) {
      console.error('Error in Operation Smokeout scraping:', error);
      throw error;
    }
  }

  private removeDuplicates(stores: ScrapedStore[]): ScrapedStore[] {
    const unique: ScrapedStore[] = [];
    const seen = new Set<string>();

    for (const store of stores) {
      const key = `${store.name.toLowerCase()}-${store.address.toLowerCase()}`;
      if (!seen.has(key)) {
        seen.add(key);
        unique.push(store);
      }
    }

    return unique;
  }
}

// Export for use in cron jobs or manual execution
export { OperationSmokeoutScraper };

// Allow running this script directly
if (require.main === module) {
  const scraper = new OperationSmokeoutScraper();
  scraper.run()
    .then(() => {
      console.log('Scraping completed');
      process.exit(0);
    })
    .catch((error) => {
      console.error('Scraping failed:', error);
      process.exit(1);
    });
}
