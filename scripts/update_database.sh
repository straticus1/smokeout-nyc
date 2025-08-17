#!/bin/bash

# NYC Smoke Shop Database Update Script
# Orchestrates data collection and import process

set -e  # Exit on any error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
VENV_DIR="$SCRIPT_DIR/venv"
JSON_FILE="$SCRIPT_DIR/nyc_smoke_shops.json"
BACKUP_FILE="$SCRIPT_DIR/nyc_smoke_shops_backup_$(date +%Y%m%d_%H%M%S).json"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default options
REFRESH_MODE=false
DRY_RUN=false
VERBOSE=false
SKIP_COLLECTION=false
USE_SAMPLE=false

# Function to print colored output
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

# Function to show usage
show_usage() {
    cat << EOF
NYC Smoke Shop Database Update Script

Usage: $0 [OPTIONS]

OPTIONS:
    --refresh           Enable refresh mode (detect status changes)
    --dry-run          Show what would be done without making changes
    --verbose          Enable verbose output
    --skip-collection  Skip data collection, use existing JSON file
    --use-sample       Use sample data instead of collecting real data
    --google-api-key   Google Maps API key for geocoding
    --help             Show this help message

EXAMPLES:
    # Full update with data collection
    $0 --google-api-key=YOUR_API_KEY

    # Refresh mode to detect changes
    $0 --refresh --verbose

    # Dry run to see what would happen
    $0 --dry-run --verbose

    # Use sample data for testing
    $0 --use-sample --dry-run

    # Skip collection and use existing JSON
    $0 --skip-collection --refresh

ENVIRONMENT VARIABLES:
    GOOGLE_MAPS_API_KEY    Google Maps API key (alternative to --google-api-key)

EOF
}

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --refresh)
                REFRESH_MODE=true
                shift
                ;;
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --verbose)
                VERBOSE=true
                shift
                ;;
            --skip-collection)
                SKIP_COLLECTION=true
                shift
                ;;
            --use-sample)
                USE_SAMPLE=true
                shift
                ;;
            --google-api-key=*)
                GOOGLE_API_KEY="${1#*=}"
                shift
                ;;
            --help|-h)
                show_usage
                exit 0
                ;;
            *)
                error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
}

# Setup Python virtual environment
setup_python_env() {
    log "Setting up Python environment..."
    
    if [ ! -d "$VENV_DIR" ]; then
        log "Creating Python virtual environment..."
        python3 -m venv "$VENV_DIR"
    fi
    
    source "$VENV_DIR/bin/activate"
    
    log "Installing Python dependencies..."
    pip install -q -r "$SCRIPT_DIR/requirements.txt"
    
    success "Python environment ready"
}

# Collect smoke shop data
collect_data() {
    if [ "$USE_SAMPLE" = true ]; then
        log "Using sample data..."
        cp "$SCRIPT_DIR/sample_data.json" "$JSON_FILE"
        success "Sample data copied to $JSON_FILE"
        return 0
    fi
    
    if [ "$SKIP_COLLECTION" = true ]; then
        if [ -f "$JSON_FILE" ]; then
            log "Skipping data collection, using existing file: $JSON_FILE"
            return 0
        else
            error "No existing JSON file found at $JSON_FILE"
            exit 1
        fi
    fi
    
    log "Starting data collection..."
    
    # Backup existing JSON file if it exists
    if [ -f "$JSON_FILE" ]; then
        log "Backing up existing data to $BACKUP_FILE"
        cp "$JSON_FILE" "$BACKUP_FILE"
    fi
    
    # Prepare collection command
    COLLECTION_CMD="python3 $SCRIPT_DIR/collect_smoke_shops.py --output=$JSON_FILE"
    
    if [ -n "$GOOGLE_API_KEY" ] || [ -n "$GOOGLE_MAPS_API_KEY" ]; then
        API_KEY="${GOOGLE_API_KEY:-$GOOGLE_MAPS_API_KEY}"
        COLLECTION_CMD="$COLLECTION_CMD --google-api-key=$API_KEY"
    else
        warning "No Google Maps API key provided. Geocoding will be skipped."
    fi
    
    if [ "$VERBOSE" = true ]; then
        COLLECTION_CMD="$COLLECTION_CMD --verbose"
    fi
    
    # Run data collection
    source "$VENV_DIR/bin/activate"
    
    log "Running: $COLLECTION_CMD"
    if eval "$COLLECTION_CMD"; then
        success "Data collection completed successfully"
    else
        error "Data collection failed"
        exit 1
    fi
}

