#!/bin/bash

# Script to fix missing includes in all API files
# Adds the auth_helper.php include to all API endpoints that need it

API_DIR="/Users/ryan/development/smokeout_nyc/api"
AUTH_HELPER_LINE="require_once __DIR__ . '/auth_helper.php';"

# List of files that need the auth helper
FILES_TO_FIX=(
    "advanced_game.php"
    "ai_risk_meter.php" 
    "ar_visualization.php"
    "data_service.php"
    "game.php"
    "insurance_marketplace.php"
    "legal_network.php"
    "membership.php"
    "nft_integration.php"
    "premium_features.php"
    "shop_owner.php"
    "social_trading.php"
    "user_interfaces.php"
    "voice_assistant.php"
    "white_label.php"
    "accounting_tools.php"
)

echo "Fixing API includes..."

for file in "${FILES_TO_FIX[@]}"; do
    filepath="${API_DIR}/${file}"
    
    if [ -f "$filepath" ]; then
        echo "Processing $file..."
        
        # Check if auth_helper is already included
        if ! grep -q "auth_helper.php" "$filepath"; then
            # Find the line with "require_once __DIR__ . '/config/database.php';" or similar
            # And add our include after it
            sed -i '' "/require_once.*config\/database\.php/a\\
$AUTH_HELPER_LINE
" "$filepath"
            echo "  ✓ Added auth_helper include to $file"
        else
            echo "  - Auth helper already included in $file"
        fi
    else
        echo "  ✗ File not found: $file"
    fi
done

echo ""
echo "✅ API includes fix complete!"
echo ""
echo "Next steps:"
echo "1. Run the missing_tables_schema.sql to create required database tables"
echo "2. Update your .env file with proper database credentials"
echo "3. Test the API endpoints to ensure proper authentication"
