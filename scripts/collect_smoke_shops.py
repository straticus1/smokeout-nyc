#!/usr/bin/env python3
"""
NYC Smoke Shop Data Collector
Collects smoke shop data from multiple sources and generates JSON file
"""

import json
import requests
import time
import re
from datetime import datetime
from typing import List, Dict, Optional
import argparse
import logging
from dataclasses import dataclass
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import googlemaps

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

@dataclass
class SmokeShop:
    name: str
    address: str
    borough: str
    latitude: Optional[float] = None
    longitude: Optional[float] = None
    phone: Optional[str] = None
    website: Optional[str] = None
    hours: Optional[Dict] = None
    status: str = "UNKNOWN"
    source: str = ""
    business_type: str = "smoke_shop"
    last_updated: str = ""

class NYCSmokeShopCollector:
    def __init__(self, google_maps_api_key: Optional[str] = None):
        self.shops: List[SmokeShop] = []
        self.gmaps = googlemaps.Client(key=google_maps_api_key) if google_maps_api_key else None
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        })
        
        # NYC boroughs and their bounds
        self.boroughs = {
            'Manhattan': {
                'bounds': {
                    'north': 40.8176,
                    'south': 40.7047,
                    'east': -73.9442,
                    'west': -74.0479
                },
                'center': {'lat': 40.7589, 'lng': -73.9851}
            },
            'Brooklyn': {
                'bounds': {
                    'north': 40.7395,
                    'south': 40.5707,
                    'east': -73.8333,
                    'west': -74.0421
                },
                'center': {'lat': 40.6892, 'lng': -73.9442}
            },
            'Queens': {
                'bounds': {
                    'north': 40.8007,
                    'south': 40.5431,
                    'east': -73.7004,
                    'west': -74.0421
                },
                'center': {'lat': 40.7282, 'lng': -73.7949}
            },
            'Bronx': {
                'bounds': {
                    'north': 40.9176,
                    'south': 40.7854,
                    'east': -73.7654,
                    'west': -73.9339
                },
                'center': {'lat': 40.8448, 'lng': -73.8648}
            },
            'Staten Island': {
                'bounds': {
                    'north': 40.6514,
                    'south': 40.4774,
                    'east': -74.0431,
                    'west': -74.2591
                },
                'center': {'lat': 40.5795, 'lng': -74.1502}
            }
        }

    def collect_from_google_places(self) -> None:
        """Collect smoke shops from Google Places API"""
        if not self.gmaps:
            logger.warning("Google Maps API key not provided, skipping Google Places collection")
            return

        logger.info("Collecting data from Google Places API...")
        
        search_terms = [
            'smoke shop',
            'tobacco shop',
            'head shop',
            'vape shop',
            'hookah lounge',
            'cigar shop',
            'cannabis dispensary'
        ]

        for borough_name, borough_data in self.boroughs.items():
            logger.info(f"Searching in {borough_name}...")
            
            for search_term in search_terms:
                try:
                    # Search for places
                    places_result = self.gmaps.places_nearby(
                        location=borough_data['center'],
                        radius=10000,  # 10km radius
                        keyword=search_term,
                        type='store'
                    )

                    for place in places_result.get('results', []):
                        # Get detailed place information
                        place_details = self.gmaps.place(
                            place_id=place['place_id'],
                            fields=['name', 'formatted_address', 'geometry', 'formatted_phone_number', 
                                   'website', 'opening_hours', 'business_status', 'types']
                        )['result']

                        # Extract data
                        shop = SmokeShop(
                            name=place_details.get('name', ''),
                            address=place_details.get('formatted_address', ''),
                            borough=borough_name,
                            latitude=place_details.get('geometry', {}).get('location', {}).get('lat'),
                            longitude=place_details.get('geometry', {}).get('location', {}).get('lng'),
                            phone=place_details.get('formatted_phone_number'),
                            website=place_details.get('website'),
                            hours=self._parse_opening_hours(place_details.get('opening_hours', {})),
                            status=self._map_business_status(place_details.get('business_status')),
                            source='Google Places API',
                            last_updated=datetime.now().isoformat()
                        )

                        # Filter for NYC addresses and relevant business types
                        if self._is_valid_nyc_shop(shop):
                            self.shops.append(shop)

                    time.sleep(1)  # Rate limiting

                except Exception as e:
                    logger.error(f"Error searching Google Places for {search_term} in {borough_name}: {e}")

    def collect_from_yelp(self) -> None:
        """Collect smoke shops from Yelp Fusion API"""
        logger.info("Collecting data from Yelp (web scraping)...")
        
        # Note: For production, you'd want to use Yelp Fusion API with proper API key
        # This is a simplified web scraping approach
        
        search_terms = ['smoke shops', 'tobacco shops', 'vape shops']
        
        for borough_name, borough_data in self.boroughs.items():
            for search_term in search_terms:
                try:
                    # Construct Yelp search URL
                    location = f"{borough_name}, New York, NY"
                    url = f"https://www.yelp.com/search?find_desc={search_term}&find_loc={location}"
                    
                    response = self.session.get(url)
                    if response.status_code == 200:
                        # Parse Yelp results (simplified - would need proper HTML parsing)
                        # This is a placeholder for actual Yelp data extraction
                        logger.info(f"Successfully fetched Yelp page for {search_term} in {borough_name}")
                    
                    time.sleep(2)  # Rate limiting
                    
                except Exception as e:
                    logger.error(f"Error fetching Yelp data for {search_term} in {borough_name}: {e}")

    def collect_from_yellow_pages(self) -> None:
        """Collect smoke shops from Yellow Pages"""
        logger.info("Collecting data from Yellow Pages...")
        
        categories = ['tobacco-shops', 'smoke-shops', 'vaping']
        
        for borough_name in self.boroughs.keys():
            for category in categories:
                try:
                    url = f"https://www.yellowpages.com/{borough_name.lower().replace(' ', '-')}-ny/{category}"
                    response = self.session.get(url)
                    
                    if response.status_code == 200:
                        # Parse Yellow Pages results (simplified)
                        logger.info(f"Successfully fetched Yellow Pages for {category} in {borough_name}")
                    
                    time.sleep(2)
                    
                except Exception as e:
                    logger.error(f"Error fetching Yellow Pages data for {category} in {borough_name}: {e}")

    def collect_from_nyc_business_directory(self) -> None:
        """Collect from NYC Business Directory and licensing data"""
        logger.info("Collecting data from NYC Business Directory...")
        
        try:
            # NYC Open Data - Business Licenses
            url = "https://data.cityofnewyork.us/resource/w7w3-xahh.json"
            params = {
                '$where': "license_type LIKE '%TOBACCO%' OR license_type LIKE '%SMOKE%'",
                '$limit': 10000
            }
            
            response = self.session.get(url, params=params)
            if response.status_code == 200:
                data = response.json()
                
                for business in data:
                    # Extract business information
                    address = f"{business.get('address_building', '')} {business.get('address_street_name', '')}, {business.get('address_city', 'New York')}, NY {business.get('address_zip', '')}"
                    
                    shop = SmokeShop(
                        name=business.get('business_name', ''),
                        address=address.strip(),
                        borough=self._map_borough(business.get('address_borough', '')),
                        phone=business.get('contact_phone'),
                        status=self._map_license_status(business.get('license_status')),
                        source='NYC Business Directory',
                        business_type='licensed_tobacco',
                        last_updated=datetime.now().isoformat()
                    )
                    
                    if self._is_valid_nyc_shop(shop):
                        self.shops.append(shop)
                        
        except Exception as e:
            logger.error(f"Error collecting NYC Business Directory data: {e}")

    def geocode_addresses(self) -> None:
        """Geocode addresses that don't have coordinates"""
        if not self.gmaps:
            logger.warning("Google Maps API key not provided, skipping geocoding")
            return

        logger.info("Geocoding addresses without coordinates...")
        
        for shop in self.shops:
            if shop.latitude is None or shop.longitude is None:
                try:
                    geocode_result = self.gmaps.geocode(shop.address)
                    if geocode_result:
                        location = geocode_result[0]['geometry']['location']
                        shop.latitude = location['lat']
                        shop.longitude = location['lng']
                        
                        # Update borough based on geocoded location
                        shop.borough = self._get_borough_from_coords(shop.latitude, shop.longitude)
                    
                    time.sleep(0.1)  # Rate limiting
                    
                except Exception as e:
                    logger.error(f"Error geocoding {shop.address}: {e}")

    def remove_duplicates(self) -> None:
        """Remove duplicate entries based on name and address similarity"""
        logger.info("Removing duplicate entries...")
        
        unique_shops = []
        seen_combinations = set()
        
        for shop in self.shops:
            # Create a normalized identifier
            normalized_name = re.sub(r'[^\w\s]', '', shop.name.lower()).strip()
            normalized_address = re.sub(r'[^\w\s]', '', shop.address.lower()).strip()
            identifier = f"{normalized_name}_{normalized_address}"
            
            if identifier not in seen_combinations:
                seen_combinations.add(identifier)
                unique_shops.append(shop)
            else:
                logger.debug(f"Duplicate found: {shop.name} at {shop.address}")
        
        logger.info(f"Removed {len(self.shops) - len(unique_shops)} duplicates")
        self.shops = unique_shops

    def save_to_json(self, filename: str) -> None:
        """Save collected data to JSON file"""
        logger.info(f"Saving {len(self.shops)} shops to {filename}")
        
        # Convert dataclasses to dictionaries
        data = {
            'metadata': {
                'collection_date': datetime.now().isoformat(),
                'total_shops': len(self.shops),
                'boroughs': {borough: len([s for s in self.shops if s.borough == borough]) 
                           for borough in self.boroughs.keys()},
                'sources': list(set(shop.source for shop in self.shops))
            },
            'shops': [shop.__dict__ for shop in self.shops]
        }
        
        with open(filename, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
        
        logger.info(f"Successfully saved data to {filename}")

    def _parse_opening_hours(self, hours_data: Dict) -> Optional[Dict]:
        """Parse Google Places opening hours"""
        if not hours_data or 'weekday_text' not in hours_data:
            return None
        
        hours = {}
        days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
        
        for i, day_text in enumerate(hours_data.get('weekday_text', [])):
            if i < len(days):
                hours[days[i]] = day_text.split(': ', 1)[1] if ': ' in day_text else 'Closed'
        
        return hours

    def _map_business_status(self, status: Optional[str]) -> str:
        """Map Google Places business status to our status"""
        if not status:
            return 'UNKNOWN'
        
        status_map = {
            'OPERATIONAL': 'OPEN',
            'CLOSED_TEMPORARILY': 'CLOSED_OTHER',
            'CLOSED_PERMANENTLY': 'CLOSED_OTHER'
        }
        
        return status_map.get(status, 'UNKNOWN')

    def _map_license_status(self, status: Optional[str]) -> str:
        """Map NYC license status to our status"""
        if not status:
            return 'UNKNOWN'
        
        status = status.upper()
        if 'ACTIVE' in status:
            return 'OPEN'
        elif 'EXPIRED' in status or 'REVOKED' in status or 'SUSPENDED' in status:
            return 'CLOSED_OTHER'
        
        return 'UNKNOWN'

    def _map_borough(self, borough: str) -> str:
        """Standardize borough names"""
        borough = borough.upper().strip()
        
        borough_map = {
            'MANHATTAN': 'Manhattan',
            'BROOKLYN': 'Brooklyn',
            'QUEENS': 'Queens',
            'BRONX': 'Bronx',
            'STATEN ISLAND': 'Staten Island',
            'NEW YORK': 'Manhattan'  # Often used for Manhattan
        }
        
        return borough_map.get(borough, borough.title())

    def _get_borough_from_coords(self, lat: float, lng: float) -> str:
        """Determine borough from coordinates"""
        for borough_name, borough_data in self.boroughs.items():
            bounds = borough_data['bounds']
            if (bounds['south'] <= lat <= bounds['north'] and 
                bounds['west'] <= lng <= bounds['east']):
                return borough_name
        
        return 'Unknown'

    def _is_valid_nyc_shop(self, shop: SmokeShop) -> bool:
        """Validate if shop is a relevant NYC smoke shop"""
        # Check if address contains NYC identifiers
        address_lower = shop.address.lower()
        nyc_indicators = ['ny', 'new york', 'brooklyn', 'queens', 'bronx', 'manhattan', 'staten island']
        
        if not any(indicator in address_lower for indicator in nyc_indicators):
            return False
        
        # Check if name suggests it's a smoke/tobacco shop
        name_lower = shop.name.lower()
        relevant_keywords = [
            'smoke', 'tobacco', 'vape', 'hookah', 'cigar', 'head shop', 
            'cannabis', 'cbd', 'kratom', 'convenience'
        ]
        
        # Allow if name contains relevant keywords or if it's from business directory
        if any(keyword in name_lower for keyword in relevant_keywords) or shop.source == 'NYC Business Directory':
            return True
        
        return False

def main():
    parser = argparse.ArgumentParser(description='Collect NYC smoke shop data')
    parser.add_argument('--output', '-o', default='nyc_smoke_shops.json', 
                       help='Output JSON filename')
    parser.add_argument('--google-api-key', help='Google Maps API key for geocoding')
    parser.add_argument('--sources', nargs='+', 
                       choices=['google', 'yelp', 'yellowpages', 'nyc'],
                       default=['google', 'nyc'],
                       help='Data sources to use')
    parser.add_argument('--verbose', '-v', action='store_true', 
                       help='Enable verbose logging')
    
    args = parser.parse_args()
    
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    collector = NYCSmokeShopCollector(args.google_api_key)
    
    # Collect from specified sources
    if 'google' in args.sources:
        collector.collect_from_google_places()
    
    if 'yelp' in args.sources:
        collector.collect_from_yelp()
    
    if 'yellowpages' in args.sources:
        collector.collect_from_yellow_pages()
    
    if 'nyc' in args.sources:
        collector.collect_from_nyc_business_directory()
    
    # Process collected data
    collector.geocode_addresses()
    collector.remove_duplicates()
    collector.save_to_json(args.output)
    
    logger.info("Data collection completed!")

if __name__ == '__main__':
    main()