# Import data to database
import_data() {
    log "Starting database import..."
    
    if [ ! -f "$JSON_FILE" ]; then
        error "JSON file not found: $JSON_FILE"
        exit 1
    fi
    
    # Prepare import command
    IMPORT_CMD="php $SCRIPT_DIR/import_shops.php --file=$JSON_FILE"
    
    if [ "$REFRESH_MODE" = true ]; then
        IMPORT_CMD="$IMPORT_CMD --refresh"
    fi
    
    if [ "$DRY_RUN" = true ]; then
        IMPORT_CMD="$IMPORT_CMD --dry-run"
    fi
    
    if [ "$VERBOSE" = true ]; then
        IMPORT_CMD="$IMPORT_CMD --verbose"
    fi
    
    # Check if .env file exists
    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        error ".env file not found in project root"
        error "Please copy env.example to .env and configure your database settings"
        exit 1
    fi
    
    # Run import
    log "Running: $IMPORT_CMD"
    if eval "$IMPORT_CMD"; then
        success "Database import completed successfully"
    else
        error "Database import failed"
        exit 1
    fi
}

# Validate JSON file
validate_json() {
    if [ ! -f "$JSON_FILE" ]; then
        return 1
    fi
    
    log "Validating JSON file structure..."
    
    # Basic JSON validation using Python
    python3 -c "
import json
import sys

try:
    with open('$JSON_FILE', 'r') as f:
        data = json.load(f)
    
    if 'shops' not in data or not isinstance(data['shops'], list):
        print('ERROR: Invalid JSON structure - shops array not found')
        sys.exit(1)
    
    print(f'JSON valid: {len(data[\"shops\"])} shops found')
    
    if 'metadata' in data:
        metadata = data['metadata']
        if 'collection_date' in metadata:
            print(f'Collection date: {metadata[\"collection_date\"]}')
        if 'sources' in metadata:
            print(f'Sources: {\", \".join(metadata[\"sources\"])}')

except json.JSONDecodeError as e:
    print(f'ERROR: Invalid JSON: {e}')
    sys.exit(1)
except Exception as e:
    print(f'ERROR: {e}')
    sys.exit(1)
"
    
    if [ $? -eq 0 ]; then
        success "JSON file validation passed"
        return 0
    else
        error "JSON file validation failed"
        return 1
    fi
}

# Generate summary report
generate_report() {
    log "Generating summary report..."
    
    if [ -f "$JSON_FILE" ]; then
        python3 -c "
import json
from collections import Counter

with open('$JSON_FILE', 'r') as f:
    data = json.load(f)

shops = data['shops']
metadata = data.get('metadata', {})

print('\\n=== NYC SMOKE SHOP DATA SUMMARY ===')
print(f'Collection Date: {metadata.get(\"collection_date\", \"Unknown\")}')
print(f'Total Shops: {len(shops)}')

# Borough breakdown
boroughs = Counter(shop.get('borough', 'Unknown') for shop in shops)
print('\\nBy Borough:')
for borough, count in boroughs.most_common():
    print(f'  {borough}: {count}')

# Status breakdown
statuses = Counter(shop.get('status', 'Unknown') for shop in shops)
print('\\nBy Status:')
for status, count in statuses.most_common():
    print(f'  {status}: {count}')

# Source breakdown
sources = Counter(shop.get('source', 'Unknown') for shop in shops)
print('\\nBy Source:')
for source, count in sources.most_common():
    print(f'  {source}: {count}')

# Shops with coordinates
with_coords = sum(1 for shop in shops if shop.get('latitude') and shop.get('longitude'))
print(f'\\nShops with coordinates: {with_coords}/{len(shops)} ({with_coords/len(shops)*100:.1f}%)')

# Shops with phone numbers
with_phone = sum(1 for shop in shops if shop.get('phone'))
print(f'Shops with phone numbers: {with_phone}/{len(shops)} ({with_phone/len(shops)*100:.1f}%)')

# Shops with websites
with_website = sum(1 for shop in shops if shop.get('website'))
print(f'Shops with websites: {with_website}/{len(shops)} ({with_website/len(shops)*100:.1f}%)')

print('=====================================\\n')
"
    fi
}

# Main execution
main() {
    log "NYC Smoke Shop Database Update Script Started"
    
    # Parse arguments
    parse_args "$@"
    
    # Show configuration
    log "Configuration:"
    log "  Refresh Mode: $REFRESH_MODE"
    log "  Dry Run: $DRY_RUN"
    log "  Verbose: $VERBOSE"
    log "  Skip Collection: $SKIP_COLLECTION"
    log "  Use Sample: $USE_SAMPLE"
    log "  JSON File: $JSON_FILE"
    
    # Setup Python environment (only if we need to collect data)
    if [ "$SKIP_COLLECTION" = false ] && [ "$USE_SAMPLE" = false ]; then
        setup_python_env
    fi
    
    # Collect data
    collect_data
    
    # Validate JSON
    if ! validate_json; then
        exit 1
    fi
    
    # Generate report
    generate_report
    
    # Import to database
    import_data
    
    success "Database update completed successfully!"
    
    if [ "$DRY_RUN" = true ]; then
        warning "This was a dry run - no actual changes were made to the database"
    fi
    
    log "Script completed at $(date)"
}

# Run main function
main "$@"
