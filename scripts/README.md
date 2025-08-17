# NYC Smoke Shop Data Management Scripts

This directory contains scripts for collecting and importing NYC smoke shop data into the SmokeoutNYC database.

## Files Overview

### Data Collection
- **`collect_smoke_shops.py`** - Python script to collect smoke shop data from multiple sources
- **`requirements.txt`** - Python dependencies for the collection script

### Data Import
- **`import_shops.php`** - PHP CLI script to import JSON data into the PostgreSQL database
- **`update_database.sh`** - Bash orchestration script that handles the complete workflow

### Sample Data
- **`sample_data.json`** - Sample JSON structure for testing
- **`cron_example.txt`** - Example cron job configurations

## Quick Start

### 1. Setup Environment

```bash
# Install Python dependencies
cd scripts
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Ensure your .env file is configured in the project root
cp ../env.example ../.env
# Edit .env with your database and API keys
```

### 2. Test with Sample Data

```bash
# Test the import process with sample data
./update_database.sh --use-sample --dry-run --verbose
```

### 3. Full Data Collection

```bash
# Collect real data and import to database
./update_database.sh --google-api-key=YOUR_API_KEY --verbose
```

### 4. Refresh Mode (Detect Changes)

```bash
# Check for status changes without full data collection
./update_database.sh --refresh --skip-collection --verbose
```

## Data Collection Sources

The Python script collects data from multiple sources:

1. **Google Places API** - Primary source for business information
2. **NYC Business Directory** - Official NYC licensing data
3. **Yelp** - Additional business listings (web scraping)
4. **Yellow Pages** - Supplementary business data

## JSON Data Structure

```json
{
  "metadata": {
    "collection_date": "2024-01-15T10:30:00Z",
    "total_shops": 150,
    "boroughs": {
      "Manhattan": 45,
      "Brooklyn": 38,
      "Queens": 32,
      "Bronx": 20,
      "Staten Island": 15
    },
    "sources": ["Google Places API", "NYC Business Directory"]
  },
  "shops": [
    {
      "name": "Example Smoke Shop",
      "address": "123 Main St, New York, NY 10001",
      "borough": "Manhattan",
      "latitude": 40.7589,
      "longitude": -73.9851,
      "phone": "+1 (212) 555-0123",
      "website": "https://example.com",
      "hours": {
        "Monday": "9:00 AM – 9:00 PM",
        "Tuesday": "9:00 AM – 9:00 PM"
      },
      "status": "OPEN",
      "source": "Google Places API",
      "business_type": "smoke_shop",
      "last_updated": "2024-01-15T10:30:00Z"
    }
  ]
}
```

## Script Options

### Data Collection Script (`collect_smoke_shops.py`)

```bash
python3 collect_smoke_shops.py [OPTIONS]

Options:
  --output, -o          Output JSON filename (default: nyc_smoke_shops.json)
  --google-api-key      Google Maps API key for geocoding
  --sources             Data sources to use (google, yelp, yellowpages, nyc)
  --verbose, -v         Enable verbose logging
```

### Import Script (`import_shops.php`)

```bash
php import_shops.php --file=<json_file> [OPTIONS]

Options:
  --file=<file>         JSON file to import (required)
  --refresh             Refresh mode: detect status changes
  --dry-run             Show what would be done without making changes
  --verbose, -v         Verbose output
  --help, -h            Show help message
```

### Orchestration Script (`update_database.sh`)

```bash
./update_database.sh [OPTIONS]

Options:
  --refresh             Enable refresh mode (detect status changes)
  --dry-run            Show what would be done without making changes
  --verbose            Enable verbose output
  --skip-collection    Skip data collection, use existing JSON file
  --use-sample         Use sample data instead of collecting real data
  --google-api-key     Google Maps API key for geocoding
  --help               Show help message
```

## Refresh Mode Features

When using `--refresh` mode, the system:

1. **Detects Status Changes**: Compares current data with database to find status changes
2. **Identifies New Shops**: Finds shops that weren't in the previous import
3. **Flags Potentially Closed**: Marks shops as potentially closed if they disappear from data sources
4. **Updates Information**: Refreshes phone numbers, addresses, hours, etc.

## Automation with Cron

Set up automated data collection and refresh:

```bash
# Edit your crontab
crontab -e

# Add entries (see cron_example.txt for examples):
# Full collection every Sunday at 2 AM
0 2 * * 0 /path/to/scripts/update_database.sh --google-api-key=KEY >> /var/log/smokeout.log 2>&1

# Daily refresh at 6 AM
0 6 * * * /path/to/scripts/update_database.sh --refresh --skip-collection >> /var/log/smokeout.log 2>&1
```

## Environment Variables

Set these in your `.env` file or environment:

```bash
# Database connection
DATABASE_URL="postgresql://user:pass@localhost:5432/smokeout_nyc"

# Google Maps API (for geocoding)
GOOGLE_MAPS_API_KEY="your-google-maps-api-key"

# Optional: Yelp API (if you have access)
YELP_API_KEY="your-yelp-api-key"
```

## Data Quality Features

### Duplicate Detection
- Matches shops by name and address similarity
- Uses coordinate-based matching for fuzzy duplicates
- Removes exact duplicates automatically

### Data Validation
- Validates NYC addresses using borough boundaries
- Filters for relevant business types (smoke shops, tobacco, vape, etc.)
- Geocodes addresses for accurate mapping

### Status Mapping
- Maps various status formats to standardized values
- Handles Google Places business status
- Processes NYC licensing status

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check your `.env` file configuration
   - Ensure PostgreSQL is running
   - Verify database credentials

2. **Google API Quota Exceeded**
   - Check your Google Cloud Console quotas
   - Consider spreading requests over time
   - Use `--skip-collection` to avoid API calls

3. **JSON Validation Failed**
   - Check the JSON file structure
   - Look for encoding issues
   - Verify the file wasn't corrupted

4. **Permission Denied**
   - Ensure scripts are executable: `chmod +x update_database.sh`
   - Check file permissions in the scripts directory

### Debugging

Enable verbose mode for detailed logging:

```bash
./update_database.sh --verbose --dry-run
```

Check log files for errors:

```bash
tail -f /var/log/smokeout_update.log
```

## Performance Considerations

- **Rate Limiting**: Scripts include delays to respect API limits
- **Batch Processing**: Database operations use transactions
- **Memory Usage**: Large datasets are processed in chunks
- **Geocoding**: Expensive operation, results are cached

## Security Notes

- API keys should be stored securely in environment variables
- Database credentials must be protected
- Web scraping respects robots.txt and rate limits
- All user input is sanitized before database insertion

## Contributing

When adding new data sources:

1. Add source to `collect_smoke_shops.py`
2. Update the source mapping in `import_shops.php`
3. Add tests with sample data
4. Update documentation

For database schema changes:
1. Update Prisma schema
2. Create migration
3. Update import script field mappings
4. Test with sample data
